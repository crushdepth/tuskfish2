<?php

declare(strict_types=1);

namespace Tfish\Stats\Model;

/**
 * \Tfish\Stats\Model\Consumption class file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Stats
 */

/**
 * Model for the consumption page (/consumption/).
 *
 * Supplies the per-capita apparent aquatic food consumption choropleth (kg/person/year) keyed by
 * M49 numeric country id. The figures are presently a static snapshot drawn from the FAO Food
 * Balance Sheets 2023 Yearbook, baked into this class so the data lives behind the Model boundary
 * (not the template) ready for the page to be made dynamic: when a consumption table is added to the
 * Stats database, only loadConsumptionData() changes — it reads the database instead of the baked
 * snapshot — and the ViewModel/Controller/template are untouched. The connection is opened (via the
 * StatsDatabase trait) now so the country list and the future data query are ready to wire up.
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
class Consumption
{
    use \Tfish\Traits\ValidateString;
    use \Tfish\Stats\Traits\StatsDatabase;

    private $database;
    private $preference;
    private $session;
    private \Tfish\Logger $logger;

    private array $consumptionData = [];
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
     * Build the consumption payload.
     *
     * The map is a single global choropleth, so the country argument has no effect on the data
     * returned; it is accepted (and validated) only to mirror the other Stats models and to keep the
     * persisted state flowing through the dashboard. When this page is made dynamic the static
     * snapshot below is replaced by a database read.
     *
     * @param   string $countryName English country name, or '' for the global picture (unused for now).
     */
    public function loadConsumptionData(string $countryName = ''): void
    {
        $this->trimString($countryName); // Validate UTF-8; the value is not used while the map is static.

        $this->consumptionData = $this->staticSnapshot();
    }

    /**
     * Return the most recently built payload.
     *
     * @return  array List of ['id' => int, 'name' => string, 'kg' => float] per reporting country.
     */
    public function consumptionData(): array
    {
        return $this->consumptionData;
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
     * Static per-capita consumption snapshot, keyed by M49 numeric id.
     *
     * Apparent consumption (domestic supply / population) of item "Fish, Seafood", kg/person/year,
     * 2023. China is reported by FAO as separate areas (mainland, Hong Kong SAR, Macao SAR, Taiwan
     * Province of). Source: FAO Food Balance Sheets (FAOSTAT), CC BY 4.0. Replace this method body
     * with a database read to make the page dynamic.
     *
     * @return  array List of ['id' => int, 'name' => string, 'kg' => float].
     */
    private function staticSnapshot(): array
    {
        $json = <<<'JSON'
[{"id":4,"name":"Afghanistan","kg":0.4},{"id":8,"name":"Albania","kg":8.6},{"id":12,"name":"Algeria","kg":2.8},{"id":24,"name":"Angola","kg":14.3},{"id":28,"name":"Antigua and Barbuda","kg":54.5},{"id":31,"name":"Azerbaijan","kg":2.1},{"id":32,"name":"Argentina","kg":7.1},{"id":36,"name":"Australia","kg":24.2},{"id":40,"name":"Austria","kg":14.1},{"id":44,"name":"Bahamas","kg":27.7},{"id":48,"name":"Bahrain","kg":19.8},{"id":50,"name":"Bangladesh","kg":27.0},{"id":51,"name":"Armenia","kg":4.9},{"id":52,"name":"Barbados","kg":43.2},{"id":56,"name":"Belgium","kg":23.9},{"id":64,"name":"Bhutan","kg":6.3},{"id":68,"name":"Bolivia (Plurinational State of)","kg":2.1},{"id":70,"name":"Bosnia and Herzegovina","kg":5.9},{"id":72,"name":"Botswana","kg":2.2},{"id":76,"name":"Brazil","kg":8.2},{"id":84,"name":"Belize","kg":16.9},{"id":90,"name":"Solomon Islands","kg":30.5},{"id":100,"name":"Bulgaria","kg":7.2},{"id":104,"name":"Myanmar","kg":40.4},{"id":112,"name":"Belarus","kg":12.5},{"id":116,"name":"Cambodia","kg":39.4},{"id":120,"name":"Cameroon","kg":18.1},{"id":124,"name":"Canada","kg":20.8},{"id":132,"name":"Cabo Verde","kg":10.7},{"id":144,"name":"Sri Lanka","kg":22.7},{"id":152,"name":"Chile","kg":14.4},{"id":156,"name":"China, mainland","kg":41.7},{"id":158,"name":"China, Taiwan Province of","kg":30.4},{"id":170,"name":"Colombia","kg":10.3},{"id":174,"name":"Comoros","kg":17.0},{"id":178,"name":"Congo","kg":23.2},{"id":180,"name":"Democratic Republic of the Congo","kg":3.3},{"id":188,"name":"Costa Rica","kg":17.8},{"id":191,"name":"Croatia","kg":19.9},{"id":196,"name":"Cyprus","kg":18.0},{"id":203,"name":"Czechia","kg":10.4},{"id":208,"name":"Denmark","kg":22.6},{"id":214,"name":"Dominican Republic","kg":17.1},{"id":218,"name":"Ecuador","kg":6.5},{"id":222,"name":"El Salvador","kg":7.2},{"id":231,"name":"Ethiopia","kg":0.5},{"id":233,"name":"Estonia","kg":12.7},{"id":242,"name":"Fiji","kg":27.9},{"id":246,"name":"Finland","kg":31.5},{"id":250,"name":"France","kg":32.6},{"id":258,"name":"French Polynesia","kg":50.5},{"id":262,"name":"Djibouti","kg":5.3},{"id":266,"name":"Gabon","kg":27.0},{"id":268,"name":"Georgia","kg":10.3},{"id":270,"name":"Gambia","kg":20.2},{"id":276,"name":"Germany","kg":13.0},{"id":288,"name":"Ghana","kg":23.6},{"id":296,"name":"Kiribati","kg":72.3},{"id":300,"name":"Greece","kg":19.6},{"id":308,"name":"Grenada","kg":20.3},{"id":320,"name":"Guatemala","kg":3.8},{"id":324,"name":"Guinea","kg":10.7},{"id":328,"name":"Guyana","kg":24.8},{"id":332,"name":"Haiti","kg":5.4},{"id":340,"name":"Honduras","kg":4.4},{"id":344,"name":"China, Hong Kong SAR","kg":69.1},{"id":348,"name":"Hungary","kg":6.5},{"id":352,"name":"Iceland","kg":83.8},{"id":356,"name":"India","kg":8.7},{"id":360,"name":"Indonesia","kg":40.4},{"id":364,"name":"Iran (Islamic Republic of)","kg":11.3},{"id":368,"name":"Iraq","kg":2.6},{"id":372,"name":"Ireland","kg":19.3},{"id":376,"name":"Israel","kg":24.8},{"id":380,"name":"Italy","kg":29.4},{"id":384,"name":"Côte d'Ivoire","kg":23.7},{"id":388,"name":"Jamaica","kg":27.9},{"id":398,"name":"Kazakhstan","kg":3.9},{"id":400,"name":"Jordan","kg":4.8},{"id":404,"name":"Kenya","kg":2.9},{"id":410,"name":"Republic of Korea","kg":52.8},{"id":414,"name":"Kuwait","kg":13.0},{"id":417,"name":"Kyrgyzstan","kg":1.5},{"id":418,"name":"Lao People's Democratic Republic","kg":28.5},{"id":422,"name":"Lebanon","kg":4.7},{"id":426,"name":"Lesotho","kg":1.9},{"id":428,"name":"Latvia","kg":24.5},{"id":430,"name":"Liberia","kg":4.8},{"id":434,"name":"Libya","kg":12.9},{"id":440,"name":"Lithuania","kg":28.5},{"id":442,"name":"Luxembourg","kg":30.3},{"id":446,"name":"China, Macao SAR","kg":61.1},{"id":450,"name":"Madagascar","kg":3.7},{"id":454,"name":"Malawi","kg":8.8},{"id":458,"name":"Malaysia","kg":51.0},{"id":462,"name":"Maldives","kg":79.7},{"id":470,"name":"Malta","kg":31.1},{"id":478,"name":"Mauritania","kg":7.6},{"id":480,"name":"Mauritius","kg":29.4},{"id":484,"name":"Mexico","kg":13.6},{"id":496,"name":"Mongolia","kg":1.0},{"id":498,"name":"Republic of Moldova","kg":19.5},{"id":499,"name":"Montenegro","kg":11.9},{"id":504,"name":"Morocco","kg":16.7},{"id":508,"name":"Mozambique","kg":12.9},{"id":512,"name":"Oman","kg":28.1},{"id":516,"name":"Namibia","kg":9.7},{"id":520,"name":"Nauru","kg":26.1},{"id":524,"name":"Nepal","kg":4.3},{"id":528,"name":"Netherlands (Kingdom of the)","kg":19.1},{"id":540,"name":"New Caledonia","kg":22.5},{"id":548,"name":"Vanuatu","kg":30.0},{"id":554,"name":"New Zealand","kg":24.6},{"id":558,"name":"Nicaragua","kg":7.3},{"id":562,"name":"Niger","kg":1.9},{"id":566,"name":"Nigeria","kg":6.9},{"id":578,"name":"Norway","kg":49.1},{"id":583,"name":"Micronesia (Federated States of)","kg":49.0},{"id":584,"name":"Marshall Islands","kg":44.6},{"id":586,"name":"Pakistan","kg":1.4},{"id":591,"name":"Panama","kg":14.9},{"id":598,"name":"Papua New Guinea","kg":7.1},{"id":600,"name":"Paraguay","kg":5.3},{"id":604,"name":"Peru","kg":25.9},{"id":608,"name":"Philippines","kg":26.3},{"id":616,"name":"Poland","kg":11.2},{"id":620,"name":"Portugal","kg":53.5},{"id":624,"name":"Guinea-Bissau","kg":2.4},{"id":626,"name":"Timor-Leste","kg":7.0},{"id":634,"name":"Qatar","kg":22.0},{"id":642,"name":"Romania","kg":8.7},{"id":643,"name":"Russian Federation","kg":22.8},{"id":646,"name":"Rwanda","kg":5.2},{"id":659,"name":"Saint Kitts and Nevis","kg":36.4},{"id":662,"name":"Saint Lucia","kg":24.9},{"id":670,"name":"Saint Vincent and the Grenadines","kg":20.1},{"id":678,"name":"Sao Tome and Principe","kg":26.5},{"id":682,"name":"Saudi Arabia","kg":12.9},{"id":686,"name":"Senegal","kg":13.8},{"id":688,"name":"Serbia","kg":8.4},{"id":690,"name":"Seychelles","kg":42.9},{"id":694,"name":"Sierra Leone","kg":24.4},{"id":703,"name":"Slovakia","kg":10.6},{"id":704,"name":"Viet Nam","kg":40.1},{"id":705,"name":"Slovenia","kg":11.2},{"id":710,"name":"South Africa","kg":5.6},{"id":716,"name":"Zimbabwe","kg":2.6},{"id":724,"name":"Spain","kg":36.8},{"id":740,"name":"Suriname","kg":16.7},{"id":748,"name":"Eswatini","kg":3.8},{"id":752,"name":"Sweden","kg":30.6},{"id":756,"name":"Switzerland","kg":16.5},{"id":760,"name":"Syrian Arab Republic","kg":0.8},{"id":762,"name":"Tajikistan","kg":0.9},{"id":764,"name":"Thailand","kg":28.6},{"id":776,"name":"Tonga","kg":28.6},{"id":780,"name":"Trinidad and Tobago","kg":18.0},{"id":784,"name":"United Arab Emirates","kg":23.1},{"id":788,"name":"Tunisia","kg":15.9},{"id":792,"name":"Türkiye","kg":5.3},{"id":795,"name":"Turkmenistan","kg":2.4},{"id":798,"name":"Tuvalu","kg":55.9},{"id":800,"name":"Uganda","kg":15.4},{"id":804,"name":"Ukraine","kg":16.9},{"id":807,"name":"North Macedonia","kg":8.0},{"id":818,"name":"Egypt","kg":20.5},{"id":826,"name":"United Kingdom of Great Britain and Northern Ireland","kg":17.7},{"id":834,"name":"United Republic of Tanzania","kg":6.4},{"id":840,"name":"United States of America","kg":21.9},{"id":854,"name":"Burkina Faso","kg":10.5},{"id":858,"name":"Uruguay","kg":11.9},{"id":860,"name":"Uzbekistan","kg":5.2},{"id":862,"name":"Venezuela (Bolivarian Republic of)","kg":8.8},{"id":882,"name":"Samoa","kg":46.6},{"id":887,"name":"Yemen","kg":2.0},{"id":894,"name":"Zambia","kg":12.9}]
JSON;

        return \json_decode($json, true);
    }
}
