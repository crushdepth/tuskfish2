<?php

declare(strict_types=1);

namespace Tfish\Entity;

/**
 * \Tfish\Entity\Template class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

/**
 * Used to hold variables that will be inserted into templates, and to render templates for display.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 * @var         string $theme The theme (template set) in use on this page.
 * @uses        trait \Tfish\Traits\TraversalCheck	Validates that a filename or path does NOT contain directory traversals in any form.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         string $template The template to use for this route.
 * @var         string $theme The theme (template set) in use on this page.
 * @var         array $variables Contains variables assigned to this page; they are extracted during render for insertion to the template.
 */

class Template
{
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\ValidateString;

    private string $template = '';
    private string $theme = '';
    public array $variables = [];

    /**
     * Constructor
     *
     * @param   string $template Name of the template to render for this page.
     * @param   string $theme Name of the theme to use for this page.
     */
    public function __construct(string $template, string $theme)
    {
        $this->template = $this->trimString($template);
        $this->theme = $this->trimString($theme);
        $this->variables = [];
    }

    /**
     * Assign a variable (value) to the template.
     *
     * Assigned variabless are extracted for insertion in the template on render.
     *
     * @param   string $key Variable name.
     * @param   mixed $value Variable value.
     */
    public function assign(string $key, $value)
    {
        if ($key != 'template' && $key !== 'theme') {
            $this->variables[$key] = $value;
        }
    }

    /**
     * Render the template.
     *
     * Variables assigned to the template are extracted to make them available for use in the
     * template.
     */
    public function render(): string
    {
        \extract($this->variables);
        \ob_start();
        include $this->validPath();
        return \ob_get_clean();
    }

    /**
     * Check the theme and template for director traversals.
     */
    public function validPath()
    {
        $path = TFISH_THEMES_PATH . $this->theme . '/' . $this->template . '.html';

        if ($this->hasTraversalorNullByte($path)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit;
        }

        return $path;
    }
}
