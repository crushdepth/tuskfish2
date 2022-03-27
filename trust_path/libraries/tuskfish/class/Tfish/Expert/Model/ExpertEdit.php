<?php

declare(strict_types=1);

namespace Tfish\Expert\Model;

/**
 * \Tfish\Expert\Model\ExpertEdit class file.
 * 
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     expert
 */

/** 
 * Model for editing expert objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     expert
 * @uses        trait \Tfish\Traits\Content\ContentTypes Provides definition of permitted expert object types.
 * @uses        trait \Tfish\Traits\HtmlPurifier Includes HTMLPurifier library.
 * @uses        trait \Tfish\Traits\Mimetypes Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\Taglink Manage object-tag associations via taglinks.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\TraversalCheck Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\UrlCheck Validate that a URL meets the specification.
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Cache Instance of the Tfish cache class.
 * @var         \HTMLPurifier Instance of HTMLPurifier class. 
 * @var         \Tfish\FileHandler Instance of the Tfish filehandler class.
 */
class ExpertEdit
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\HtmlPurifier;
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\Taglink;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\UrlCheck;
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
     * Edit expert object.
     * 
     * @param   int $id ID of expert object.
     * @return  array Expert object data as associative array.
     */
    public function edit(int $id): array
    {
        $row = $this->getRow($id);

        if (empty($row)) {
            return [];
        }

        $row['tags'] = $this->getTagIds($id);

        return $row;
    }

    /**
     * Insert an expert object into the database.
     * 
     * @return  bool True on success, false on failure.
     */
    public function insert(): bool
    {
        $content = $this->validateForm($_POST['content']);
        $tags = $this->validateTags($_POST['tags'] ?? []);

        $content['submissionTime'] = \time();
        $content['lastUpdated'] = 0;
        $content['expiresOn'] = 0;
        $content['counter'] = 0;

        // Upload image/media files and update the file names in $content.
        $this->uploadImage($content);

        // Insert new content.
        if (!$this->database->insert('expert', $content)) {
            return false;
        }

        // Insert the taglinks, which requires knowledge of the ID.
        $contentId = $this->database->lastInsertId();
        if (!$this->saveTaglinks($contentId, $content['type'], 'expert', $tags)) {
            return false;
        }

        // Flush cache.
        $this->cache->flush();

        return true;
    }

    /**
     * Update an expert object in the database.
     * 
     * @return True on success, false on failure.
     */
    public function update(): bool
    {        
        $content = $this->validateForm($_POST['content']);
        $tags = $this->validateTags($_POST['tags'] ?? []);

        $id = (int) $content['id'];
        $content['lastUpdated'] = \time();

        // Set image/media to currently stored values.
        $savedContent = $this->getRow($id);
        $content['image'] = $savedContent['image'];

        // Check if there are any redundant image/media files that should be deleted.
        if (!empty($savedContent['image']) && $savedContent['image'] !== $content['image']) {
            $this->fileHandler->deleteFile('image/' . $savedContent['image']);
        }

        // Check if delete flag was set.
        if ($_POST['deleteImage'] === '1' && !empty($savedContent['image'])) {
            $content['image'] = '';
            $this->fileHandler->deleteFile('image/' . $savedContent['image']);
        }

        // Upload any new image files and update file names. 
        $this->uploadImage($content);

        // Update taglinks.
        $this->updateTaglinks($id, $content['type'], 'expert', $tags);

        // Flush cache.
        $this->cache->flush();

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
        
        return $this->database->update('expert', $id, $content);
    }

    /** Utilities. */

    /**
     * Get a single expert object as an associative array.
     * 
     * @param   int $id ID of expert object.
     * @return  array
     */
    private function getRow(int $id): array
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        $row = $this->database->select('exp[ert', $criteria)
            ->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) ? $row : [];
    }

    /**
     * Move an uploaded image from temporary to permanent storage location.
     * 
     * @param   array $expert Content object as associative array.
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
     * Validate submitted form data for expert object.
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

        $template = $this->trimString($form['template'] ?? '');

        if (!\in_array($template, $this->listTemplates()[$clean['type']])) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TEMPLATE, E_USER_ERROR);
        }

        $clean['template'] = $template;

        $id = ((int) ($form['id'] ?? 0));
        if ($id > 0) $clean['id'] = $id;
        
        $clean['title'] = $this->trimString($form['title'] ?? '');

        // Validate HTML fields.
        $teaser = $this->trimString($form['teaser'] ?? '');
        $teaser = \str_replace(TFISH_LINK, 'TFISH_LINK', $teaser);
        $clean['teaser'] = $this->htmlPurifier->purify($teaser);

        $description = $this->trimString($form['description'] ?? '');
        $description = \str_replace(TFISH_LINK, 'TFISH_LINK', $description);
        $clean['description'] = $this->htmlPurifier->purify($description);

        $clean['creator'] = $this->trimString($form['creator'] ?? '');

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
        $clean['language'] = $this->trimString($form['language'] ?? '');
        $clean['rights'] = (int) ($form['rights'] ?? 0);
        $clean['publisher'] = $this->trimString($form['publisher'] ?? '');
        $clean['metaTitle'] = $this->trimString($form['metaTitle'] ?? '');
        $clean['metaDescription'] = $this->trimString($form['metaDescription'] ?? '');
        $clean['metaSeo'] = $this->trimString($form['metaSeo'] ?? '');

        return $clean;
    }
}
