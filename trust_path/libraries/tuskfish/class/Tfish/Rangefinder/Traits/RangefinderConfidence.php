<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Traits;

/**
 * \Tfish\Rangefinder\Traits\RangefinderConfidence trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * The confidence bucket (D-18): one rule, expressed for both a loaded row and a WHERE clause.
 *
 * A record falls in exactly one of three buckets, and which one it is decides how it may be
 * presented — this is the load-bearing rule of the whole interface, the thing that keeps an
 * unverified lead from being read as an expert determination:
 *
 *   verified      an expert determination from the curated species layer;
 *   reported      a species-level name, but from a non-authoritative source;
 *   unidentified  a lead reaching only the genus — including one whose species name is not a
 *                 recognised taxon, which is demoted here and has its name suppressed.
 *
 * It exists in two forms because it has two consumers with incompatible needs, and the two forms
 * MUST agree:
 *
 *   - categoryOf() decides it for a row already in memory. The map payload uses this: it holds the
 *     whole marker set anyway, and shipping the bucket as a value means the filter, the popups and
 *     the headline counts all read one field instead of each re-deriving the rule.
 *   - bucketSql() decides it inside the query. /explore paginates, and a LIMIT slice is taken by
 *     the database — so filtering in PHP after the slice would filter a page of the wrong
 *     population and report a wrong total. The bucket has to be a WHERE clause there, not a
 *     post-pass.
 *
 * That duplication is a real drift risk and is deliberately bounded rather than wished away: the
 * two forms are written side by side here, in one file, and the acceptance check is arithmetic —
 * /explore's per-bucket totals over the mapped set must equal the map's headline counts. If either
 * form is edited alone, that check fails.
 *
 * Neither form is a taxonomic authority. Both defer to the accepted-taxa whitelist in
 * RangefinderTaxonomy, which is a presentation decision about how a *reported* name is shown; the
 * database remains the sole authority on what a record is.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Rangefinder\Traits\RangefinderTaxonomy  Host class MUST also use this:
 *              the accepted-taxa whitelist both forms of the rule are decided against.
 */
trait RangefinderConfidence
{
    /**
     * The three buckets, in presentation order (most confident first).
     *
     * The order is the order they are offered and counted in, so it is fixed here rather than in
     * each template.
     *
     * @return  array<string>
     */
    public function confidenceBuckets(): array
    {
        return ['verified', 'reported', 'unidentified'];
    }

    /**
     * Narrow a record's taxon claim to what may actually be shown.
     *
     * Applies the demotion: a presence-layer name that is not a currently-recognised taxon loses
     * its name and drops to genus rank, so an unrecognised, dubious or erroneous name can never be
     * read off the page as a species claim. Only ever narrows, and only ever a presence-layer
     * claim — a verified determination is returned exactly as the database has it.
     *
     * The whitelist is checked for EVERY presence-layer record whatever rank it declares, because
     * rank is the publisher's claim about their own name and the broad-GBIF layer files plenty of
     * non-names at genus: BOLD BIN codes ('BOLD:AAD2313'), pipeline non-assignments
     * ('unclassified.Artemia urmiana'). The test has to run on the name itself.
     *
     * @param   string|null $layer 'species' or 'presence'.
     * @param   string|null $canonicalTaxon Derived canonical name as stored.
     * @param   string|null $taxonRank Normalised rank as stored ('genus'|'species'|'infraspecific').
     * @return  array ['canonical_taxon' => ?string, 'taxon_rank' => ?string] after demotion.
     */
    public function effectiveTaxon(?string $layer, ?string $canonicalTaxon, ?string $taxonRank): array
    {
        if ($layer === 'presence' && !$this->isAcceptedTaxon($canonicalTaxon)) {
            return ['canonical_taxon' => null, 'taxon_rank' => 'genus'];
        }

        return ['canonical_taxon' => $canonicalTaxon, 'taxon_rank' => $taxonRank];
    }

    /**
     * The confidence bucket of one row, decided from its already-demoted rank.
     *
     * Callers must pass the rank as effectiveTaxon() returned it, not the raw stored rank — that
     * ordering is what makes a lead whose species name was not recognised fall to 'unidentified'
     * rather than 'reported'.
     *
     * @param   string|null $layer 'species' or 'presence'.
     * @param   string|null $effectiveRank Rank AFTER demotion (see effectiveTaxon()).
     * @return  string One of 'verified', 'reported', 'unidentified'.
     */
    public function categoryOf(?string $layer, ?string $effectiveRank): string
    {
        if ($layer === 'species') return 'verified';

        // A rank the source never recorded is not evidence of a species: absent reads as genus.
        return ($effectiveRank ?? 'genus') === 'genus' ? 'unidentified' : 'reported';
    }

