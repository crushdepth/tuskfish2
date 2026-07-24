/**
 * ISO 3166-1 alpha-2 country code -> display name.
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
 * Generated from the Debian iso-codes package (/usr/share/iso-codes/json/iso_3166-1.json), using
 * each entry's short common name where it defines one and its formal name otherwise. Stored as one
 * delimited string rather than an object literal because it is a fifth of the size that way, and it
 * is parsed once at load.
 *
 * Regenerate rather than hand-edit. Lines are wrapped on entry boundaries only, never inside a
 * name; ':' and '|' are asserted absent from every name at generation time; and names are emitted
 * as JSON string literals, so quotes are escaped and accented characters are written as \uXXXX
 * escapes. The file is therefore pure ASCII and does not depend on how it is served.
 *
 * @copyright   Simon Wilkinson 2026+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @package     Rangefinder
 */
(function () {
    'use strict';

    var TABLE =
      "AD:Andorra|AE:United Arab Emirates|AF:Afghanistan|AG:Antigua and Barbuda|AI:Anguilla|"
    + "AL:Albania|AM:Armenia|AO:Angola|AQ:Antarctica|AR:Argentina|AS:American Samoa|AT:Austria|"
    + "AU:Australia|AW:Aruba|AX:\u00c5land Islands|AZ:Azerbaijan|BA:Bosnia and Herzegovina|"
    + "BB:Barbados|BD:Bangladesh|BE:Belgium|BF:Burkina Faso|BG:Bulgaria|BH:Bahrain|BI:Burundi|"
    + "BJ:Benin|BL:Saint Barth\u00e9lemy|BM:Bermuda|BN:Brunei Darussalam|BO:Bolivia|"
    + "BQ:Bonaire, Sint Eustatius and Saba|BR:Brazil|BS:Bahamas|BT:Bhutan|BV:Bouvet Island|"
    + "BW:Botswana|BY:Belarus|BZ:Belize|CA:Canada|CC:Cocos (Keeling) Islands|"
    + "CD:Congo, The Democratic Republic of the|CF:Central African Republic|CG:Congo|"
    + "CH:Switzerland|CI:C\u00f4te d'Ivoire|CK:Cook Islands|CL:Chile|CM:Cameroon|CN:China|"
    + "CO:Colombia|CR:Costa Rica|CU:Cuba|CV:Cabo Verde|CW:Cura\u00e7ao|CX:Christmas Island|CY:Cyprus|"
    + "CZ:Czechia|DE:Germany|DJ:Djibouti|DK:Denmark|DM:Dominica|DO:Dominican Republic|"
    + "DZ:Algeria|EC:Ecuador|EE:Estonia|EG:Egypt|EH:Western Sahara|ER:Eritrea|ES:Spain|"
    + "ET:Ethiopia|FI:Finland|FJ:Fiji|FK:Falkland Islands (Malvinas)|"
    + "FM:Micronesia, Federated States of|FO:Faroe Islands|FR:France|GA:Gabon|GB:United Kingdom|"
    + "GD:Grenada|GE:Georgia|GF:French Guiana|GG:Guernsey|GH:Ghana|GI:Gibraltar|GL:Greenland|"
    + "GM:Gambia|GN:Guinea|GP:Guadeloupe|GQ:Equatorial Guinea|GR:Greece|"
    + "GS:South Georgia and the South Sandwich Islands|GT:Guatemala|GU:Guam|GW:Guinea-Bissau|"
    + "GY:Guyana|HK:Hong Kong|HM:Heard Island and McDonald Islands|HN:Honduras|HR:Croatia|"
    + "HT:Haiti|HU:Hungary|ID:Indonesia|IE:Ireland|IL:Israel|IM:Isle of Man|IN:India|"
    + "IO:British Indian Ocean Territory|IQ:Iraq|IR:Iran|IS:Iceland|IT:Italy|JE:Jersey|"
    + "JM:Jamaica|JO:Jordan|JP:Japan|KE:Kenya|KG:Kyrgyzstan|KH:Cambodia|KI:Kiribati|KM:Comoros|"
    + "KN:Saint Kitts and Nevis|KP:North Korea|KR:South Korea|KW:Kuwait|KY:Cayman Islands|"
    + "KZ:Kazakhstan|LA:Laos|LB:Lebanon|LC:Saint Lucia|LI:Liechtenstein|LK:Sri Lanka|LR:Liberia|"
    + "LS:Lesotho|LT:Lithuania|LU:Luxembourg|LV:Latvia|LY:Libya|MA:Morocco|MC:Monaco|MD:Moldova|"
    + "ME:Montenegro|MF:Saint Martin (French part)|MG:Madagascar|MH:Marshall Islands|"
    + "MK:North Macedonia|ML:Mali|MM:Myanmar|MN:Mongolia|MO:Macao|MP:Northern Mariana Islands|"
    + "MQ:Martinique|MR:Mauritania|MS:Montserrat|MT:Malta|MU:Mauritius|MV:Maldives|MW:Malawi|"
    + "MX:Mexico|MY:Malaysia|MZ:Mozambique|NA:Namibia|NC:New Caledonia|NE:Niger|"
    + "NF:Norfolk Island|NG:Nigeria|NI:Nicaragua|NL:Netherlands|NO:Norway|NP:Nepal|NR:Nauru|"
    + "NU:Niue|NZ:New Zealand|OM:Oman|PA:Panama|PE:Peru|PF:French Polynesia|PG:Papua New Guinea|"
    + "PH:Philippines|PK:Pakistan|PL:Poland|PM:Saint Pierre and Miquelon|PN:Pitcairn|"
    + "PR:Puerto Rico|PS:Palestine, State of|PT:Portugal|PW:Palau|PY:Paraguay|QA:Qatar|"
    + "RE:R\u00e9union|RO:Romania|RS:Serbia|RU:Russian Federation|RW:Rwanda|SA:Saudi Arabia|"
    + "SB:Solomon Islands|SC:Seychelles|SD:Sudan|SE:Sweden|SG:Singapore|"
    + "SH:Saint Helena, Ascension and Tristan da Cunha|SI:Slovenia|SJ:Svalbard and Jan Mayen|"
    + "SK:Slovakia|SL:Sierra Leone|SM:San Marino|SN:Senegal|SO:Somalia|SR:Suriname|"
    + "SS:South Sudan|ST:Sao Tome and Principe|SV:El Salvador|SX:Sint Maarten (Dutch part)|"
    + "SY:Syria|SZ:Eswatini|TC:Turks and Caicos Islands|TD:Chad|TF:French Southern Territories|"
    + "TG:Togo|TH:Thailand|TJ:Tajikistan|TK:Tokelau|TL:Timor-Leste|TM:Turkmenistan|TN:Tunisia|"
    + "TO:Tonga|TR:T\u00fcrkiye|TT:Trinidad and Tobago|TV:Tuvalu|TW:Taiwan|TZ:Tanzania|UA:Ukraine|"
    + "UG:Uganda|UM:United States Minor Outlying Islands|US:United States|UY:Uruguay|"
    + "UZ:Uzbekistan|VA:Holy See (Vatican City State)|VC:Saint Vincent and the Grenadines|"
    + "VE:Venezuela|VG:Virgin Islands, British|VI:Virgin Islands, U.S.|VN:Vietnam|VU:Vanuatu|"
    + "WF:Wallis and Futuna|WS:Samoa|YE:Yemen|YT:Mayotte|ZA:South Africa|ZM:Zambia|ZW:Zimbabwe";

    var names = Object.create(null);

    TABLE.split('|').forEach(function (entry) {
        var split = entry.indexOf(':');

        if (split > 0) {
            names[entry.slice(0, split)] = entry.slice(split + 1);
        }
    });

    window.rangefinder = window.rangefinder || {};

    /**
     * Resolve a country code to a display name.
     *
     * @param   {string} code  ISO 3166-1 alpha-2 code.
     * @param   {string} [supplied]  Name from the database; returned unchanged when present.
     * @returns {string} The display name, falling back to the bare code if the table has no entry.
     */
    window.rangefinder.countryName = function (code, supplied) {
        if (supplied) return supplied;

        return names[code] || code || '';
    };
}());
