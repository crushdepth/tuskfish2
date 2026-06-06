<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\FoodSecurity class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Model for the food security page (/insecurity/).
 *
 * Supplies the choropleth of the prevalence of moderate or severe food insecurity (SDG indicator
 * 2.1.2, percentage of the total population) keyed by M49 numeric country id. The figures are
 * presently a static snapshot drawn from the FAO Food Insecurity Experience Scale (FIES) release,
 * baked into this class so the data lives behind the Model boundary (not the template) ready for the
 * page to be made dynamic: when a food-security table is added to the Stats database, only
 * loadFoodSecurityData() changes — it reads the database instead of the baked snapshot — and the
 * ViewModel/Controller/template are untouched. The connection is opened (via the StatsDatabase
 * trait) now so the country list and the future data query are ready to wire up.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 * @uses        trait \Tfish\Traits\ValidateString  Validates UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Stats\Traits\StatsDatabase  Connection and country lookup helpers.
 */
class FoodSecurity
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    /**
     * Reference year of the baked snapshot. Single source of truth for the year shown on the page:
     * the template reads it via the ViewModel rather than hardcoding it, so the year lives in exactly
     * one place. Update this together with staticSnapshot() when regenerating from a newer FIES
     * release (see data/.../extract_fies.py in the fishstat project).
     */
    private const REFERENCE_YEAR = 2022;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $foodSecurityData = [];
    private array $countryList = [];

    public function __construct(
        \Tfish\Database $database,
        \Tfish\Entity\Preference $preference,
        \Tfish\Session $session,
        \Tfish\Logger $logger
    ) {
        $this->database = $database;
        $this->preference = $preference;
        $this->session = $session;
        $this->logger = $logger;
        $this->connect();
    }

    /**
     * Build the food security payload.
     *
     * The map is a single global choropleth, so the country argument has no effect on the data
     * returned; it is accepted (and validated) only to mirror the other Stats models and to keep the
     * persisted state flowing through the dashboard. When this page is made dynamic the static
     * snapshot below is replaced by a database read.
     *
     * @param   string $countryName English country name, or '' for the global picture (unused for now).
     */
    public function loadFoodSecurityData(string $countryName = ''): void
    {
        $this->trimString($countryName); // Validate UTF-8; the value is not used while the map is static.

        $this->foodSecurityData = $this->staticSnapshot();
    }

    /**
     * Return the most recently built payload.
     *
     * @return  array List of ['id' => int, 'name' => string, 'pct' => float] per reporting country.
     */
    public function foodSecurityData(): array
    {
        return $this->foodSecurityData;
    }

    /**
     * Reference year of the current snapshot (the year the map depicts).
     *
     * @return  int Four-digit year.
     */
    public function referenceYear(): int
    {
        return self::REFERENCE_YEAR;
    }

    /**
     * Populate the country list for the page (member-state filter; reserved for the dynamic version).
     */
    public function loadCountryList(): void
    {
        $this->countryList = $this->getCountryList();
    }

    /**
     * Return the loaded country list.
     *
     * @return  array Alphabetical list of country names.
     */
    public function countries(): array
    {
        return $this->countryList;
    }

    /**
     * Static food-insecurity snapshot, keyed by M49 numeric id.
     *
     * Prevalence of moderate or severe food insecurity in the total population (SDG indicator 2.1.2),
     * percent, reference year 2022 (the most recent year with near-complete country coverage; the
     * 2023 release is sparser and 2024 is not yet populated). Regional and income-group aggregates
     * are excluded so only individual countries/territories are mapped. Source: FAO Food Insecurity
     * Experience Scale (FIESMS series, FAOSTAT SDG database), CC BY 4.0. Replace this method body with
     * a database read to make the page dynamic.
     *
     * SECURITY: the 'name' field is treated as hostile free text downstream. The /insecurity map
     * tooltip (Stats/templates/stats-food-security.html, showTip) renders it via .text() (textContent),
     * never interpolated into .html(), so markup in a name cannot execute. If you swap this snapshot
     * for a live query, keep that assumption intact — do not echo names into HTML without escaping.
     *
     * @return  array List of ['id' => int, 'name' => string, 'pct' => float].
     */
    private function staticSnapshot(): array
    {
        $json = <<<'JSON'
[{"id":4,"name":"Afghanistan","pct":80.9},{"id":8,"name":"Albania","pct":32.2},{"id":12,"name":"Algeria","pct":18.9},{"id":28,"name":"Antigua and Barbuda","pct":13.5},{"id":31,"name":"Azerbaijan","pct":12.2},{"id":32,"name":"Argentina","pct":36.1},{"id":36,"name":"Australia","pct":12.9},{"id":40,"name":"Austria","pct":4.9},{"id":44,"name":"Bahamas","pct":17.2},{"id":51,"name":"Armenia","pct":7.8},{"id":52,"name":"Barbados","pct":31.1},{"id":56,"name":"Belgium","pct":7.3},{"id":70,"name":"Bosnia and Herzegovina","pct":13.3},{"id":72,"name":"Botswana","pct":55.4},{"id":76,"name":"Brazil","pct":18.4},{"id":100,"name":"Bulgaria","pct":14.8},{"id":104,"name":"Myanmar","pct":32.0},{"id":108,"name":"Burundi","pct":70.8},{"id":112,"name":"Belarus","pct":1.2},{"id":116,"name":"Cambodia","pct":43.2},{"id":120,"name":"Cameroon","pct":60.3},{"id":124,"name":"Canada","pct":8.5},{"id":132,"name":"Cabo Verde","pct":34.3},{"id":140,"name":"Central African Republic","pct":88.1},{"id":144,"name":"Sri Lanka","pct":11.4},{"id":148,"name":"Chad","pct":67.0},{"id":152,"name":"Chile","pct":17.6},{"id":170,"name":"Colombia","pct":30.6},{"id":174,"name":"Comoros","pct":79.7},{"id":180,"name":"Democratic Republic of the Congo","pct":80.2},{"id":188,"name":"Costa Rica","pct":16.2},{"id":191,"name":"Croatia","pct":7.9},{"id":203,"name":"Czechia","pct":10.0},{"id":204,"name":"Benin","pct":63.3},{"id":208,"name":"Denmark","pct":7.1},{"id":212,"name":"Dominica","pct":34.4},{"id":214,"name":"Dominican Republic","pct":46.1},{"id":218,"name":"Ecuador","pct":36.9},{"id":222,"name":"El Salvador","pct":46.9},{"id":231,"name":"Ethiopia","pct":59.0},{"id":233,"name":"Estonia","pct":9.3},{"id":242,"name":"Fiji","pct":29.2},{"id":246,"name":"Finland","pct":12.6},{"id":250,"name":"France","pct":7.9},{"id":262,"name":"Djibouti","pct":49.2},{"id":268,"name":"Georgia","pct":32.3},{"id":270,"name":"Gambia","pct":59.0},{"id":275,"name":"Palestine","pct":27.4},{"id":276,"name":"Germany","pct":4.0},{"id":288,"name":"Ghana","pct":42.4},{"id":296,"name":"Kiribati","pct":41.9},{"id":300,"name":"Greece","pct":6.4},{"id":308,"name":"Grenada","pct":18.6},{"id":320,"name":"Guatemala","pct":52.4},{"id":328,"name":"Guyana","pct":25.5},{"id":332,"name":"Haiti","pct":82.8},{"id":340,"name":"Honduras","pct":43.7},{"id":348,"name":"Hungary","pct":15.0},{"id":352,"name":"Iceland","pct":7.0},{"id":360,"name":"Indonesia","pct":4.7},{"id":364,"name":"Iran (Islamic Republic of)","pct":39.9},{"id":372,"name":"Ireland","pct":4.2},{"id":376,"name":"Israel","pct":7.2},{"id":380,"name":"Italy","pct":2.0},{"id":384,"name":"Côte d'Ivoire","pct":39.4},{"id":388,"name":"Jamaica","pct":55.1},{"id":392,"name":"Japan","pct":5.5},{"id":398,"name":"Kazakhstan","pct":2.2},{"id":404,"name":"Kenya","pct":72.8},{"id":410,"name":"Republic of Korea","pct":5.7},{"id":414,"name":"Kuwait","pct":8.6},{"id":417,"name":"Kyrgyzstan","pct":7.0},{"id":418,"name":"Lao People's Democratic Republic","pct":36.3},{"id":422,"name":"Lebanon","pct":40.1},{"id":426,"name":"Lesotho","pct":57.4},{"id":428,"name":"Latvia","pct":10.2},{"id":430,"name":"Liberia","pct":81.0},{"id":434,"name":"Libya","pct":37.9},{"id":440,"name":"Lithuania","pct":6.1},{"id":442,"name":"Luxembourg","pct":2.6},{"id":450,"name":"Madagascar","pct":68.6},{"id":454,"name":"Malawi","pct":81.7},{"id":458,"name":"Malaysia","pct":16.7},{"id":462,"name":"Maldives","pct":13.4},{"id":466,"name":"Mali","pct":20.0},{"id":470,"name":"Malta","pct":8.2},{"id":478,"name":"Mauritania","pct":61.2},{"id":480,"name":"Mauritius","pct":31.2},{"id":484,"name":"Mexico","pct":20.7},{"id":496,"name":"Mongolia","pct":4.9},{"id":498,"name":"Republic of Moldova","pct":24.7},{"id":499,"name":"Montenegro","pct":12.3},{"id":516,"name":"Namibia","pct":56.8},{"id":520,"name":"Nauru","pct":29.9},{"id":524,"name":"Nepal","pct":37.0},{"id":528,"name":"Netherlands (Kingdom of the)","pct":5.5},{"id":554,"name":"New Zealand","pct":16.4},{"id":562,"name":"Niger","pct":50.3},{"id":566,"name":"Nigeria","pct":73.9},{"id":578,"name":"Norway","pct":6.8},{"id":585,"name":"Palau","pct":28.1},{"id":586,"name":"Pakistan","pct":41.7},{"id":600,"name":"Paraguay","pct":26.2},{"id":604,"name":"Peru","pct":42.5},{"id":608,"name":"Philippines","pct":33.2},{"id":616,"name":"Poland","pct":5.5},{"id":620,"name":"Portugal","pct":12.3},{"id":624,"name":"Guinea-Bissau","pct":62.5},{"id":642,"name":"Romania","pct":19.1},{"id":643,"name":"Russian Federation","pct":3.6},{"id":678,"name":"Sao Tome and Principe","pct":54.6},{"id":686,"name":"Senegal","pct":29.4},{"id":688,"name":"Serbia","pct":13.0},{"id":694,"name":"Sierra Leone","pct":88.6},{"id":702,"name":"Singapore","pct":7.7},{"id":703,"name":"Slovakia","pct":9.0},{"id":704,"name":"Viet Nam","pct":10.8},{"id":705,"name":"Slovenia","pct":7.9},{"id":710,"name":"South Africa","pct":20.0},{"id":716,"name":"Zimbabwe","pct":70.7},{"id":724,"name":"Spain","pct":6.9},{"id":728,"name":"South Sudan","pct":87.3},{"id":740,"name":"Suriname","pct":35.8},{"id":748,"name":"Eswatini","pct":57.2},{"id":752,"name":"Sweden","pct":6.0},{"id":756,"name":"Switzerland","pct":2.5},{"id":762,"name":"Tajikistan","pct":28.0},{"id":764,"name":"Thailand","pct":6.5},{"id":768,"name":"Togo","pct":57.0},{"id":776,"name":"Tonga","pct":14.8},{"id":780,"name":"Trinidad and Tobago","pct":27.6},{"id":788,"name":"Tunisia","pct":26.7},{"id":800,"name":"Uganda","pct":59.5},{"id":804,"name":"Ukraine","pct":31.0},{"id":807,"name":"North Macedonia","pct":20.2},{"id":818,"name":"Egypt","pct":29.8},{"id":826,"name":"United Kingdom of Great Britain and Northern Ireland","pct":5.7},{"id":834,"name":"United Republic of Tanzania","pct":58.2},{"id":840,"name":"United States of America","pct":9.3},{"id":854,"name":"Burkina Faso","pct":40.7},{"id":858,"name":"Uruguay","pct":15.7},{"id":860,"name":"Uzbekistan","pct":26.1},{"id":882,"name":"Samoa","pct":19.3},{"id":887,"name":"Yemen","pct":72.5},{"id":894,"name":"Zambia","pct":46.1}]
JSON;

        return \json_decode($json, true);
    }
}
