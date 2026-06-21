<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\BlockTemplate trait file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Resolves a block template path, checking the active theme before the module's bundled default.
 *
 * Mirrors the page-template resolution in \Tfish\Entity\Template::validPath(): a theme may override a
 * module's bundled block template by placing a file with the same name in its own blocks/ directory
 * (themes/{theme}/blocks/{template}.html). When the theme does not provide the template, the module's
 * bundled copy is used, so a block always renders even with no theme-side customisation.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\TraversalCheck Validates that a path does NOT contain directory traversals or null bytes.
 */
trait BlockTemplate
{
    use \Tfish\Traits\TraversalCheck;

    /**
     * Resolve the path to a block template, theme override first, module default as fallback.
     *
     * Resolution order:
     * 1. Theme path (themes/{theme}/blocks/{template}.html) -- wins if present, letting theme authors
     *    override a module's bundled block template.
     * 2. Module path ({moduleBlockPath}{template}.html) -- the module's default, used when no theme is
     *    supplied or the theme does not provide the template.
     *
     * @param   string $theme Active theme name. Empty resolves directly against the module default.
     * @param   string $template Template file name, without extension (drawn from listTemplates()).
     * @param   string $moduleBlockPath Absolute path to the module's Block directory (trailing slash).
     * @return  string Absolute path to the resolved template file.
     */
    private function blockTemplatePath(string $theme, string $template, string $moduleBlockPath): string
    {
        $themePath = TFISH_THEMES_PATH . $theme . '/blocks/' . $template . '.html';

        if ($theme !== '' && \is_file($themePath)) {
            $path = $themePath;
        } else {
            $path = $moduleBlockPath . $template . '.html';
        }

        if ($this->hasTraversalorNullByte($path)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        if (!\is_file($path)) {
            throw new \RuntimeException(TFISH_ERROR_TEMPLATE_NOT_FOUND);
        }

        return $path;
    }
}
