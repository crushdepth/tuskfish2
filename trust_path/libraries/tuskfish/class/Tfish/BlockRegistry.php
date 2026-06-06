<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\BlockRegistry class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Registry of block type/template/position/route whitelists aggregated from module headers.
 *
 * Modules register their block types, templates, config sub-templates and routes by appending to
 * the seed arrays in their header.php (auto-discovered by index.php). The aggregated arrays are
 * injected once (DICE shared) into this immutable registry, which is the single source of truth for
 * the block admin whitelists previously hard-coded in \Tfish\Traits\BlockOption. Core no longer
 * references any specific module's blocks: a new block-providing module ships everything in its own
 * header with zero edits to core.
 *
 * Holds developer-defined data only (never user input). The block admin Models/ViewModels continue
 * to validate user-supplied type/template/position/route against the whitelists this class exposes.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString Provides methods for validating UTF-8 character encoding and string composition.
 * @var         array $types Block type whitelist (fully qualified class name => human-readable label).
 * @var         array $templates Block template whitelist (class => [templateName => label]).
 * @var         array $positions Layout-slot whitelist (positionKey => label); core/theme vocabulary.
 * @var         array $routes Flat whitelist of routes that blocks may be displayed on.
 * @var         array $config Config sub-template map (class => template name, no extension).
 */
class BlockRegistry
{
    use \Tfish\Traits\ValidateString;

    private readonly array $types;
    private readonly array $templates;
    private readonly array $positions;
    private readonly array $routes;
    private readonly array $config;

    /**
     * Constructor.
     *
     * Takes a single associative array of registrations rather than five positional array
     * parameters: the DI container resolves array arguments by type, and multiple same-typed array
     * parameters cannot be disambiguated positionally (it would greedily mis-assign them). A single
     * keyed bag sidesteps that and documents each registration by name.
     *
     * @param   array $blocks Block registrations keyed:
     *                        'types'     => class => label,
     *                        'templates' => class => [templateName => label],
     *                        'positions' => positionKey => label,
     *                        'routes'    => flat list of block-hosting routes,
     *                        'config'    => class => config template name (no extension).
     */
    public function __construct(array $blocks = [])
    {
        $this->types = $blocks['types'] ?? [];
        $this->templates = $blocks['templates'] ?? [];
        $this->positions = $blocks['positions'] ?? [];
        // Routes are merged from several modules' numeric arrays, so de-duplicate and re-index in one
        // place (the associative arrays above already de-duplicate by key).
        $this->routes = \array_values(\array_unique($blocks['routes'] ?? []));
        $this->config = $blocks['config'] ?? [];
    }

    /**
     * Whitelist of block types available on the system.
     *
     * @return array Fully qualified class name => human-readable label.
     */
    public function types(): array
    {
        return $this->types;
    }

    /**
     * Whitelist of templates available to each block type.
     *
     * @return array Fully qualified class name => [templateName => label].
     */
    public function templates(): array
    {
        return $this->templates;
    }

    /**
     * Whitelist of permitted block positions (layout slots).
     *
     * @return array Position key => human-readable label.
     */
    public function positions(): array
    {
        return $this->positions;
    }

    /**
     * Whitelist of routes that blocks are permitted to be displayed on.
     *
     * @return array Flat list of route strings.
     */
    public function routes(): array
    {
        return $this->routes;
    }

    /**
     * Return the path to the configuration sub-template for a given block type.
     *
     * @param   string $class Fully qualified block class name.
     * @return  string Absolute path to the block's config template.
     */
    public function configTemplate(string $class): string
    {
        $class = $this->trimString($class);

        if (!\array_key_exists($class, $this->config)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TEMPLATE_NOT_FOUND);
        }

        return $this->blockPath($class) . $this->config[$class] . '.html';
    }

    /**
     * Calculate the filesystem directory for a block from its fully qualified class name.
     *
     * @param   string $class Fully qualified class name for a block.
     * @return  string File path to the directory containing the block's templates.
     */
    private function blockPath(string $class): string
    {
        $class = $this->trimString($class);
        $path = \mb_substr($class, 0, \mb_strrpos($class, '\\') + 1);
        $convertedPath = \str_replace('\\', '/', $path);
        $finalPath = \ltrim($convertedPath, '/');

        return TFISH_CLASS_PATH . $finalPath;
    }
}
