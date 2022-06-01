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
 * @since       1.1
 * @package     user
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 */
class UserEdit
{
    use \Tfish\Traits\EmailCheck;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $session;
    private $criteriaFactory;
    private $preference;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
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
     * Edit content object.
     *
     * @param   int $id ID of content object.
     * @return  array Content object data as associative array.
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
     * Insert a content object into the database.
     *
     * @return  bool True on success, false on failure.
     */
    public function insert(): bool
    {
        $content = $this->validateForm($_POST['content'], true);

        // Insert new content.
        if (!$this->database->insert('user', $content)) {
            return false;
        }

        return true;
    }

    /**
     * Update a content object in the database.
     *
     * @return True on success, false on failure.
     */
    public function update(): bool
    {
        $content = $this->validateForm($_POST['content'], false);

        $id = (int) $content['id'];

        // As this is being sent to storage, decode some entities that were encoded for display.
        $fieldsToDecode = [
            'adminEmail',
            'yubikeyId',
            'yubikeyId2',
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
     * @param   int $id ID of content object.
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
     * Validate submitted form data for content object.
     *
     * @param   array $form Submitted form data.
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

        // YubikeyId (primary).
        $yubikeyId = !empty($form['yubikeyId']) ? $this->trimString($form['yubikeyId']) : '';

        if (!empty($yubikeyId) && \mb_strlen($yubikeyId) !== 12) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['yubikeyId'] = $yubikeyId;

        // YubikeyId2 (secondary).
        $yubikeyId2= !empty($form['yubikeyId2']) ? $this->trimString($form['yubikeyId2']) : '';

        if (!empty($yubikeyId2) && \mb_strlen($yubikeyId2) !== 12)  {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['yubikeyId2'] = $yubikeyId2;

        // userGroup, locked to Editor (2) on insert, but unset (unchanged) on update.
        if ($passwordRequired === true) { //
            $clean['userGroup'] = 2;
        }

        // loginErrors.
        $clean['loginErrors'] = !empty($form['loginErrors']) ? (int) $form['loginErrors'] : 0;

        $onlineStatus = !empty($form['onlineStatus']) ? (int) $form['onlineStatus'] : 0;

        if ($onlineStatus < 0 || $onlineStatus > 1) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $clean['onlineStatus'] = $onlineStatus;

        $clean = $this->lockAdminFields($clean);

        return $clean;
    }

    /**
     * Admin account may not have user group changed or be set offline.
     *
     * @param array $clean
     * @return array
     */
    private function lockAdminFields(array $clean): array
    {
        $row = $this->getRow($clean['id']);

        if ($row['userGroup'] == '1') {
            $clean['userGroup'] = '1';
            $clean['onlineStatus'] = '1';
        }

        return $clean;
    }
}
