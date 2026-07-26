<?php

declare(strict_types=1);

namespace Tfish\Rangefinder\Traits;

/**
 * \Tfish\Rangefinder\Traits\RangefinderCountries trait file.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */

/**
 * ISO 3166-1 alpha-2 country code -> display name, and a sort key for ordering names.
 *
 * The occurrence database stores country_code as the canonical country key because it is the only
 * one every source supplies; the human-readable country name is present for curated records but
 * NULL for every record that arrived through the GBIF sweep. On the live Artemia dataset that is
 * 17 of 65 countries with a code and no name. Without this table those countries would appear in
 * the filter as bare codes, so the map would ask a visitor to know that QA means Qatar.
 *
 * Used ONLY to fill gaps. Where the database supplies a country name, that name wins: it is what
 * the original data source recorded, and this project treats source-supplied values as
 * authoritative rather than substituting a mechanically-derived alternative. So a record whose
 * source wrote "Brasil" or "P.R. China" keeps that wording; it is not silently rewritten here.
 *
 * Lives in PHP rather than in the browser because the country filter is server-rendered: the facet
 * does not change after page load, so the whole list is built in templates/map.html and the client
 * only reads the selected value. The predecessor of this file shipped the entire table to every
 * visitor to fill in 17 names.
 *
 * Generated from the Debian iso-codes package (/usr/share/iso-codes/json/iso_3166-1.json), using
 * each entry's short common name where it defines one and its formal name otherwise. Regenerate
 * with the generator rather than hand-editing.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0.4
 * @since       2.2.9
 * @package     Rangefinder
 */
trait RangefinderCountries
{
    /**
     * Fold the accented Latin letters that occur in country names down to ASCII, for sorting.
     *
     * Deliberately a fixed table rather than iconv //TRANSLIT (whose output varies with the system
     * locale and libc) or Normalizer (ext-intl, which is not guaranteed present). It covers the
     * Latin-1 and Latin Extended-A range that appears in ISO 3166 names and in the database's own
     * country_name values; anything outside it is left as-is and sorts after the ASCII letters,
     * which is the same fallback an unmapped name got before.
     */
    private const FOLD = [
        "\u{00c0}" => 'A', "\u{00c1}" => 'A', "\u{00c2}" => 'A', "\u{00c3}" => 'A',
        "\u{00c4}" => 'A', "\u{00c5}" => 'A', "\u{00c6}" => 'AE', "\u{00c7}" => 'C',
        "\u{00c8}" => 'E', "\u{00c9}" => 'E', "\u{00ca}" => 'E', "\u{00cb}" => 'E',
        "\u{00cc}" => 'I', "\u{00cd}" => 'I', "\u{00ce}" => 'I', "\u{00cf}" => 'I',
        "\u{00d1}" => 'N', "\u{00d2}" => 'O', "\u{00d3}" => 'O', "\u{00d4}" => 'O',
        "\u{00d5}" => 'O', "\u{00d6}" => 'O', "\u{00d8}" => 'O', "\u{00d9}" => 'U',
        "\u{00da}" => 'U', "\u{00db}" => 'U', "\u{00dc}" => 'U', "\u{00dd}" => 'Y',
        "\u{00df}" => 'ss',
        "\u{00e0}" => 'a', "\u{00e1}" => 'a', "\u{00e2}" => 'a', "\u{00e3}" => 'a',
        "\u{00e4}" => 'a', "\u{00e5}" => 'a', "\u{00e6}" => 'ae', "\u{00e7}" => 'c',
        "\u{00e8}" => 'e', "\u{00e9}" => 'e', "\u{00ea}" => 'e', "\u{00eb}" => 'e',
        "\u{00ec}" => 'i', "\u{00ed}" => 'i', "\u{00ee}" => 'i', "\u{00ef}" => 'i',
        "\u{00f1}" => 'n', "\u{00f2}" => 'o', "\u{00f3}" => 'o', "\u{00f4}" => 'o',
        "\u{00f5}" => 'o', "\u{00f6}" => 'o', "\u{00f8}" => 'o', "\u{00f9}" => 'u',
        "\u{00fa}" => 'u', "\u{00fb}" => 'u', "\u{00fc}" => 'u', "\u{00fd}" => 'y',
        "\u{00ff}" => 'y', "\u{0131}" => 'i', "\u{015f}" => 's', "\u{0161}" => 's',
        "\u{017e}" => 'z',
    ];

    /**
     * Resolve a country code to a display name.
     *
     * @param   string|null $code ISO 3166-1 alpha-2 code.
     * @param   string|null $supplied Name from the database; returned unchanged when present.
     * @return  string The display name, falling back to the bare code if the table has no entry.
     */
    public function countryName(?string $code, ?string $supplied = null): string
    {
        if (!empty($supplied)) return $supplied;

        $code = (string) $code;

        return self::COUNTRY_NAMES[$code] ?? $code;
    }

