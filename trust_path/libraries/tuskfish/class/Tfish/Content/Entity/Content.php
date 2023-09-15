<?php

declare(strict_types=1);

namespace Tfish\Content\Entity;

/**
 * \Tfish\Content\Entity\Content class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     content
 */

/**
 * Represents a single content object.
 *
 * Content objects are the base data class for Tuskfish CMS. The 'type' property determines the template that
 * will be used to display the object, and some aspects of its behaviour.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes	Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\Language	Returns a list of languages in use by the system.
 * @uses        trait \Tfish\Traits\Metadata HTML metadata tag support.
 * @uses        trait \Tfish\Traits\Mimetypes	Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\ResizeImage	Resize and cache copies of image files to allow them to be used at different sizes in templates.
 * @uses        trait \Tfish\Traits\Rights	Provides a common list of intellectual property rights licenses.
 * @uses        trait \Tfish\Traits\Tag Support for tagging of content.
 * @uses        trait \Tfish\Traits\TraversalCheck	Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\UrlCheck    Validate that a URL meets the specification.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         int $id Auto-increment, set by database.
 * @var         string $type Content object type eg. TfArticle etc. [ALPHA]
 * @var         string $title The name of this content.
 * @var         string $teaser A short (one paragraph) summary or abstract for this content. [HTML]
 * @var         string $description The full article or description of the content. [HTML]
 * @var         string $creator Author.
 * @var         string $media An associated download/audio/video file. [FILEPATH OR URL]
 * @var         string $externalMedia An external media file. [URL]
 * @var         string $format Mimetype
 * @var         int $fileSize Specify in bytes.
 * @var         string image An associated image file, eg. a screenshot a good way to handle it. [FILEPATH OR URL]
 * @var         string $caption Caption of the image file.
 * @var         string $date Date of publication expressed as a string.
 * @var         int $submissionTime Timestamp representing submission time.
 * @var         int $lastUpdated Timestamp representing last time this object was updated.
 * @var         string $expiresOn Date for this object expressed as a string.
 * @var         int $counter Number of times this content was viewed or downloaded.
 * @var         int $onlineStatus Toggle object on or offline.
 * @var         int $parent A source work or collection of which this content is part.
 * @var         string $language Future proofing.
 * @var         int $rights Intellectual property rights scheme or license under which the work is distributed.
 * @var         string $publisher The entity responsible for distributing this work.
 * @var         string $template The user-side template for displaying this object.
 * @var         string $module The module that handles this content type (not persistent).
 */