    /**
     * The same rule as a bound WHERE fragment, for queries that paginate.
     *
     * Mirrors categoryOf() composed with effectiveTaxon(), term for term:
     *
     *   verified      layer = 'species'
     *   reported      layer <> 'species' AND rank <> 'genus' AND canonical IN (whitelist)
     *   unidentified  layer <> 'species' AND NOT (rank <> 'genus' AND canonical IN (whitelist))
     *
     * Two details are load-bearing and easy to get subtly wrong:
     *
     *   - the rank test is "not genus", not "= species". An accepted infraspecific name
     *     ('artemia franciscana monica') is a species-level claim and PHP's `$rank === 'genus'`
     *     test treats it as one; writing `= 'species'` here would file it as unidentified in the
     *     query while the map called it reported.
     *   - a NULL rank counts as genus, hence COALESCE, matching `$effectiveRank ?? 'genus'`.
     *
     * The whitelist arrives as bound placeholders, never interpolated, even though it is
     * hard-coded PHP: one path for values into SQL, no exceptions.
     *
     * @param   array<string> $buckets Selected bucket names; empty or all three means no restriction.
     * @param   string $alias Table/view alias to qualify columns with ('' for none).
     * @param   string $prefix Placeholder prefix, so several fragments can coexist in one statement.
     * @return  array ['sql' => string, 'params' => array] — sql is '' when nothing is restricted.
     */
    public function bucketSql(array $buckets, string $alias = '', string $prefix = 'cb'): array
    {
        $selected = \array_values(\array_intersect($this->confidenceBuckets(), $buckets));

        // No selection and a full selection are the same query: everything. Say so with no clause
        // rather than a three-way OR the planner has to see through.
        if (empty($selected) || \count($selected) === \count($this->confidenceBuckets())) {
            return ['sql' => '', 'params' => []];
        }

        $q = $alias === '' ? '' : $alias . '.';
        $params = [];
        $placeholders = [];

        foreach ($this->acceptedTaxa() as $i => $taxon) {
            $name = ':' . $prefix . 't' . $i;
            $placeholders[] = $name;
            $params[$name] = $taxon;
        }

        // "Names a species we recognise": the demotion test, inverted into positive form.
        $named = '(COALESCE(' . $q . "taxon_rank, 'genus') <> 'genus' AND " . $q
            . 'canonical_taxon IN (' . \implode(', ', $placeholders) . '))';

        $terms = [];

        foreach ($selected as $bucket) {
            if ($bucket === 'verified') {
                $terms[] = '(' . $q . "layer = 'species')";
            } elseif ($bucket === 'reported') {
                $terms[] = '(' . $q . "layer <> 'species' AND " . $named . ')';
            } else {
                $terms[] = '(' . $q . "layer <> 'species' AND NOT " . $named . ')';
            }
        }

        // Whitelist params are only needed if a bucket that tests names was selected.
        if (\count($selected) === 1 && $selected[0] === 'verified') {
            $params = [];
        }

        return ['sql' => '(' . \implode(' OR ', $terms) . ')', 'params' => $params];
    }

    /**
     * The bucket as a bound SELECT expression, for listing a row's confidence in a result set.
     *
     * Same three terms as bucketSql(), evaluated per row instead of filtered on, so a table or a
     * CSV export can print the bucket without the reader re-deriving it. Kept here beside the two
     * other forms for the same reason they are together: if the rule changes it changes once.
     *
     * @param   string $alias Table/view alias to qualify columns with ('' for none).
     * @param   string $prefix Placeholder prefix.
     * @return  array ['sql' => string, 'params' => array] — sql is a CASE expression, unaliased.
     */
    public function bucketExpressionSql(string $alias = '', string $prefix = 'ce'): array
    {
        $q = $alias === '' ? '' : $alias . '.';
        $params = [];
        $placeholders = [];

        foreach ($this->acceptedTaxa() as $i => $taxon) {
            $name = ':' . $prefix . 't' . $i;
            $placeholders[] = $name;
            $params[$name] = $taxon;
        }

        $sql = 'CASE WHEN ' . $q . "layer = 'species' THEN 'verified'"
            . ' WHEN COALESCE(' . $q . "taxon_rank, 'genus') <> 'genus'"
            . ' AND ' . $q . 'canonical_taxon IN (' . \implode(', ', $placeholders) . ')'
            . " THEN 'reported'"
            . " ELSE 'unidentified' END";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * The demoted taxon name as a bound SELECT expression.
     *
     * The query-side counterpart of effectiveTaxon(): NULLs the canonical name of any presence-layer
     * record whose name is not recognised, so a listing or an export cannot print a name the map
     * suppresses. Without this the two surfaces would disagree about what a record claims — the
     * exact failure the whitelist exists to prevent.
     *
     * @param   string $alias Table/view alias to qualify columns with ('' for none).
     * @param   string $prefix Placeholder prefix.
     * @return  array ['sql' => string, 'params' => array] — sql is a CASE expression, unaliased.
     */
    public function effectiveTaxonSql(string $alias = '', string $prefix = 'et'): array
    {
        $q = $alias === '' ? '' : $alias . '.';
        $params = [];
        $placeholders = [];

        foreach ($this->acceptedTaxa() as $i => $taxon) {
            $name = ':' . $prefix . 't' . $i;
            $placeholders[] = $name;
            $params[$name] = $taxon;
        }

        $sql = 'CASE WHEN ' . $q . "layer = 'species'"
            . ' OR ' . $q . 'canonical_taxon IN (' . \implode(', ', $placeholders) . ')'
            . ' THEN ' . $q . 'canonical_taxon ELSE NULL END';

        return ['sql' => $sql, 'params' => $params];
    }
}