    /**
     * Sort key for a country display name: accent-folded and lower-cased.
     *
     * So that an accented initial files with its unaccented letter rather than after Z, which is
     * what a byte-wise comparison of UTF-8 would do.
     *
     * @param   string $name Display name.
     * @return  string
     */
    public function countrySortKey(string $name): string
    {
        return \strtolower(\strtr($name, self::FOLD));
    }

    /**
     * ISO 3166-1 alpha-2 code -> display name. Generated; do not hand-edit.
     */
    private const COUNTRY_NAMES = [
        'AD' => 'Andorra',
        'AE' => 'United Arab Emirates',
        'AF' => 'Afghanistan',
        'AG' => 'Antigua and Barbuda',
        'AI' => 'Anguilla',
        'AL' => 'Albania',
        'AM' => 'Armenia',
        'AO' => 'Angola',
        'AQ' => 'Antarctica',
        'AR' => 'Argentina',
        'AS' => 'American Samoa',
        'AT' => 'Austria',
        'AU' => 'Australia',
        'AW' => 'Aruba',
        'AX' => 'Åland Islands',
        'AZ' => 'Azerbaijan',
        'BA' => 'Bosnia and Herzegovina',
        'BB' => 'Barbados',
        'BD' => 'Bangladesh',
        'BE' => 'Belgium',
        'BF' => 'Burkina Faso',
        'BG' => 'Bulgaria',
        'BH' => 'Bahrain',
        'BI' => 'Burundi',
        'BJ' => 'Benin',
        'BL' => 'Saint Barthélemy',
        'BM' => 'Bermuda',
        'BN' => 'Brunei Darussalam',
        'BO' => 'Bolivia',
        'BQ' => 'Bonaire, Sint Eustatius and Saba',
        'BR' => 'Brazil',
        'BS' => 'Bahamas',
        'BT' => 'Bhutan',
        'BV' => 'Bouvet Island',
        'BW' => 'Botswana',
        'BY' => 'Belarus',
        'BZ' => 'Belize',
        'CA' => 'Canada',
        'CC' => 'Cocos (Keeling) Islands',
        'CD' => 'Congo, The Democratic Republic of the',
        'CF' => 'Central African Republic',
        'CG' => 'Congo',
        'CH' => 'Switzerland',
        'CI' => 'Côte d\'Ivoire',
        'CK' => 'Cook Islands',
        'CL' => 'Chile',
        'CM' => 'Cameroon',
        'CN' => 'China',
        'CO' => 'Colombia',
        'CR' => 'Costa Rica',
        'CU' => 'Cuba',
        'CV' => 'Cabo Verde',
        'CW' => 'Curaçao',
        'CX' => 'Christmas Island',
        'CY' => 'Cyprus',
        'CZ' => 'Czechia',
        'DE' => 'Germany',
        'DJ' => 'Djibouti',
        'DK' => 'Denmark',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'DZ' => 'Algeria',
        'EC' => 'Ecuador',
        'EE' => 'Estonia',
        'EG' => 'Egypt',
        'EH' => 'Western Sahara',
        'ER' => 'Eritrea',
        'ES' => 'Spain',
        'ET' => 'Ethiopia',
        'FI' => 'Finland',
        'FJ' => 'Fiji',
        'FK' => 'Falkland Islands (Malvinas)',
        'FM' => 'Micronesia, Federated States of',
        'FO' => 'Faroe Islands',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GB' => 'United Kingdom',
        'GD' => 'Grenada',
        'GE' => 'Georgia',
        'GF' => 'French Guiana',
        'GG' => 'Guernsey',
        'GH' => 'Ghana',
        'GI' => 'Gibraltar',
        'GL' => 'Greenland',
        'GM' => 'Gambia',
        'GN' => 'Guinea',
        'GP' => 'Guadeloupe',
        'GQ' => 'Equatorial Guinea',
        'GR' => 'Greece',
        'GS' => 'South Georgia and the South Sandwich Islands',
        'GT' => 'Guatemala',
        'GU' => 'Guam',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HK' => 'Hong Kong',
        'HM' => 'Heard Island and McDonald Islands',
        'HN' => 'Honduras',
        'HR' => 'Croatia',
        'HT' => 'Haiti',
        'HU' => 'Hungary',
        'ID' => 'Indonesia',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IM' => 'Isle of Man',
        'IN' => 'India',
        'IO' => 'British Indian Ocean Territory',
        'IQ' => 'Iraq',
        'IR' => 'Iran',
        'IS' => 'Iceland',
        'IT' => 'Italy',
        'JE' => 'Jersey',
        'JM' => 'Jamaica',
        'JO' => 'Jordan',
        'JP' => 'Japan',
        'KE' => 'Kenya',
        'KG' => 'Kyrgyzstan',
        'KH' => 'Cambodia',
        'KI' => 'Kiribati',
        'KM' => 'Comoros',
        'KN' => 'Saint Kitts and Nevis',
        'KP' => 'North Korea',
        'KR' => 'South Korea',
        'KW' => 'Kuwait',
        'KY' => 'Cayman Islands',
        'KZ' => 'Kazakhstan',
        'LA' => 'Laos',
        'LB' => 'Lebanon',
        'LC' => 'Saint Lucia',
        'LI' => 'Liechtenstein',
        'LK' => 'Sri Lanka',
        'LR' => 'Liberia',
        'LS' => 'Lesotho',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'LV' => 'Latvia',
        'LY' => 'Libya',
        'MA' => 'Morocco',
        'MC' => 'Monaco',
        'MD' => 'Moldova',
        'ME' => 'Montenegro',
        'MF' => 'Saint Martin (French part)',
        'MG' => 'Madagascar',
        'MH' => 'Marshall Islands',
        'MK' => 'North Macedonia',
        'ML' => 'Mali',
        'MM' => 'Myanmar',
        'MN' => 'Mongolia',
        'MO' => 'Macao',
        'MP' => 'Northern Mariana Islands',
        'MQ' => 'Martinique',
        'MR' => 'Mauritania',
        'MS' => 'Montserrat',
        'MT' => 'Malta',
        'MU' => 'Mauritius',
        'MV' => 'Maldives',
        'MW' => 'Malawi',
        'MX' => 'Mexico',
        'MY' => 'Malaysia',
        'MZ' => 'Mozambique',
        'NA' => 'Namibia',
        'NC' => 'New Caledonia',
        'NE' => 'Niger',
        'NF' => 'Norfolk Island',
        'NG' => 'Nigeria',
        'NI' => 'Nicaragua',
        'NL' => 'Netherlands',
        'NO' => 'Norway',
        'NP' => 'Nepal',
        'NR' => 'Nauru',
        'NU' => 'Niue',
        'NZ' => 'New Zealand',
        'OM' => 'Oman',
        'PA' => 'Panama',
        'PE' => 'Peru',
        'PF' => 'French Polynesia',
        'PG' => 'Papua New Guinea',
        'PH' => 'Philippines',
        'PK' => 'Pakistan',
        'PL' => 'Poland',
        'PM' => 'Saint Pierre and Miquelon',
        'PN' => 'Pitcairn',
        'PR' => 'Puerto Rico',
        'PS' => 'Palestine, State of',
        'PT' => 'Portugal',
        'PW' => 'Palau',
        'PY' => 'Paraguay',
        'QA' => 'Qatar',
        'RE' => 'Réunion',
        'RO' => 'Romania',
        'RS' => 'Serbia',
        'RU' => 'Russian Federation',
        'RW' => 'Rwanda',
        'SA' => 'Saudi Arabia',
        'SB' => 'Solomon Islands',
        'SC' => 'Seychelles',
        'SD' => 'Sudan',
        'SE' => 'Sweden',
        'SG' => 'Singapore',
        'SH' => 'Saint Helena, Ascension and Tristan da Cunha',
        'SI' => 'Slovenia',
        'SJ' => 'Svalbard and Jan Mayen',
        'SK' => 'Slovakia',
        'SL' => 'Sierra Leone',
        'SM' => 'San Marino',
        'SN' => 'Senegal',
        'SO' => 'Somalia',
        'SR' => 'Suriname',
        'SS' => 'South Sudan',
        'ST' => 'Sao Tome and Principe',
        'SV' => 'El Salvador',
        'SX' => 'Sint Maarten (Dutch part)',
        'SY' => 'Syria',
        'SZ' => 'Eswatini',
        'TC' => 'Turks and Caicos Islands',
        'TD' => 'Chad',
        'TF' => 'French Southern Territories',
        'TG' => 'Togo',
        'TH' => 'Thailand',
        'TJ' => 'Tajikistan',
        'TK' => 'Tokelau',
        'TL' => 'Timor-Leste',
        'TM' => 'Turkmenistan',
        'TN' => 'Tunisia',
        'TO' => 'Tonga',
        'TR' => 'Türkiye',
        'TT' => 'Trinidad and Tobago',
        'TV' => 'Tuvalu',
        'TW' => 'Taiwan, Province of China',
        'TZ' => 'Tanzania',
        'UA' => 'Ukraine',
        'UG' => 'Uganda',
        'UM' => 'United States Minor Outlying Islands',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VA' => 'Holy See (Vatican City State)',
        'VC' => 'Saint Vincent and the Grenadines',
        'VE' => 'Venezuela',
        'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.S.',
        'VN' => 'Vietnam',
        'VU' => 'Vanuatu',
        'WF' => 'Wallis and Futuna',
        'WS' => 'Samoa',
        'YE' => 'Yemen',
        'YT' => 'Mayotte',
        'ZA' => 'South Africa',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
}
