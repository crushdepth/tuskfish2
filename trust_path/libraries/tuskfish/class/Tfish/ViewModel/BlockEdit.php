<?php

declare(strict_types=1);

namespace Tfish\ViewModel;

/**
 * \Tfish\ViewModel\BlockEdit class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * ViewModel for editing block objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\ValidateToken Provides CSRF check functionality.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Classname of the model used to display this page.
 * @var         \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
 * @var         int $id ID of a single block object to be displayed.
 * @var         mixed $content Block object to be edited.
 * @var         string $action Action to be embedded in the form and executed after next submission.
 * @var         string $response Message to display to the user after processing action (success/failure).
 * @var         string $backUrl $backUrl URL to return to if the user cancels the action.
 */
class BlockEdit implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\BlockOption;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\ValidateToken;
    use \Tfish\Traits\Viewable;

    private object $model;
    private int $id = 0;
    private object $content;
    private array $route = [];
    private string $action = '';
    private string $response = '';
    private string $backUrl = '';
    private \Tfish\Entity\Preference $preference;

    /**
     * Constructor.
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->preference = $preference;
        $this->theme = 'admin';
        $this->setMetadata(['robots' => 'noindex,nofollow']);
    }

    /** Actions */

    /**
     * Display Add block form.
     */
    public function displayAdd(): void
    {
        if ($_POST['isReload'] ?? '') {
            $content = $_POST['content'] ?? [];
            $this->setContent($content);
            $route = $_POST['route'] ?? [];
            $this->setRoute($route);
        }

        $this->pageTitle = TFISH_BLOCK_ADD;
        $this->template = 'blockEntry';
    }

    /**
     * Cancel action and redirect to admin page.
     */
    public function displayCancel(): void
    {
        \header('Location: ' . TFISH_ADMIN_BLOCK_URL);
        exit;
    }

    /**
     * Display edit block form.
     */
    public function displayEdit(): void
    {
        $id = (int) ($_GET['id'] ?? 0);

        if ($block = $this->model->edit($id)) {
            $this->pageTitle = TFISH_EDIT_BLOCK;
            $this->setContent($block);
            $this->action = 'update';
            $this->template = 'blockEdit';
        } else {
            $this->pageTitle = TFISH_FAILED;
            $this->response = TFISH_ERROR_NO_SUCH_OBJECT;
            $this->backUrl = TFISH_ADMIN_BLOCK_URL;
            $this->template = 'response';
        }
    }

    /**
     * Save block object (new or updated).
     */
    public function displaySave(): void
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
        $this->backUrl = TFISH_ADMIN_BLOCK_URL;
    }

    /** Utilities */

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
     * Return a block object.
     *
     * @return mixed
     */
    public function content(): mixed
    {
        return $this->content;
    }

    /**
     * Set block object.
     *
     * @param   mixed $content block object to be edited.
     */
    public function setContent(mixed $content): void
    {
        $this->content = $content;
    }

    /**
     * Return route(s).
     *
     * @return array
     */
    public function route(): array
    {
        return $this->route;
    }

    /**
     * Set route(s).
     *
     * @param array $route
     * @return void
     */
    public function setRoute(array $route)
    {
        $this->route = $route;
    }

    /**
     * Return ID of block object.
     *
     * @return  int ID of block object.
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

    /** Utilities */

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
     * Returns a list of options for the content type select box.
     *
     * @return  array Array of content types and titles as key-value pairs.
     */
    public function listTypes(): array
    {
        return [0 => TFISH_ZERO_OPTION] + $this->model->listTypes();
    }

    /** Unused but required for compliance with interface. */
    public function fetchBlocks(string $path): array { return []; }
}