class Content
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\Language;
    use \Tfish\Traits\Metadata;
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\ResizeImage;
    use \Tfish\Traits\Rights;
    use \Tfish\Traits\Tag;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\UrlCheck;
    use \Tfish\Traits\ValidateString;

    private $id = 0;
    private $type = '';
    private $title = '';
    private $teaser = '';
    private $description = '';
    private $creator = '';
    private $media = '';
    private $externalMedia = '';
    private $format = '';
    private $fileSize = 0;
    private $image = '';
    private $caption = '';
    private $date = '';
    private $submissionTime = 0;
    private $lastUpdated = 0;
    private $expiresOn = '';
    private $counter = 0;
    private $minimumViews = 0;
    private $onlineStatus = 0;
    private $parent = 0;
    private $language = '';
    private $rights = 1;
    private $publisher = '';
    private $template = '';
    private $module = 'content';

    /**
     * Load properties.
     *
     * Parameters are validated by the respective setters.
     *
     * @param   array $row Data to load into properties.
     * @param   bool $convertUrlToConstant Convert the TFISH_LINK constant to a URL and vice-versa
     * to aid portability.
     */
    public function load(array $row, bool $convertUrlToConstant = true)
    {
        $this->setId((int) ($row['id'] ?? 0));
        $this->setType((string) ($row['type'] ?? ''));
        $this->setTemplate((string) ($row['template'] ?? ''));
        $this->setTitle((string) ($row['title'] ?? ''));
        $this->setTeaser((string) ($row['teaser'] ?? ''));
        $this->setDescription((string) ($row['description'] ?? ''));
        $this->setCreator((string) ($row['creator'] ?? ''));
        $this->setMedia((string) ($row['media'] ?? ''));
        $this->setExternalMedia((string) ($row['externalMedia'] ?? ''));
        $this->setFormat((string) ($row['format'] ?? ''));
        $this->setFileSize((int) ($row['fileSize'] ?? 0));
        $this->setImage((string) ($row['image'] ?? ''));
        $this->setCaption((string) ($row['caption'] ?? ''));
        $this->setDate((string) ($row['date'] ?? ''));
        $this->setSubmissionTime((int) ($row['submissionTime'] ?? 0));
        $this->setLastUpdated((int) ($row['lastUpdated'] ?? 0));
        $this->setExpiresOn((string) ($row['expiresOn'] ?? ''));
        $this->setCounter((int) ($row['counter'] ?? 0));
        $this->setOnlineStatus((int) ($row['onlineStatus'] ?? 1));
        $this->setParent((int) ($row['parent'] ?? 0));
        $this->setLanguage((string) ($row['language'] ?? 'en'));
        $this->setRights((int) ($row['rights'] ?? 1));
        $this->setPublisher((string) ($row['publisher'] ?? ''));
        $this->setTags($row['tags'] ?? []);
        $this->setMetaTitle((string) ($row['metaTitle'] ?? ''));
        $this->setMetaDescription((string) ($row['metaDescription'] ?? ''));
        $this->setMetaSeo((string) ($row['metaSeo'] ?? ''));

        // Convert URLs back to TFISH_LINK for insertion or update, to aid portability.
        // Convert base url to TFISH_LINK (true) or TFISH_LINK to base url (false).
        if (isset($this->teaser) && !empty($row['teaser'])) {
            $teaser = $this->convertBaseUrlToConstant($row['teaser'], $convertUrlToConstant);
            $this->setTeaser($teaser);
        }

        if (isset($this->description) && !empty($row['description'])) {
            $description = $this->convertBaseUrlToConstant($row['description'], $convertUrlToConstant);
            $this->setDescription($description);
        }
    }

    /** Utilities */
    /**
     * Converts bytes to a human readable units (KB, MB, GB etc).
     *
     * @return string Bytes expressed as convenient human readable units.
     */
    public function bytesToHumanReadable()
    {
        $bytes = $this->fileSize;
        $unit = $val = '';

        if ($bytes >= 0 && $bytes < ONE_KILOBYTE) {
            $unit = ' bytes';
            $val = $bytes;
        } elseif ($bytes >= ONE_KILOBYTE && $bytes < ONE_MEGABYTE) {
            $unit = ' KB';
            $val = ($bytes / ONE_KILOBYTE);
        } elseif ($bytes >= ONE_MEGABYTE && $bytes < ONE_GIGABYTE) {
            $unit = ' MB';
            $val = ($bytes / ONE_MEGABYTE);
        } else {
            $unit = ' GB';
            $val = ($bytes / ONE_GIGABYTE);
        }

        $val = round($val, 2);

        return $val . ' ' . $unit;
    }

    /**
     * Convert the site base URL to the TFISH_LINK constant and vice versa.
     *
     * This aids site portability. The URL is stored as a constant in the database,
     * but is converted to actual URL on display. If the domain changes at some point
     * all the references to TFISH_LINK will update automatically.
     *
     * @param   string $html HTML field to search and replace.
     * @param   bool $convertToConstant
     */
    private function convertBaseUrlToConstant(string $html, bool $convertToConstant = false)
    {
        if ($convertToConstant === true) {
            $html = \str_replace(TFISH_LINK, 'TFISH_LINK', $html);
        } else {
                $html = \str_replace('TFISH_LINK', TFISH_LINK, $html);
        }

        return $html;
    }

    /**
     * Unset properties that are not stored in the database.
     *
     * @param   array $keyValues Content object as associative array.
     * @return  array Content object with non-persistent properties unset.
     */
    private function unsetNonPersistent(array $keyValues): array
    {
        unset(
            $keyValues['tags'],
            $keyValues['module']
            );

        return $keyValues;
    }

    /**
     * Return a URL (permalink) to a content object.
     *
     * @param   string $customRoute Override to customise the URL.
     * @return  string $url.
     */
    public function url(string $customRoute = ''): string
    {
        $url = empty($customRoute) ? TFISH_PERMALINK_URL : TFISH_URL;

        if (!empty($customRoute)) {
            $url .= $this->trimString($customRoute);
        }

        $url .= '?id=' . $this->id;

        if (!empty($this->metaSeo)) {
            $url .= '&amp;title=' . $this->encodeQueryString($this->metaSeo);
        }

        return $url;
    }

    /**
     * Url-encode the query string segment of a URL.
     *
     * @param   string $url Query string to encode.
     * @return  string Encoded URL.
     */
    private function encodeQueryString(string $url): string
    {
        $url = $this->trimString($url); // Trim control characters, verify UTF-8 character set.
        return \rawurlencode($url); // Encode characters to make them URL safe.
    }

    /** Getters and setters */

    /**
     * Return ID.
     *
     * @return int
     */
    public function id(): int
    {
        return (int) $this->id;
    }

    /**
     * Set ID
     *
     * @param   int $id ID of content object.
     */
    public function setId(int $id)
    {
        if ($id < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->id = $id;
    }

    /**
     * Return title.
     *
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Set title
     *
     * @param   string $title Title of content object.
     */
    public function setTitle(string $title)
    {
        $this->title = $this->trimString($title);
    }

    /**
     * Return type of content object.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type;
    }

    /**
     * Set type.
     *
     * @param   string $type Type of content object.
     */
    public function setType(string $type)
    {
        $type = $this->trimString($type);

        if (\array_key_exists($type, $this->listTypes())) {
            $this->type = $type;
        } else {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_ERROR);
        }
    }

    /**
     * Return teaser.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function teaser(): string
    {
        return $this->teaser;
    }

    /**
     * Return teaser with TFISH_LINK constant converted to URL.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function teaserForDisplay(): string
    {
        $teaser = \str_replace('TFISH_LINK', TFISH_LINK, $this->teaser);
        return $teaser;
    }

    /**
     * Set teaser.
     *
     * @param   string $teaser HTML teaser.
     */
    public function setTeaser(string $teaser)
    {
        $this->teaser = $this->trimString($teaser);
    }

    /**
     * Return description.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Return description with TFISH_LINK constant converted to URL.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function descriptionForDisplay(): string
    {
        $description = \str_replace('TFISH_LINK', TFISH_LINK, $this->description);
        return $description;
    }

    /**
     * Set description.
     *
     * @param   string $description HTML description.
     */
    public function setDescription(string $description)
    {
        $this->description = $this->trimString($description);
    }

    /**
     * Return creator.
     *
     * @return string
     */
    public function creator(): string
    {
        return $this->creator;
    }

    /**
     * Set creator.
     *
     * @param   string $creator Author of this content.
     */
    public function setCreator(string $creator)
    {
        $this->creator = $this->trimString($creator);
    }

    /**
     * Return media file name.
     *
     * @return string
     */
    public function media(): string
    {
        return $this->media;
    }

    /**
     * Set media file name.
     *
     * @param   string $filename Media file name.
     */
    public function setMedia(string $filename)
    {
        $filename = $this->trimString($filename);

        if ($this->hasTraversalorNullByte($filename)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit; // Hard stop due to high probability of abuse.
        }

        // Video files are now assumed to be hosted externally so this should be a URL.
        if ($this->type === 'TfVideo') {
            $this->media = $this->isUrl($filename) ? $filename : '';

            return;
        }

        $whitelist = $this->listMimetypes();
        $extension = \mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION), 'UTF-8');

        if (empty($extension) || (!empty($extension) && !\array_key_exists($extension, $whitelist))) {
            $this->media = '';
            $this->format = '';
            $this->fileSize = '';
        } else {
            $this->media = $filename;
        }
    }

    /**
     * Return file extension.
     *
     * @return string
     */
    public function extension(): string
    {
        return !empty($this->format) ? \array_search($this->format, $this->listMimetypes()) : '';
    }

    /**
     * Return format (mimetype).
     *
     * @return string
     */
    public function format(): string
    {
        return !empty($this->format) ? $this->format : '';
    }

    /**
     * Set format.
     *
     * @param   string $format Mimetype of media attachment.
     */
    public function setFormat(string $format)
    {
        $format = $this->trimString($format);
        $whitelist = $this->listMimetypes();

        if (!empty($format) && !\in_array($format, $whitelist, true)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_MIMETYPE, E_USER_ERROR);
        }

        $this->format = $format;
    }

    /**
     * Return the raw file size (bytes) of media attachment.
     *
     * @return int
     */
    public function fileSize(): int
    {
        return (int) $this->fileSize;
    }

    /**
     * Return file size of media attachment, formatted for display (bytes / KB / MB / GB etc).
     *
     * @return string
     */
    public function fileSizeForDisplay(): string
    {
        return $this->bytesToHumanReadable();
    }

    /**
     * Set file size of media attachment.
     *
     * @param   int $fileSize File size in bytes.
     */
    public function setFileSize(int $fileSize)
    {
        if ($fileSize < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->fileSize = $fileSize;
    }

    /**
     * Return external media URL.
     */
    public function externalMedia(): string
    {
        return $this->externalMedia;
    }

    public function setExternalMedia(string $url)
    {
        $url = $this->trimString($url);

        if (!empty($url) && !$this->isUrl($url)) {
            \trigger_error(TFISH_ERROR_NOT_URL, E_USER_ERROR);
        }

        $this->externalMedia = $url;
    }

    /**
     * Return image name.
     *
     * @return string
     */
    public function image(): string
    {
        return $this->image;
    }

    /**
     * Set image.
     *
     * @param   string $filename Name of image file.
     */
    public function setImage(string $filename)
    {
        $filename = $this->trimString($filename);

        if ($this->hasTraversalorNullByte($filename)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit; // Hard stop due to high probability of abuse.
        }

        $whitelist = $this->listImageMimetypes();
        $extension = \mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION), 'UTF-8');

        if (!empty($extension) && !\array_key_exists($extension, $whitelist)) {
            $this->image = '';
            \trigger_error(TFISH_ERROR_ILLEGAL_MIMETYPE, E_USER_ERROR);
        } else {
            $this->image = $filename;
        }
    }

    /**
     * Return caption of image.
     *
     * @return string
     */
    public function caption(): string
    {
        return $this->caption;
    }

    /**
     * Set caption.
     *
     * @param   string $caption Caption to image file.
     */
    public function setCaption(string $caption)
    {
        $this->caption = $this->trimString($caption);
    }

    /**
     * Return date as a DateTime object that can be manipulated.
     *
     * @return string
     */
    public function date(): \DateTime
    {
        return \date_create($this->date);
    }

    /**
     * Set date.
     *
     * @param   string $date
     */
    public function setDate(string $date)
    {
        $date = $this->trimString($date);
        $checkDate = \date_parse_from_format('Y-m-d', $date);

        if (!$checkDate || $checkDate['warning_count'] > 0 || $checkDate['error_count'] > 0) {
            $date = \date(DATE_RSS, \time());
            \trigger_error(TFISH_ERROR_BAD_DATE_DEFAULTING_TO_TODAY, E_USER_WARNING);
        }

        $this->date = $date;
    }

    /**
     * Return meta information about content object.
     *
     * @return string
     */
    public function info(): string
    {
        $info = [];

        if ($this->creator)
            $info[] = $this->creator;

        if ($this->counter >= $this->minimumViews) {
            $suffix = ($this->type == 'TfDownload') ? TFISH_DOWNLOADS : TFISH_VIEWS;
            $info[] = $this->counter . ' ' . $suffix;
        }

        if ($this->format)
            $info[] = '.' . $this->extension();

        if ($this->fileSize)
            $info[] = $this->fileSizeForDisplay();

        return \implode(' | ', $info);
    }

    /**
     * Return submission time.
     *
     * @return int Timestamp.
     */
    public function submissionTime(): int
    {
        return (int) $this->submissionTime;
    }

    /**
     * Set submission time.
     *
     * @param   int $timestamp
     */
    public function setSubmissionTime(int $timestamp)
    {
        if ($timestamp < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->submissionTime = $timestamp;
    }

    /**
     * Return last modification time.
     *
     * @return int $timestamp
     */
    public function lastUpdated(): int
    {
        return (int) $this->lastUpdated;
    }

    /**
     * Set last updated time.
     *
     * @param   int $timestamp
     */
    public function setLastUpdated(int $timestamp)
    {
        if ($timestamp < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->lastUpdated = $timestamp;
    }

    /**
     * Return expiry date.
     *
     * Expiry date is not yet implemented.
     *
     * @return string $date
     */
    public function expiresOn(): string
    {
        return (string) $this->expiresOn;
    }

    /**
     * Set expiry date.
     *
     * @param   string $date
     */
    public function setExpiresOn(string $date)
    {
        $this->expiresOn = $this->trimString($date);
    }

    /**
     * Return view/download counter.
     *
     * The counter tracks downloads for download content types, and views for everything else.
     *
     * @return int
     */
    public function counter(): int
    {
        return (int) $this->counter;
    }

    /**
     * Set view/download counter.
     *
     * @param   int $counter
     */
    public function setCounter(int $counter)
    {
        if ($counter < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->counter = $counter;
    }

    public function setMinimumViews(int $minimumViews)
    {
        if ($minimumViews < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->minimumViews = $minimumViews;
    }

    /**
     * Return online status.
     *
     * @return int 0 if offline, 1 if online.
     */
    public function onlineStatus(): int
    {
        return (int) $this->onlineStatus;
    }

    /**
     * Set online status.
     *
     * @param   int $status 0 for offline, 1 for online.
     */
    public function setOnlineStatus(int $status)
    {
        if ($status !== 0 && $status !== 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->onlineStatus = $status;
    }

    /**
     * Return parent ID.
     *
     * @return int ID of parent collection object.
     */
    public function parent(): int
    {
        return (int) $this->parent;
    }

    /**
     * Set parent.
     *
     * @param   int $parent ID of parent collection.
     */
    public function setParent(int $parent)
    {
        if ($parent < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        if ($parent === $this->id && $parent > 0) {
            \trigger_error(TFISH_ERROR_CIRCULAR_PARENT_REFERENCE);
        }

        $this->parent = $parent;
    }

    /**
     * Return language.
     *
     * @return string Two-letter ISO language code.
     */
    public function language(): string
    {
        return $this->language;
    }

    /**
     * Set language.
     *
     * @param   string $language Two-letter ISO language code.
     */
    public function setLanguage(string $language)
    {
        $language = $this->trimString($language);

        if (\array_key_exists($language, $this->listLanguages())) {
            $this->language = $language;
        }
    }

    /**
     * Return rights.
     *
     * Rights is an index to a license found in listRights();
     *
     * @return int
     */
    public function rights(): int
    {
        return (int) $this->rights;
    }

    /**
     * Set rights.
     *
     * Index to license stored in listRights().
     *
     * @param   int $rights
     */
    public function setRights(int $rights)
    {
        if (!\array_key_exists($rights, $this->listRights())) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->rights = $rights;
    }

    /**
     * Return publisher.
     *
     * @return string
     */
    public function publisher(): string
    {
        return $this->publisher;
    }

    /**
     * Set publisher.
     *
     * @param   string $publisher The publisher of this content.
     */
    public function setPublisher(string $publisher)
    {
        $this->publisher = $this->trimString($publisher);
    }

    /**
     * Return template
     *
     * @return string The user-side template for displaying this object.
     */
    public function template(): string
    {
        return $this->template;
    }

    /**
     * Set template
     *
     * @param string $template Should correspond to file name of template (without extension).
     * @return void
     */
    public function setTemplate(string $template)
    {
        $template = $this->trimString($template);

        if ($this->hasTraversalorNullByte($template)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
        }

        $this->template = $template;
    }

    /**
     * Return module.
     *
     * Deprecated.
     *
     * @return string
     */
    public function module(): string
    {
        return $this->module;
    }

    /**
     * Set module.
     *
     * Deprecated, will be removed.
     *
     * @param   string $module Name of module.
     */
    public function setModule(string $module)
    {
        $this->module = $this->trimString($module);
    }
}
