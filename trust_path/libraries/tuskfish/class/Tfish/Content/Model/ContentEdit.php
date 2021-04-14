<?php

declare(strict_types=1);

namespace Tfish\Content\Model;

/**
 * \Tfish\Content\Model\ContentEdit class file.
 * 
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     content
 */

/** 
 * Model for editing content objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\HtmlPurifier Includes HTMLPurifier library.
 * @uses        trait \Tfish\Traits\Mimetypes Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\Taglink Manage object-tag associations via taglinks.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\TraversalCheck Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 * @var         \HTMLPurifier Instance of HTMLPurifier class. 
 * @var         \Tfish\FileHandler Instance of the Tfish filehandler class.
 */
class ContentEdit
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\HtmlPurifier;
    use \Tfish\Traits\Language;
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\Taglink;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;
    private $cache;
    private $htmlPurifier;
    private $fileHandler;

    /**
     * Constructor.
     * 
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Cache $cache Instance of the Tuskfish cache class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\FileHandler $fileHandler Instance of the Tuskfish filehandler class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\FileHandler $fileHandler,
        \Tfish\Cache $cache
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->cache = $cache;
        $this->htmlPurifier = $this->getHtmlPurifier();
        $this->fileHandler = $fileHandler;
    }

    /** Actions. */

    /**
     * Edit content object.
     * 
     * @param   int $id ID of content object.
     * @param   string $lang Language of content object.
     * @return  array Content object data as associative array.
     */
    public function edit(int $id, string $lang): array
    {
        $id = ($id < 0) ? 0 : $id;

        if (!\array_key_exists($lang, $this->listLanguages())) {
            $lang = '';
        }

        $row = $this->getRow($id, $lang);

        if (empty($row)) {
            return [];
        }

        $row['tags'] = $this->getTagIds($id);

        return $row;
    }

    /**
     * Insert a content object into the database.
     * 
     * @return  bool True on success, false on failure.
     */
    public function insert(): bool
    {
        $content = $this->validateForm($_POST['content']);
        $tags = $this->validateTags($_POST['tags'] ?? []);
        $content['id'] = $this->database->maxVal('id', 'content') + 1; // Increment to next highest available ID slot.
        $content['submissionTime'] = \time();
        $content['lastUpdated'] = 0;
        $content['expiresOn'] = 0;
        $content['counter'] = 0;

        // Upload image/media files and update the file names in $content.
        $this->uploadImage($content);
        $this->uploadMedia($content);

        // Insert new content.
        if (!$this->database->insert('content', $content)) {
            return false;
        }

        if (!$this->saveTaglinks($content['id'], $content['language'], $content['type'], 'content', $tags)) {
            return false;
        }

        // Flush cache.
        $this->cache->flush();

        return true;
    }

    /**
     * Update a content object in the database.
     * 
     * @return True on success, false on failure.
     */
    public function update(): bool
    {        
        $content = $this->validateForm($_POST['content']);
        $tags = $this->validateTags($_POST['tags'] ?? []);

        $id = (int) $content['id'];
        $lang = $this->trimString($content['language']);
        $content['lastUpdated'] = \time();

        // Set image/media to currently stored values.
        $savedContent = $this->getRow($id, $lang);
        $content['image'] = $savedContent['image'];
        $content['media'] = $savedContent['media'];

        // Check if there are any redundant image/media files that should be deleted.
        if (!empty($savedContent['image']) 
            && $savedContent['image'] !== $content['image']
            && $this->soleReferenceToFile('image', $savedContent['image'])) {
                $this->fileHandler->deleteFile('image/' . $savedContent['image']);
        }

        if (!empty($savedContent['media'])
            && $savedContent['media'] !== $content['media']
            && $this->soleReferenceToFile('media', $savedContent['media'])) {
                $this->fileHandler->deleteFile('media/' . $savedContent['media']);
        }

        // Check if delete flag was set.
        if ($_POST['deleteImage'] === '1' && !empty($savedContent['image'])) {
            $content['image'] = '';

            if ($this->soleReferenceToFile('image', $savedContent['image'])) {
                $this->fileHandler->deleteFile('image/' . $savedContent['image']);
            }
        }

        if ($_POST['deleteMedia'] === '1' && !empty($savedContent['media'])) {
            $content['media'] = '';
            $content['format'] = '';
            $content['fileSize'] = 0;

            if ($this->soleReferenceToFile('media', $savedContent['media'])) {
                $this->fileHandler->deleteFile('media/' . $savedContent['media']);
            }
        }

        // Upload any new image/media files and update file names. 
        $this->uploadImage($content);
        $this->uploadMedia($content);

        // Update taglinks.
        $this->updateTaglinks($id, $content['language'], $content['type'], 'content', $tags);

        // Flush cache.
        $this->cache->flush();

        // Check if this content *used* to be a collection, ie. it's type has changed.
        $this->checkExCollection($content, $savedContent);

        // As this is being sent to storage, decode some entities that were encoded for display.
        $fieldsToDecode = ['title', 'creator', 'publisher'];

        foreach ($fieldsToDecode as $field) {
            if (isset($content[$field])) {
                $content[$field] = htmlspecialchars_decode($content[$field], ENT_NOQUOTES);
            }
        }

        // Properties that are used within attributes must have quotes encoded.
        $fieldsToDecode = ['metaTitle', 'metaSeo', 'metaDescription'];
        
        foreach ($fieldsToDecode as $field) {
            if (isset($content[$field])) {
                $content[$field] = htmlspecialchars_decode($content[$field], ENT_QUOTES);
            }
        }
        
        return $this->database->update('content', $id, $content, $content['language']);
    }

    /** Utilities. */

    /**
     * Check if the object used to be a TfCollection and delete parental references if necessary.
     * 
     * When updating an object, this method is used to check if it used to be a collection. If so,
     * other content objects referring to it as parent will need to be updated. Note that you must
     * pass in the SAVED copy of the object from the database, rather than the 'current' version, 
     * as the purpose of the method is to determine if the object *used to be* a collection.
     * 
     * @param   array $content An array of the updated content object data as key value pairs.
     * @param   array $savedContent The old content object data as currently stored in database.
     */
    private function checkExCollection(array $content, array $savedContent)
    {
        if ($savedContent['type'] === 'TfCollection' && $content['type'] !== 'TfCollection') {
            
            $criteria = $this->criteriaFactory->criteria();
            $criteria->add($this->criteriaFactory->item('parent', (int) $content['id']));

            if (!$this->database->updateAll('content', array('parent' => 0), $criteria)) {
                \trigger_error(TFISH_ERROR_PARENT_UPDATE_FAILED, E_USER_NOTICE);
            }
        }
    }

    /**
     * Get all colllection-type content objects.
     * 
     * @return  array Array of collections.
     */
    public function collections()
    {        
        $criteria = $this->criteriaFactory->criteria();

        $criteria->add($this->criteriaFactory->item('type', 'TfCollection'));
        $criteria->add($this->criteriaFactory->item('onlineStatus', 1));
        $criteria->setSort('title');
        $criteria->setOrder('ASC');
        $criteria->setSecondarySort('submissionTime');
        $criteria->setSecondaryOrder('DESC');

        $statement = $this->database->select('content', $criteria);

        if(!$statement) {
            \trigger_error(TFISH_ERROR_NO_RESULT, E_USER_ERROR);
        }

        return $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
    }

    /**
     * Get a single content object as an associative array.
     * 
     * @param   int $id ID of content object.
     * @return  array
     */
    private function getRow(int $id, string $lang): array
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));
        $criteria->add($this->criteriaFactory->item('language', $lang));

        $row = $this->database->select('content', $criteria)
            ->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) ? $row : [];
    }

    /**
     * Checks to see if an image or media file attachment is in use by another translation of this
     * content object, in order to avoid deleting files that are in service.
     *
     * @param string $field Name of file field, ie. 'image' or 'media'.
     * @param string $filename Name of file.
     * @return boolean True if there is only one reference to a file, false if there are more.
     */
    private function soleReferenceToFile(string $field, string $filename): bool {
        
        if ($field !== 'image' && $field !== 'media') {
            return false;
        }

        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item($field, $filename));
        $count = $this->runCount($criteria);
        
        return $count === 1 ? true : false;
    }

    /**
     * Run the count query.
     * 
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @return  int Count.
     */
    private function runCount(\Tfish\Criteria $criteria): int
    {
        return $this->database->selectCount('content', $criteria);
    }

    /**
     * Move an uploaded image from temporary to permanent storage location.
     * 
     * @param   array $content Content object as associative array.
     */
    private function uploadImage(array & $content)
    {
        if (!empty($_FILES['content']['name']['image'])) {
            $filename = $this->trimString($_FILES['content']['name']['image']);
            $cleanFilename = $this->fileHandler->uploadFile($filename, 'image');
            
            if ($cleanFilename) {
                $content['image'] = $cleanFilename;
            }
        }
    }

    /**
     * Move an uploaded media file from temporary to permanent storage location.
     * 
     * @param   array $content Content object as associative array.
     */
    private function uploadMedia(array & $content)
    {        
        if (!empty($_FILES['content']['name']['media'])) {
            $filename = $this->trimString($_FILES['content']['name']['media']);
            
            $cleanFilename = $this->fileHandler->uploadFile($filename, 'media');

            if ($cleanFilename) {
                $content['media'] = $cleanFilename;
                $extension = pathinfo($cleanFilename, PATHINFO_EXTENSION);
                $content['format'] = $this->listMimetypes()[$extension];
                $content['fileSize'] = $_FILES['content']['size']['media'];
            }
        }
    }

    /**
     * Validate submitted form data for content object.
     * 
     * @param   array $form Submitted form data.
     * @return  array Validated form data.
     */
    public function validateForm(array $form): array
    {
        $clean = [];

        $type = $this->trimString($form['type'] ?? '');

        if (!\array_key_exists($type, $this->listTypes())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
        }

        $clean['type'] = $type;

        $id = ((int) ($form['id'] ?? 0));
        if ($id > 0) $clean['id'] = $id;

        $lang = $this->trimString($form['language'] ?? '');

        if (!\array_key_exists($lang, $this->listLanguages())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['language'] = $lang;
        
        $clean['title'] = $this->trimString($form['title'] ?? '');

        // Validate HTML fields.
        $teaser = $this->trimString($form['teaser'] ?? '');
        $teaser = \str_replace(TFISH_LINK, 'TFISH_LINK', $teaser);
        $clean['teaser'] = $this->htmlPurifier->purify($teaser);

        $description = $this->trimString($form['description'] ?? '');
        $description = \str_replace(TFISH_LINK, 'TFISH_LINK', $description);
        $clean['description'] = $this->htmlPurifier->purify($description);

        $clean['creator'] = $this->trimString($form['creator'] ?? '');
        $clean['media'] = $this->trimString($form['media'] ?? '');

        $format = $this->trimString($form['format'] ?? '');
        
        if (!empty($format) && !\in_array($format, $this->listMimetypes())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_MIMETYPE, E_USER_ERROR);
        }

        $clean['format'] = $format;
        $clean['fileSize'] = (int) ($form['fileSize'] ?? 0);

        $image = $this->trimString($form['image'] ?? '');

        if (!empty($image) && $this->hasTraversalorNullByte($image)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
        }

        if (!empty($image) && !\in_array($image, $this->listImageMimetypes())) {
            \trigger_error(TFISH_ERROR_ILLEGAL_MIMETYPE, E_USER_ERROR);
        }

        $clean['image'] = $image;

        $clean['caption'] = $this->trimString($form['caption'] ?? '');
        $clean['date'] = (string) ($form['date'] ?? 0);
        $clean['submissionTime'] = (int) ($form['submissionTime'] ?? 0);
        $clean['lastUpdated'] = (int) ($form['lastUpdated'] ?? 0);
        $clean['expiresOn'] = (int) ($form['expiresOn'] ?? 0);
        $clean['counter'] = (int) ($form['counter'] ?? 0);
        $clean['onlineStatus'] = (int) ($form['onlineStatus'] ?? 0);
        $clean['parent'] = (int) ($form['parent'] ?? 0);
        $clean['rights'] = (int) ($form['rights'] ?? 0);
        $clean['publisher'] = $this->trimString($form['publisher'] ?? '');
        $clean['metaTitle'] = $this->trimString($form['metaTitle'] ?? '');
        $clean['metaDescription'] = $this->trimString($form['metaDescription'] ?? '');
        $clean['metaSeo'] = $this->trimString($form['metaSeo'] ?? '');
        
        return $clean;
    }
}
