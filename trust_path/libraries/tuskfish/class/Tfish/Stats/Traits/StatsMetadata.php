<?php

declare(strict_types=1);

namespace Tfish\Stats\Traits;

/**
 * \Tfish\Stats\Traits\StatsMetadata trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Builds page title and description metadata for Stats module pages.
 *
 * Each Stats ViewModel sets a base $pageTitle (from the Viewable trait) and a $description in its
 * constructor, then calls buildMetadata() from its display action, optionally passing the active
 * filter values (country, species, year). Present filters are appended to the title so that a
 * parameterised view such as /species/?country=Norway&year=2020 renders a specific title. The
 * resulting array is read by the FrontController, which merges it into the site \Tfish\Entity\Metadata.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @var         string $description Meta description for this page.
 */
trait StatsMetadata
{
    private string $description = '';

    /**
     * Assemble page title, description and canonical URL metadata.
     *
     * Active filters are appended to the title (in the order given) so a parameterised view such as
     * /species/?country=Norway&year=2020 renders a specific, human-readable title. The canonical URL
     * always points at the bare section page: the per-country/species/year states are JS-rendered
     * dashboard views over the same server-rendered HTML, so they are consolidated to one indexable
     * URL rather than treated as distinct pages. The host ViewModel must implement pageKey().
     *
     * @param   array $filters Active filter values (e.g. country, species name, year) in display
     *                         order. Empty values (''/0) are skipped.
     * @return  void
     */
    private function buildMetadata(array $filters = []): void
    {
        $title = $this->pageTitle;

        $parts = [];

        foreach ($filters as $value) {
            $value = \is_string($value) ? \trim($value) : $value;

            if ($value !== '' && $value !== 0) {
                $parts[] = (string) $value;
            }
        }

        if (!empty($parts)) {
            $title .= ' ' . TFISH_STATS_TITLE_SEPARATOR . ' ' . \implode(', ', $parts);
        }

        $metadata = [];

        if ($title !== '') $metadata['title'] = $title;
        if ($this->description !== '') $metadata['description'] = $this->description;
        $metadata['canonicalUrl'] = TFISH_URL . $this->pageKey() . '/';

        $this->metadata = $metadata;
    }
}
