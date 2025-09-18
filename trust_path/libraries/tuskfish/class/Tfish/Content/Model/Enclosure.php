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
 * @uses        trait \Tfish\Traits\Group Whitelist of user groups on system and bitmask authorisation tests.
 * @uses        trait \Tfish\Traits\Mimetypes	Provides a list of common (permitted) mimetypes for file uploads.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 */

class Enclosure
{
    use \Tfish\Traits\Group;
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\ValidateString;

    private \Tfish\Database $database;
    private \Tfish\CriteriaFactory $criteriaFactory;
    private \Tfish\Session $session;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     */
    public function __construct(\Tfish\Database $database, \Tfish\CriteriaFactory $criteriaFactory, \Tfish\Session $session)
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->session = $session;
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
     * @return void
     */
    public function streamFileToBrowser(int $id, string $filename = ''): void
    {
        if ($id < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
            exit;
        }

        $filename = !empty($filename) ? $this->trimString($filename) : '';
        $this->_streamFileToBrowser($id, $filename);
        $this->database->close();
        exit;
    }

    /** @internal */
    private function _streamFileToBrowser(int $id, string $filename)
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));
        $statement = $this->database->select('content', $criteria, 
            ['type', 'media', 'expiresOn', 'accessGroups', 'onlineStatus']);

        if (!$statement) {
            \trigger_error(TFISH_ERROR_NO_STATEMENT, E_USER_NOTICE);
        }

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $statement->closeCursor();

        // Check that object is online and hasn't expired.
        if ($row && $row['onlineStatus'] == '1'
            && (empty($row['expiresOn']) || $row['expiresOn'] >= \time())) {

            // Authorisation check.
            $contentMask = (int) $row['accessGroups'];
            $userMask = (int) $this->session->verifyPrivileges();

            // 
            if (!$this->canAccess($userMask, $contentMask)) {
                if ($userMask === 0) {
                    $this->setNextUrl($_SERVER['REQUEST_URI'] ?? '/');
                    $this->setRedirectTitle(TFISH_MEMBER_CONTENT);
                    $this->setRedirectMessage(TFISH_PLEASE_LOGIN);
                    \header('Location: ' . TFISH_URL . 'login/', true, 303);
                    exit;
                }

                $this->setRedirectTitle(TFISH_RESTRICTED_ACCESS);
                $this->setRedirectMessage(TFISH_RESTRICTED_ACCESS_MESSAGE);
                \header('Location: ' . TFISH_URL . 'restricted/', true, 303);
                exit;
            }

            $media = $row['media'] ?? false;

            if ($media && \is_readable(TFISH_MEDIA_PATH . $media)) {
                \ob_start();
                $filepath = TFISH_MEDIA_PATH . $media;
                $filename = empty($filename) ? \pathinfo($filepath, PATHINFO_FILENAME) : $filename;
                $fileExtension = \strtolower(\pathinfo($filepath, PATHINFO_EXTENSION));
                $fileSize = \filesize(TFISH_MEDIA_PATH . $media);
                $mimetypeList = $this->listMimetypes();
                $mimetype = $mimetypeList[$fileExtension];

                // Update counter.
                if ($row['type'] === 'TfDownload') {
                    $this->updateCounter($id);
                }

                // Must call session_write_close() first otherwise the script gets locked.
                \session_write_close();

                // Output the header.
                $this->_outputHeader($id, $filename, $fileExtension, $mimetype, $fileSize, $filepath);

            } else {
                return false;
            }
        } else {
            $this->database->close();
            \header('Location: ' . TFISH_URL . 'error/', true, 303);
            exit;
        }
    }

    /** @internal */
    private function _outputHeader($id, $filename, $fileExtension, $mimetype, $fileSize, $filepath)
    {
        // Prevent caching.
        \header("Pragma: public");
        \header("Expires: -1");
        \header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

        // Set canonical link to prevent external sites gaining authority over our resource!
        \header('Link: <' . TFISH_ENCLOSURE_URL . $id . '>; rel="canonical"');

        // Set file-specific headers.
        \header('Content-Disposition: attachment; filename="' . $filename . '.' . $fileExtension . '"');
        \header("Content-Type: " . $mimetype);
        \header("Content-Length: " . $fileSize);
        \ob_clean();
        \flush();
        \readfile($filepath);
    }

    /**
     * Update download counter.
     * 
     * @param int $id ID of content object.
     * @return void
     */
    private function updateCounter(int $id): void
    {
        $this->database->updateCounter($id, 'content', 'counter');
    }

    /**
     * Set onwards redirection path after successful authentication.
     *
     * @param string $path
     * @return void
     */
    public function setNextUrl(string $path): void
    {
        $this->session->setNextUrl($path);
    }

    /**
     * Set redirect page title.
     * 
     * @return void
     */
    public function setRedirectTitle(string $title): void
    {
        $this->session->setRedirectTitle($title);
    }

    /**
     * Set redirect page context message.
     * 
     * @return void
     */
    public function setRedirectMessage(string $message): void
    {
        $this->session->setRedirectMessage($message);
    }
}
