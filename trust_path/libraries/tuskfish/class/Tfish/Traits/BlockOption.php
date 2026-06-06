<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\BlockOption trait file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

/**
 * Exposes the block whitelists to the block admin Models.
 *
 * The whitelists themselves are no longer hard-coded here: they are aggregated from module headers
 * and injected as a \Tfish\BlockRegistry. This trait is a thin façade preserving the long-standing
 * blockX() method names that the block admin Models call. A using class MUST implement the abstract
 * registry() accessor below (typically returning an injected \Tfish\BlockRegistry property).
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait BlockOption
{
    /**
     * Provide the block registry the using class holds.
     *
     * Declared abstract so a using class MUST supply it: the whitelist methods below are thin
     * facades over the registry, and this enforces the dependency at class-definition time (a fatal
     * error if unimplemented) rather than failing at call time on a missing property.
     *
     * @return \Tfish\BlockRegistry
     */
    abstract protected function registry(): \Tfish\BlockRegistry;

    /**
     * Whitelist of permitted block positions (layout slots).
     *
     * @return array Position key => human-readable label.
     */
    public function blockPositions(): array
    {
        return $this->registry()->positions();
    }

    /**
     * Whitelist of routes that blocks are permitted to be displayed on.
     *
     * @return array Flat list of route strings.
     */
    public function blockRoutes(): array
    {
        return $this->registry()->routes();
    }

    /**
     * Whitelist of templates available to each block type.
     *
     * @return array Fully qualified class name => [templateName => label].
     */
    public function blockTemplates(): array
    {
        return $this->registry()->templates();
    }

    /**
     * Return the path to the configuration sub-template for a given block type.
     *
     * @param   string $class Fully qualified block class name.
     * @return  string Absolute path to the block's config template.
     */
    public function blockConfigTemplate(string $class): string
    {
        return $this->registry()->configTemplate($class);
    }

    /**
     * Whitelist of block types available on the system.
     *
     * @return array Fully qualified class name => human-readable label.
     */
    public function blockTypes(): array
    {
        return $this->registry()->types();
    }
}
