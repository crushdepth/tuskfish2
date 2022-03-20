<?php

declare(strict_types=1);

namespace Tfish\Content\Model;

/**
 * \Tfish\Content\Model\Enclosure class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for streaming file attachments (enclosures).
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Mimetypes	Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 */

class Enclosure
{
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;

    /**
     * Constructor.
     * 
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     */
    public function __construct(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
    }

    /**
     * Initiate streaming of a downloadable media file associated with a content object.
     * 
     * DOES NOT WORK WITH COMPRESSION ENABLED IN OUTPUT BUFFER. This method acts as an intermediary
     * to provide access to uploaded file resources that reside outside of the web root, while
     * concealing the real file path and name. Use this method to provide safe user access to
     * uploaded files. If anything nasty gets uploaded nobody will be able to execute it directly
     * through the browser.
     * 
     * @param int $id ID of the associated content object.
     * @param string $filename An alternative name (rename) for the file you wish to transfer,
     * excluding extension.
     * @return bool True on success, false on failure. 
     */
    public function streamFileToBrowser(int $id, string $filename = '')
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
        }

        $filename = !empty($filename) ? $this->trimString($filename) : '';
        $result = $this->_streamFileToBrowser($id, $filename);
        $this->database->close();
        exit;
    }

    /** @internal */
    private function _streamFileToBrowser(int $id, string $filename)
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));
        $statement = $this->database->select('content', $criteria, ['type', 'media', 'onlineStatus']);
        
        if (!$statement) {
            \trigger_error(TFISH_ERROR_NO_STATEMENT, E_USER_NOTICE);
        }
        
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        
        if ($row && $row['onlineStatus'] == '1') {
            $media = $row['media'] ?? false;
            
            if ($media && \is_readable(TFISH_MEDIA_PATH . $media)) {
                \ob_start();
                $filepath = TFISH_MEDIA_PATH . $media;
                $filename = empty($filename) ? \pathinfo($filepath, PATHINFO_FILENAME) : $filename;
                $fileExtension = \pathinfo($filepath, PATHINFO_EXTENSION);
                $fileSize = \filesize(TFISH_MEDIA_PATH . $media);
                $mimetypeList = $this->listMimetypes();
                $mimetype = $mimetypeList[$fileExtension];

                // Update counter. 
                // Speculative fix for DB locked error: Moved this above session_write_close().
                if ($row['type'] === 'TfDownload') {
                    $this->updateCounter($id);
                }

                // Must call session_write_close() first otherwise the script gets locked.
                \session_write_close();
                
                // Output the header.
                $this->_outputHeader($filename, $fileExtension, $mimetype, $fileSize, $filepath);
                
            } else {
                return false;
            }
        } else {
            $this->database->close();
            \header("HTTP/1.0 404 Not Found");
            exit;
        }
    }
    
    /** @internal */
    private function _outputHeader($filename, $fileExtension, $mimetype, $fileSize, $filepath)
    {
        // Prevent caching.
        \header("Pragma: public");
        \header("Expires: -1");
        \header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        // Set file-specific headers.
        \header('Content-Disposition: attachment; filename="' . $filename . '.' . $fileExtension . '"');
        \header("Content-Type: " . $mimetype);
        \header("Content-Length: " . $fileSize);
        \ob_clean();
        \flush();
        \readfile($filepath);
    }

    private function updateCounter(int $id)
    {
        $this->database->updateCounter($id, 'content', 'counter');
    }
}
