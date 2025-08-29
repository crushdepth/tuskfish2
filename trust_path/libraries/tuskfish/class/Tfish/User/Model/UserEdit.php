<?php

declare(strict_types=1);

namespace Tfish\User\Model;

/**
 * \Tfish\User\Model\UserEdit class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.1
 * @package     user
 */

/**
 * Model for editing user objects.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     user
 * @uses        trait \Tfish\Traits\EmailCheck Validate that email address conforms to specification.
 * @uses        trait \Tfish\Traits\Group Whitelist of groups permitted on the system.
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\Session $session Instance of the Tuskfish session manager class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 */
class UserEdit
{
    use \Tfish\Traits\EmailCheck;
    use \Tfish\Traits\Group;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $session;
    private $criteriaFactory;
    private $preference;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference Instance of the Tfish site preferences class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\Session $session,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference
        )
    {
        $this->database = $database;
        $this->session = $session;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
    }

    /** Actions. */

    /**
     * Edit user object.
     *
     * @param   int $id ID of user object.
     * @return  array User object data as associative array.
     */
    public function edit(int $id): array
    {
        $row = $this->getRow($id);

        if (empty($row)) {
            return [];
        }

        return $row;
    }

    /**
     * Insert a user into the database.
     *
     * @return  bool True on success, false on failure.
     */
    public function insert(): bool
    {
        if ($this->duplicateYubikeysSubmitted()) {
            \trigger_error(TFISH_ERROR_YUBIKEY_NOT_UNIQUE, E_USER_NOTICE);
            return false;
        }

        $content = $this->validateForm($_POST['content'], true);

        // If a submitted yubikey ID is not present in $content this indicates it was not unique.
        if (!empty($_POST['content']['yubikeyId']) && empty($content['yubikeyId'])) return false;
        if (!empty($_POST['content']['yubikeyId2']) && empty($content['yubikeyId2'])) return false;
        if (!empty($_POST['content']['yubikeyId3']) && empty($content['yubikeyId3'])) return false;

        // Insert new content.
        if (!$this->database->insert('user', $content)) {
            return false;
        }

        return true;
    }

    /**
     * Update a user in the database.
     *
     * @return True on success, false on failure.
     */
    public function update(): bool
    {
        if ($this->duplicateYubikeysSubmitted()) {
            \trigger_error(TFISH_ERROR_YUBIKEY_NOT_UNIQUE, E_USER_NOTICE);
            return false;
        }

        $content = $this->validateForm($_POST['content'], false);

        // If a submitted yubikey ID is not present in $content this indicates it was not unique.
        if (!empty($_POST['content']['yubikeyId']) && empty($content['yubikeyId'])) return false;
        if (!empty($_POST['content']['yubikeyId2']) && empty($content['yubikeyId2'])) return false;
        if (!empty($_POST['content']['yubikeyId3']) && empty($content['yubikeyId3'])) return false;

        $id = (int) $content['id'];

        // As this is being sent to storage, decode some entities that were encoded for display.
        $fieldsToDecode = [
            'adminEmail',
            'yubikeyId',
            'yubikeyId2',
            'yubikeyId3',
        ];

        foreach ($fieldsToDecode as $field) {
            if (isset($content[$field])) {
                $content[$field] = htmlspecialchars_decode($content[$field], ENT_NOQUOTES);
            }
        }

        return $this->database->update('user', $id, $content);
    }

    /** Utilities. */

    /**
     * Get a single content object as an associative array.
     *
     * @param   int $id ID of user.
     * @return  array
     */
    private function getRow(int $id): array
    {
        $criteria = $this->criteriaFactory->criteria();
        $criteria->add($this->criteriaFactory->item('id', $id));

        $row = $this->database->select('user', $criteria)
            ->fetch(\PDO::FETCH_ASSOC);

        return !empty($row) ? $row : [];
    }

    /**
     * Validate submitted form data for user.
     *
     * Password is only mandatory when submitting a new record. If an existing record is being
     * edited, then password is optional (and providing one will reset it).
     *
     * @param   array $form Submitted form data.
     * @param   bool $passwordRequired True if inserting new record, false if editing existing record.
     * @return  array Validated form data.
     */
    public function validateForm(array $form, bool $passwordRequired): array
    {
        $clean = [];

        // ID.
        $id = ((int) ($form['id'] ?? 0));
        if ($id > 0) $clean['id'] = $id;

        $email = !empty($form['adminEmail']) ? $this->trimString($form['adminEmail']) : '';

        if (empty($email) || !$this->isEmail($email)) {
            \trigger_error(TFISH_ERROR_NOT_EMAIL, E_USER_ERROR);
        }

        // adminEmail
        $clean['adminEmail'] = $email;

        // On add (insert) password is mandatory.
        if ($passwordRequired === true) {
            if (empty($form['password']) || \mb_strlen($form['password'], "UTF-8") < 15) {
                \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            }
        }

        // On edit (update) password is optional and represents a reset.
        if ($passwordRequired === false) {
            if (!empty($form['password']) && \mb_strlen($form['password'], "UTF-8") < 15) {
                \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            }
        }

        if (!empty($form['password'])) $clean['passwordHash'] = $this->session->hashPassword($form['password']);

        // Yubikey IDs
        $yubikeyId = !empty($form['yubikeyId']) ? $this->trimString($form['yubikeyId']) : '';
        $yubikeyId2 = !empty($form['yubikeyId2']) ? $this->trimString($form['yubikeyId2']) : '';
        $yubikeyId3 = !empty($form['yubikeyId3']) ? $this->trimString($form['yubikeyId3']) : '';

        if (!empty($yubikeyId)) {

            if (\mb_strlen($yubikeyId) !== 12) {
                \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            }

            if (!$this->isValidYubikeyId($id, $yubikeyId)) {
                $yubikeyId = '';
                \trigger_error(TFISH_ERROR_YUBIKEY_NOT_UNIQUE, E_USER_NOTICE);
            }
        }

        $clean['yubikeyId'] = $yubikeyId;

        if (!empty($yubikeyId2)) {

            if (\mb_strlen($yubikeyId2) !== 12) {
                \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            }

            if (!$this->isValidYubikeyId($id, $yubikeyId2)) {
                $yubikeyId2 = '';
                \trigger_error(TFISH_ERROR_YUBIKEY_NOT_UNIQUE, E_USER_NOTICE);
            }
        }

        $clean['yubikeyId2'] = $yubikeyId2;

        if (!empty($yubikeyId3)) {

            if (\mb_strlen($yubikeyId3) !== 12) {
                \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            }

            if (!$this->isValidYubikeyId($id, $yubikeyId3)) {
                $yubikeyId3 = '';
                \trigger_error(TFISH_ERROR_YUBIKEY_NOT_UNIQUE, E_USER_NOTICE);
            }
        }

        $clean['yubikeyId3'] = $yubikeyId3;

        // User group.
        if (empty($form['userGroup'])) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $groupOptions = $this->listUserGroups();

        if (!\array_key_exists((int) $form['userGroup'], $groupOptions)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
            exit;
        }

        // Do not allow assignment to Admin group. Admin's user group will not be overwritten).
        if ((int) $form['userGroup'] !== self::G_SUPER ) {
            $clean['userGroup'] = (int) $form['userGroup'];
        }

        // loginErrors.
        $clean['loginErrors'] = !empty($form['loginErrors']) ? (int) $form['loginErrors'] : 0;

        $onlineStatus = !empty($form['onlineStatus']) ? (int) $form['onlineStatus'] : 0;

        if ($onlineStatus < 0 || $onlineStatus > 1) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['onlineStatus'] = $onlineStatus;

        if ($id > 0) $clean = $this->lockAdminFields($clean);

        return $clean;
    }

    /**
     * Admin account may not have user group changed or be set offline.
     *
     * Tests if the user is the admin, and if so locks their user group and online status.
     *
     * @param array $clean
     * @return array
     */
    private function lockAdminFields(array $clean): array
    {
        $row = $this->getRow($clean['id']);

        if ((int) $row['userGroup'] === 1) {
            $clean['userGroup'] = 1;
            $clean['onlineStatus'] = 1;
        }

        return $clean;
    }

    /**
     * Check if the same yubikey ID has been submitted in primary and secondary fields.
     *
     * @return boolean True if duplicated, false if not.
     */
    private function duplicateYubikeysSubmitted(): bool
    {
        if (!empty($_POST['content']['yubikeyId']) && !empty($_POST['content']['yubikeyId'])) {
            $yubikeyId = $this->trimString($_POST['content']['yubikeyId']);
            $yubikeyId2 = $this->trimString($_POST['content']['yubikeyId2']);
            $yubikeyId3 = $this->trimString($_POST['content']['yubikeyId3']);

            if ($yubikeyId === $yubikeyId2 || $yubikeyId === $yubikeyId3 || $yubikeyId2 === $yubikeyId3) return true;
        }

        return false;
    }

    /**
     * Check if a submitted yubikey ID is unique.
     *
     * The yubikey Id is used to identify accounts when using two-factor authentication, so they
     * must be unique, you cannot share them!
     *
     * @param int $id ID of user (0) if new user.
     * @param string $yubikeyId First 12 characters of yubikey output is its public ID.
     * @return boolean true if valid and unique, false if ID is invalid or already in use.
     */
    private function isValidYubikeyId(int $id, string $yubikeyId): bool
    {
        $count = 0;

        $sql = "SELECT COUNT(*) FROM `user` WHERE " .
                "(`yubikeyId` = :yubikeyId OR " .
                "`yubikeyId2` = :yubikeyId OR " .
                "`yubikeyId3` = :yubikeyId) ";

        if ($id > 0) $sql .= " AND `id` != :id";

        $statement = $this->database->preparedStatement($sql);
        $statement->bindParam(':yubikeyId', $yubikeyId, \PDO::PARAM_STR);

        if ($id > 0) $statement->bindParam(':id', $id, \PDO::PARAM_INT);

        $statement->execute();
        $count = $statement->fetch(\PDO::FETCH_NUM);
        $count = (int) \reset($count);

        return $count === 0 ? true : false;
    }
}
