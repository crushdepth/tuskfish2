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
    private string $modulePath = '';
    public array $variables = [];

    /**
     * Constructor
     *
     * @param   string $template Name of the template to render for this page.
     * @param   string $theme Name of the theme to use for this page.
     * @param   string $modulePath Absolute path to a module's templates directory, used as a
     *          fallback default when the active theme does not provide the template. Empty for
     *          core/theme-only resolution (preserves legacy behaviour).
     */
    public function __construct(string $template, string $theme, string $modulePath = '')
    {
        $this->template = $this->trimString($template);
        $this->theme = $this->trimString($theme);
        $this->modulePath = $this->trimString($modulePath);
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
     * Resolve the template file path, checking the active theme first and falling back to the
     * module's bundled default.
     *
     * Resolution order:
     * 1. Theme path (themes/{theme}/{template}.html) -- wins if present, letting theme authors
     *    override a module's bundled template.
     * 2. Module path ({modulePath}/{template}.html) -- the module's default, used only when a
     *    module path was supplied and the theme does not provide the template.
     *
     * When no module path is set (core/Content/theme-only routes), behaviour is identical to
     * resolving directly against the theme.
     */
    public function validPath()
    {
        $themePath = TFISH_THEMES_PATH . $this->theme . '/' . $this->template . '.html';

        if ($this->modulePath === '' || \is_file($themePath)) {
            $path = $themePath;
        } else {
            $path = $this->modulePath . $this->template . '.html';
        }

        if ($this->hasTraversalorNullByte($path)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        if (!\is_file($path)) {
            throw new \RuntimeException(TFISH_ERROR_TEMPLATE_NOT_FOUND . ': ' . $this->template);
        }

        return $path;
    }
}
