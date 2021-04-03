<?php

declare(strict_types=1);

/**
 * Tuskfish configuration script.
 * 
 * Sets file paths and key constants. Included in every page via mainfile.php.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     core
 */

if (!\defined("TFISH_ROOT_PATH")) die("TFISH_ERROR_ROOT_PATH_NOT_DEFINED");

// Constants that make use of the physical path.
\define("TFISH_ADMIN_PATH", TFISH_ROOT_PATH . "admin/");
\define("TFISH_PUBLIC_CACHE_PATH", TFISH_ROOT_PATH . "cache/");
\define("TFISH_THEMES_PATH", TFISH_ROOT_PATH . "themes/");
\define("TFISH_VENDOR_PATH", TFISH_ROOT_PATH . "vendor/");
\define("TFISH_UPLOADS_PATH", TFISH_ROOT_PATH . "uploads/");
\define("TFISH_MEDIA_PATH", TFISH_UPLOADS_PATH . "media/");
\define("TFISH_IMAGE_PATH", TFISH_UPLOADS_PATH . 'image/');

// Constants that make use of the trust path (which is a derivative of the physical path).
\define("TFISH_CLASS_PATH", TFISH_PATH . "class/");
\define("TFISH_DATABASE_PATH", TFISH_TRUST_PATH . "database/");
\define("TFISH_ERROR_LOG_PATH", TFISH_TRUST_PATH . "log/tuskfish_log.txt");
\define("TFISH_FORM_PATH", TFISH_PATH . "form/");
\define("TFISH_LIBRARIES_PATH", TFISH_TRUST_PATH . "libraries/");
\define("TFISH_PRIVATE_CACHE_PATH", TFISH_TRUST_PATH . "cache/");
\define("TFISH_MODULE_PATH", TFISH_TRUST_PATH . "modules/");
\define("TFISH_ONE_TIME_PAD_PATH", TFISH_TRUST_PATH . 'pads/');

// Constants that make use of the virtual (URL) path, these refer to assets accessed by URL.
\define("TFISH_ADMIN_URL", TFISH_URL . "admin/");
\define("TFISH_PREFERENCE_URL",  TFISH_URL . "preference/");
\define("TFISH_PREFERENCE_EDIT_URL", TFISH_URL . "preference/edit/");
\define("TFISH_PASSWORD_URL", TFISH_URL . "password/");
\define("TFISH_CACHE_URL", TFISH_URL . "cache/");
\define("TFISH_THEMES_URL", TFISH_URL . "themes/");
\define("TFISH_VENDOR_URL", TFISH_URL . "vendor/");
\define("TFISH_RSS_URL", TFISH_URL . "rss/");
\define("TFISH_PERMALINK_URL", TFISH_URL);
\define("TFISH_MEDIA_URL", TFISH_URL . "uploads/media/");
\define("TFISH_IMAGE_URL", TFISH_URL . "uploads/image/");

// Alias of TFISH_URL (without trailing slash) for use in teaser/description fields. Use this to
// make your content portable (if you change domain, all your links will still be valid). The
// trailing slash is omitted for ease of reading in the editor.
\define("TFISH_LINK", \rtrim(TFISH_URL, '/'));

// RSS enclosure URL - spec requires that the URL use http protocol, as https will invalidate feed.
if (\parse_url(TFISH_URL, PHP_URL_SCHEME) === 'https') {
    \define("TFISH_ENCLOSURE_URL", "http://" . \parse_url(TFISH_URL, PHP_URL_HOST)
            . "/enclosure/?id=");
} else {
    \define("TFISH_ENCLOSURE_URL", TFISH_URL . "enclosure/?id=");
}

/*
 * Preferences
 */
// Language: Specify the file name of the default language file.
\define("TFISH_LANGUAGE_PATH", TFISH_PATH . "language/");

// Numerical constants.
\define("ONE_KILOBYTE", 1024);
\define("ONE_MEGABYTE", 1048576);
\define("ONE_GIGABYTE", 1073741824);

/**
 * Autoload core Tuskfish classes. spl_autoload_register() avoids namespace clashes.
 * 
 * Note that you must check if the file exists before attempting to include it; otherwise calls
 * to additional autoloaders down the chain (eg. module-specific autoloaders) will fail.
 * 
 * @param string $classname Name of class to autoload. 
 */
function tfish_autoload(string $classname) {
    $classname = \str_replace('\\', '/', $classname);
    if (\is_file(TFISH_CLASS_PATH . $classname . '.php')) {
        include TFISH_CLASS_PATH . $classname . '.php';
    }
}
\spl_autoload_register('tfish_autoload');

// Optional Yubikey support for two-factor authentication. See the user manual for how to configure.
// If (!\defined("TFISH_YUBIKEY_ID"))
//define("TFISH_YUBIKEY_ID", "your_client_id");

// if (!\defined("TFISH_YUBIKEY_SIGNATURE_KEY"))
    //define("TFISH_YUBIKEY_SIGNATURE_KEY", "your_api_key");

// Site database name are appended here.
if (!\defined("TFISH_DATABASE")) define("TFISH_DATABASE", "/opt/lampp/htdocs/tuskfish2/trust_path/database/960547053_test.db");