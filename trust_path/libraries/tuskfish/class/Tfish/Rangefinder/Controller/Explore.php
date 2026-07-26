<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Controller;

/**
 * \Tfish\Rangefinder\Controller\Explore class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * Controller for the Rangefinder occurrence table (/explore/).
 *
 * The map's mirror image where caching is concerned. /map/ is one cacheable document whatever its
 * query string, because the parameters are read by JavaScript after load and never change the HTML.
 * Here the query string IS the page: it selects the rows, the counts and the pagination. So a
 * filtered request is not server-cached (following the precedent D-15a set for the Stats module) —
 * otherwise the cache fills with one file per filter permutation, of which there are effectively
 * unlimited many, to serve a page each visitor sees once.
 *
 * Browser caching still applies to both, through a validator rather than a max-age: an ETag of the
 * database's mtime plus a fingerprint of the normalised filter state. Revisiting the same filterset
 * revalidates to 304, and a database rebuild invalidates every copy in the world immediately — which
 * a max-age cannot do, and which matters when the payload is scientific records that may be corrected.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 */
class Explore
{
    use \Tfish\Traits\ValidateString;

    private object $model;
    private object $viewModel;
    private \Tfish\Logger $logger;

    public function __construct(object $model, object $viewModel, \Tfish\Logger $logger)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
        $this->logger = $logger;
    }

    /**
     * Render the occurrence table.
     *
     * Reads $_GET rather than $_REQUEST deliberately: this is a read-only, shareable, bookmarkable
     * view, and every parameter it honours must be visible in the URL. Honouring a POST body or a
     * cookie of the same name would give two visitors the same URL and different pages.
     *
     * The request is normalised by the filter trait on the model before anything else happens, so
     * nothing downstream — not the query, not the links, not the cache key — ever sees raw input.
     *
     * @return  array Cache parameters. Empty for a filtered view: the do-not-cache signal.
     */
    public function display(): array
    {
        $state = $this->model->parseFilters($_GET);
        $loggedIn = !empty($_SESSION['id']);

        // Before anything is loaded: on a 304 this exits without touching the database.
        $this->sendCacheValidators($state, $loggedIn);

        $this->viewModel->displayExplore($state);

        if (!$this->model->isDefaultState($state)) {
            // Two mechanisms, because they act at different points: empty $cacheParams stops
            // \Tfish\Cache::check() and ::save(), and doNotCache stops FrontController saving the
            // rendered buffer. Setting only one would leave the other free to store the page.
            $this->viewModel->setDoNotCache(true);

            return [];
        }

        $cacheParams = ['page' => 'explore'];

        if ($loggedIn) {
            $cacheParams['loggedIn'] = '1';
        }

        return $cacheParams;
    }

    /**
     * Stream the current filterset as a CSV file (D-7).
     *
     * The whole point of building /explore first: this action adds no filter vocabulary of its own. It
     * parses the same query string with the same parser, runs the same query builder, and streams what
     * the table would have shown — so "export" means exactly "the view I am looking at, as a file",
     * and there is no second definition of a filter to keep in step.
     *
     * Attribution is carried BOTH ways, deliberately:
     *
     *   - a '#'-prefixed preamble states the export date, the filters, the terms that govern the file
     *     as a whole and where they come from;
     *   - and every row carries its own licence, dataset, citation and DOI.
     *
     * Either alone fails a foreseeable use: a preamble is stripped by anything that parses the file as
     * data, and per-row columns are lost when someone pastes a subset into an email. Repeating the
     * attribution is a few bytes per row against the risk of records circulating with no source.
     *
     * Streams rather than buffers, and exits rather than returning: an action that exits bypasses
     * \Tfish\Cache::check(), which is what must happen — a CSV must never be stored as a cached page
     * and served back as one.
     *
     * @return  array Never reached; the action exits. Declared for signature consistency.
     */
    public function export(): array
    {
        $state = $this->model->parseFilters($_GET);
        $this->model->setState($state);

        // The page buffer opened by FrontController holds whatever has been emitted so far. Discard it:
        // a single stray byte before the headers would both corrupt the file and break the download.
        while (\ob_get_level() > 0) {
            \ob_end_clean();
        }

        $filename = 'artemia-occurrences-' . \date('Y-m-d') . '.csv';

        \header_remove('Pragma');
        \header_remove('Expires');
        \header('Content-Type: text/csv; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        // An export is a snapshot of a filter state at a moment. Revalidation is meaningless for a
        // downloaded file, and a stale cached copy of scientific records is exactly what must not
        // happen, so this one is not cached anywhere.
        \header('Cache-Control: no-store');
        \header('X-Content-Type-Options: nosniff');

        $output = \fopen('php://output', 'w');

        if ($output === false) exit;

        // UTF-8 BOM. The dataset carries accented locality names and recorder names from a dozen
        // sources; without it Excel on Windows reads them as mojibake, which corrupts exactly the
        // attribution this file exists to carry.
        \fwrite($output, "\xEF\xBB\xBF");

        foreach ($this->preamble($state) as $line) {
            \fwrite($output, '# ' . $line . "\r\n");
        }

        $this->writeCsvRow($output, $this->exportColumns());

        foreach ($this->model->exportRows() as $record) {
            $this->writeCsvRow($output, $this->exportRow($record));
        }

        \fflush($output);
        \fclose($output);
        exit;
    }

    /**
     * Write one CSV row with every formatting parameter stated.
     *
     * The escape character is passed explicitly as '' — no backslash escaping — for two reasons. It is
     * what RFC 4180 actually specifies (a quote inside a field is doubled, and nothing else is
     * special), so the file reads correctly in R, pandas, Excel and LibreOffice alike; and PHP's own
     * default is changing, which on PHP 8.5 emits a deprecation notice *per row* — 3,300 log lines for
     * one download, drowning the log the module's real errors go to.
     *
     * @param   resource $handle Open output stream.
     * @param   array $fields Cells, in exportColumns() order.
     */
    private function writeCsvRow($handle, array $fields): void
    {
        \fputcsv($handle, $fields, ',', '"', '');
    }

    /**
     * The '#'-prefixed preamble: what this file is, and on what terms it may be used.
     *
     * @param   array $state Normalised filter state.
     * @return  array<string> Lines, without the '#' prefix or line ending.
     */
    private function preamble(array $state): array
    {
        $lines = [
            TFISH_RANGEFINDER_EXPORT_TITLE,
            TFISH_RANGEFINDER_EXPORT_DATE . ': ' . \gmdate('Y-m-d H:i') . ' UTC',
            TFISH_RANGEFINDER_EXPORT_SOURCE . ': ' . TFISH_URL . 'explore/',
            TFISH_RANGEFINDER_EXPORT_RECORDS . ': ' . $this->model->recordCount(),
        ];

        $summary = $this->model->filterSummary($state);

        if (empty($summary)) {
            $lines[] = TFISH_RANGEFINDER_EXPORT_FILTERS . ': ' . TFISH_RANGEFINDER_EXPORT_NO_FILTERS;
        } else {
            $terms = [];

            foreach ($summary as $item) {
                $terms[] = $item['label'] . ' = ' . $item['value'];
            }

            $lines[] = TFISH_RANGEFINDER_EXPORT_FILTERS . ': ' . \implode('; ', $terms);
        }

        $licenses = $this->model->licensesInSet();

        $lines[] = TFISH_RANGEFINDER_EXPORT_TERMS . ': ' . $this->combinedTerms($licenses);
        $lines[] = TFISH_RANGEFINDER_EXPORT_LICENSES . ': '
            . (empty($licenses) ? TFISH_RANGEFINDER_NO_VALUE : \implode(', ', $licenses));
        $lines[] = TFISH_RANGEFINDER_EXPORT_ATTRIBUTION;
        $lines[] = TFISH_RANGEFINDER_EXPORT_GBIF_TERMS;
        $lines[] = TFISH_RANGEFINDER_EXPORT_CAVEAT;

        // Strip anything that could end a comment line early and make the next line read as data.
        return \array_map(static function (string $line): string {
            return \str_replace(["\r", "\n"], ' ', $line);
        }, $lines);
    }

    /**
     * The terms governing the file as a whole: the most restrictive licence present in it.
     *
     * A mixed extract cannot be offered under its most permissive licence — that would licence away
     * rights the restrictive rows never granted. So the combined figure is the most restrictive one
     * present: any NC row makes the whole file non-commercial. The per-row licence column is what a
     * user needs to separate the parts again, and it is why that column exists.
     *
     * @param   array<string> $licenses Distinct licences in the filtered set.
     * @return  string
     */
    private function combinedTerms(array $licenses): string
    {
        if (empty($licenses)) return TFISH_RANGEFINDER_EXPORT_TERMS_UNKNOWN;

        foreach ($licenses as $license) {
            if (\stripos($license, '-NC') !== false) return TFISH_RANGEFINDER_EXPORT_TERMS_NC;
        }

        if (\count($licenses) === 1) return $licenses[0];

        return TFISH_RANGEFINDER_EXPORT_TERMS_MIXED;
    }

    /**
     * CSV header row.
     *
     * Column names are the interface's own, not the schema's: a file a colleague can read without the
     * schema in front of them. 'scientific_name_as_published' and 'scientific_name_grouped' are named
     * to keep the load-bearing distinction legible in the file — the first is the authority, the
     * second our normalised grouping key, and neither is a substitute for the other.
     *
     * @return  array<string>
     */
    private function exportColumns(): array
    {
        return [
            'occurrence_id',
            'scientific_name_as_published',
            'scientific_name_grouped',
            'taxon_rank',
            'identification_qualifier',
            'confidence',
            'determination_confidence',
            'layer',
            'ploidy',
            'locality',
            'locality_as_published',
            'site_key',
            'state_province',
            'country',
            'country_code',
            'decimal_latitude',
            'decimal_longitude',
            'coordinate_accuracy_m',
            'coordinate_status',
            'event_date',
            'year',
            'basis_of_record',
            'individual_count',
            'institution_code',
            'collection_code',
            'catalog_number',
            'preparations',
            'disposition',
            'material_type',
            'holder',
            'recorded_by',
            'associated_sequences',
            'occurrence_remarks',
            'source_dataset',
            'source_dataset_key',
            'source_citation',
            'source_doi',
            'license',
            'record_url',
        ];
    }

    /**
     * One display row as CSV cells, in exportColumns() order.
     *
     * @param   array $record Display row from Model\Explore.
     * @return  array
     */
    private function exportRow(array $record): array
    {
        $cells = [
            $record['occurrence_id'],
            $record['verbatim_scientific_name'],
            // The DEMOTED name, as everywhere else: an unrecognised presence-layer name is empty here,
            // so the file cannot make a species claim the interface refuses. The published name in the
            // previous column is untouched, so nothing is lost.
            $record['display_taxon'],
            $record['taxon_rank'],
            $record['identification_qualifier'],
            $record['confidence'],
            $record['determination_confidence'],
            $record['layer'],
            $record['ploidy'],
            $record['locality_name'],
            $record['locality_text'] ?? $record['verbatim_locality'],
            $record['site_key'],
            $record['state_province'],
            $record['country'],
            $record['country_code'],
            $record['latitude'],
            $record['longitude'],
            $record['accuracy_m'],
            $record['coordinate_status'],
            $record['event_date'],
            $record['year'],
            $record['basis_of_record'],
            $record['individual_count'],
            $record['institution_code'],
            $record['collection_code'],
            $record['catalog_number'],
            $record['preparations'],
            $record['disposition'],
            $record['holding_type'],
            $record['holder'],
            $record['recorded_by'],
            $record['associated_sequences'],
            $record['occurrence_remarks'],
            $record['source_dataset'],
            $record['dataset_key'],
            $record['source_citation'],
            $record['source_doi'],
            $record['license'],
            $record['references_url'],
        ];

        return \array_map([$this, 'guardCell'], $cells);
    }

    /**
     * Neutralise a cell a spreadsheet would execute as a formula.
     *
     * A leading '=', '+', '-', '@', tab or CR makes Excel and LibreOffice treat the cell as a formula,
     * which is a code-execution path out of third-party archive text. Prefixing a tab defuses it while
     * leaving the value readable.
     *
     * Applied only where the value is not numeric, which is the load-bearing exception: a latitude of
     * -12.5 starts with '-' and must stay a number, or every southern-hemisphere coordinate in the file
     * becomes text and the file stops being usable for the thing it is for.
     *
     * @param   mixed $value Cell value.
     * @return  mixed
     */
    private function guardCell($value)
    {
        if ($value === null || $value === '') return '';
        if (\is_int($value) || \is_float($value)) return $value;

        $string = (string) $value;

        if (\is_numeric($string)) return $string;

        return \strpbrk(\substr($string, 0, 1), "=+-@\t\r") !== false ? "\t" . $string : $string;
    }

    /**
     * Send browser cache validators, and answer a conditional request with 304 if nothing changed.
     *
     * The validator has two components because two things can change the response: the occurrence
     * database (rebuilt wholesale and copied in, so its mtime changes if and only if the data does)
     * and the filter state (which selects what is rendered from it). The filter component is a hash
     * of the NORMALISED state, not of the raw query string, so two URLs describing the same set — a
     * different parameter order, a default stated explicitly, a junk parameter dropped — share one
     * validator instead of defeating it.
     *
     * @param   array $state Normalised filter state.
     * @param   bool $loggedIn Whether a session is active.
     */
    private function sendCacheValidators(array $state, bool $loggedIn): void
    {
        $timestamp = $this->model->databaseTimestamp();

        if ($timestamp < 1) return;

        $etag = '"rf-' . $timestamp . '-' . $this->model->filterStateKey($state)
            . ($loggedIn ? '-in' : '') . '"';

        // PHP's session cache limiter has already emitted 'Pragma: no-cache' and a 1981 'Expires'.
        // Cache-Control outranks both for HTTP/1.1 and Pragma means nothing in a response, but a
        // contradictory header set is exactly what an intermediary resolves however it likes. Drop
        // them and let one directive speak. (Same treatment as Controller\Map.)
        \header_remove('Pragma');
        \header_remove('Expires');

        \header('Cache-Control: ' . ($loggedIn ? 'private' : 'public') . ', must-revalidate');
        \header('ETag: ' . $etag);

        $ifNoneMatch = $this->trimString($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');

        // A client may return a list, and a cache may have weakened the tag with a W/ prefix.
        foreach (\explode(',', $ifNoneMatch) as $candidate) {
            if (\ltrim(\trim($candidate), 'W/') === $etag) {
                \http_response_code(304);
                exit;
            }
        }
    }
}
