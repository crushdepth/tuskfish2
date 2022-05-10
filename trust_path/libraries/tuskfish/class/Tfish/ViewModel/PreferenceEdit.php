<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModelModel\PreferenceEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * ViewModel for editing site preferences.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Language	Returns a list of languages in use by the system.
 * @uses        trait \Tfish\Traits\Timezones	Provides an array of time zones.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\View\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         string $theme Name of the theme used to display this page.
 * @var         string $template Name of the HTML template used to display this page (without the file extension).
 * @var         string $pageTitle Title of this page.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preference class.
 * @var         string $response Message to display to the user after processing action (success/failure).
 * @var         string $backUrl URL to return to if the user cancels the action.
 * @var         array $metadata Overrides of site metadata properties, to customise it for this page.
 */

class PreferenceEdit implements Viewable
{
    Use \Tfish\Traits\Language;
    Use \Tfish\Traits\Timezones;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private $model;
    private $preference;
    private $response = '';
    private $backUrl = '';

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tfish site preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->pageTitle = TFISH_PREFERENCE_EDIT_PREFERENCES;
        $this->model = $model;
        $this->theme = 'admin';
        $this->preference = $preference;
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions. */

    /**
     * Cancel editing of preferences and redirect the user.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_PREFERENCE_URL);
        exit;
    }

    /**
     * Display the edit preferences form.
     */
    public function displayEdit()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $this->template = 'preferenceEdit';
    }

    /**
     * Update preferences and display confirmation message (success or failure).
     */
    public function displayUpdate()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        if ($this->model->update()) {
            $this->response = TFISH_PREFERENCES_WERE_UPDATED;
            $this->backUrl = TFISH_PREFERENCE_URL;
            $this->template = 'response';
        } else {
            $this->response = TFISH_PREFERENCES_UPDATE_FAILED;
            $this->backUrl = TFISH_PREFERENCE_URL;
            $this->template = 'response';
        }
    }

    /* Getters and setters. */

    /**
     * Return the backUrl.
     *
     * If the cancel button is clicked, the user will be redirected to the backUrl.
     *
     * @return  string
     */
    public function backUrl(): string
    {
        return $this->backUrl;
    }

    /**
     * Return an instance of the site preferences class.
     *
     * @return  \Tfish\Entity\Preference
     */
    public function preference(): \Tfish\Entity\Preference
    {
        return $this->preference;
    }

    /**
     * Return the response message (success or failure) for an action.
     *
     * @return  string
     */
    public function response(): string
    {
        return $this->response;
    }
}
