<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Traits;

/**
 * \Tfish\Rangefinder\Traits\RangefinderTaxonomy trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Accepted-taxa whitelist and scientific-name normalisation for the occurrence map.
 *
 * This is a UI concern, not a taxonomic authority. The database remains the sole authority on what
 * a record *is*: determinations, and any future revision (a species split between authors, a
 * re-determination) are made per record in the occurrence database, never rewritten here. This
 * trait only decides how a *reported* (unverified, GBIF-layer) name is presented on the map:
 *
 *   - a reported name that normalises to an accepted taxon stays "reported to species";
 *   - a reported name that does not (an unrecognised, dubious or erroneous name) is demoted to
 *     "not identified to species" and its species name is suppressed on the map, so an unrecognised
 *     name can never be read off the page as a species claim.
 *
 * It performs NO remapping: it never turns one name into another. A name is either recognised as-is
 * or it is not. Consolidating historical designations onto a current name (e.g. the records source-
 * labelled "Artemia monica" onto the accepted subspecies "Artemia franciscana monica") is a per-
 * record database revision, out of scope for this presentation layer; until that is done both names
 * are simply listed as accepted here so neither is wrongly demoted.
 *
 * Verified determinations (the curated species layer) are never touched by any of this: they are
 * expert-vetted by definition.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */
trait RangefinderTaxonomy
{
    /**
     * The accepted-taxa whitelist: currently recognised Artemia species and subspecies.
     *
     * Hand-edited when taxonomic advances or revisions occur — this is the one place to add or
     * remove a recognised name. Entries are stored already-normalised (lower case, no author
     * citation, no rank markers), because that is the form isAcceptedTaxon() compares against;
     * keep new entries in the same form.
     *
     * Parthenogenetic populations are grouped here under the single name "artemia parthenogenetica"
     * and are separated in the interface by ploidy, which the demo uses as a proxy for lineage. A
     * ploidy class may in time prove to contain more than one independently-arisen lineage; that is
     * a known limitation to revisit when strain-level evidence is available, not a naming decision
     * this list can make.
     *
     * Only current accepted names appear here. Historical source labels are mapped to the current
     * name upstream, in the importer's canonical-taxon step (TAXON_SYNONYMS in build_db.py), so this
     * list never needs a synonym entry — e.g. source-labelled "A. monica" records arrive already
     * carrying canonical "artemia franciscana monica" and match on that.
     *
     * @return  array<string> Normalised accepted names.
     */
    public function acceptedTaxa(): array
    {
        return [
            'artemia salina',
            'artemia urmiana',
            'artemia sinica',
            'artemia tibetiana',
            'artemia persimilis',
            'artemia franciscana',
            'artemia franciscana monica',
            'artemia sorgeloosi',
            'artemia amati',
            'artemia parthenogenetica',    // parthenogenetic populations, grouped; split by ploidy in the UI.
        ];
    }

    /**
     * Is a canonical taxon name a currently-recognised taxon?
     *
     * The comparison is against the already-normalised canonical_taxon computed at import
     * (db/build_db.py normalise_taxon), so this is a plain membership test — no normalisation
     * happens here. That normaliser and acceptedTaxa() share one form (lower case, no author
     * citation, no rank markers): keep them in step when either changes.
     *
     * @param   string|null $canonicalTaxon The derived canonical name from the database.
     * @return  bool True if it is an entry in acceptedTaxa().
     */
    public function isAcceptedTaxon(?string $canonicalTaxon): bool
    {
        if ($canonicalTaxon === null || $canonicalTaxon === '') return false;

        return \in_array($canonicalTaxon, $this->acceptedTaxa(), true);
    }
}
