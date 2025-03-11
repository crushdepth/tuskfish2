<?php

declare(strict_types=1);

namespace Tfish\Expert\ViewModel;

/**
 * \Tfish\Expert\ViewModel\ExperttEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 */

/**
 * ViewModel for editing expert objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     expert
 * @uses        trait \Tfish\Expert\Traits\Options Common traits of expert objects and form controls.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\View\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         int $id ID of a single expert object to be displayed.
 * @var         \Tfish\Expert\Entity\Expert $content Expert object to be edited.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         string $response Message to display to the user after processing action (success/failure).
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 */
class ExpertEdit implements \Tfish\Interface\Viewable
{
    use \Tfish\Expert\Traits\Options;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private $model;
    private $id = 0;
    private $content = '';
    private $parentOptions = [];
    private $action = '';
    private $response = '';
    private $backUrl = '';
    private $preference;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct($model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = 'admin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display Add expert form.
     */
    public function displayAdd()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $this->pageTitle = TFISH_ADD;
        $this->content = new \Tfish\Expert\Entity\Expert();
        $this->template = 'expertEntry';
    }

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel()
    {
        \header('Location: ' . TFISH_EXPERTS_ADMIN_URL);
        exit;
    }

    /**
     * Display edit expert form.
     */
    public function displayEdit()
    {
        $id = (int) ($_GET['id'] ?? 0);

        $this->pageTitle = TFISH_EDIT;
        $content = new \Tfish\Expert\Entity\Expert();

        if ($data = $this->model->edit($id)) {
            $content->load($data, false);
            $this->setContent($content);
            $this->action = 'update';
            $this->parentOptions = [];
            $this->template = 'expertEdit';
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_ERROR_NO_SUCH_OBJECT;
            $this->backUrl = TFISH_ADMIN_URL;
            $this->template = 'response';
        }
    }

    /**
     * Save expert object (new or updated).
     */
    public function displaySave()
    {
        $token = isset($_POST['token']) ? $this->trimString($_POST['token']) : '';
        $this->validateToken($token);

        $id = (int) ($_POST['content']['id'] ?? 0);

        if (empty($id)) {

            if ($this->model->insert()) {
                $this->pageTitle = TFISH_SUCCESS;
                $this->response = TFISH_OBJECT_WAS_INSERTED;
            } else {
                $this->pageTitle = TFISH_FAILED;
                $this->response = TFISH_OBJECT_INSERTION_FAILED;
            }
        }

        if (!empty($id)) {

            if ($this->model->update()) {
                $this->pageTitle = TFISH_SUCCESS;
                $this->response = TFISH_OBJECT_WAS_UPDATED;
            } else {
                $this->pageTitle = TFISH_FAILED;
                $this->response = TFISH_OBJECT_UPDATE_FAILED;
            }
        }

        $this->template = 'response';
        $this->backUrl = TFISH_EXPERTS_ADMIN_URL;
    }

    /** Utilities */

    /**
     * Returns the date template as per the date() function of PHP.
     *
     * @return  string
     */
    public function dateFormat(): string
    {
        return $this->preference->dateFormat();
    }

    /**
     * Returns the default language preference.
     *
     * @return  string Default language as two-letter ISO code.
     */
    public function defaultLanguage(): string
    {
        return $this->preference->defaultLanguage();
    }

    /**
     * Returns a list of options for the tag select box.
     *
     * @return  array Array of tag IDs and titles as key-value pairs.
     */
    public function listTags(): array
    {
        return [0 => TFISH_ZERO_OPTION] + $this->model->onlineTagSelectOptions();
    }

    /**
     * Return the site author preference.
     *
     * @return  string
     */
    public function siteAuthor(): string
    {
        return $this->preference->siteAuthor();
    }

    /** Getters and setters */

    /**
     * Return the action for this page.
     *
     * The action is usually embedded in the form, to control handling on submission (next page load).
     *
     * @return string
     */
    public function action(): string
    {
        return $this->action;
    }

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
     * Return a expert object.
     *
     * @return \Tfish\Expert\Entity\Expert
     */
    public function expert(): \Tfish\Expert\Entity\Expert
    {
        return $this->content;
    }

    /**
     * Set expert.
     *
     * @param   \Tfish\Expert\Entity\Expert $content Expert object to be edited.
     */
    public function setContent(\Tfish\Expert\Entity\Expert $content)
    {
        $this->content = $content;
    }

    /**
     * Return ID.
     *
     * @return  int ID of expert object.
     */
    public function id(): int
    {
        return $this->id;
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
