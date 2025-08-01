<?php

/**
 * Installation script for Tuskfish CMS.
 *
 * The installation directory should be deleted after use, otherwise someone may decide to reinstall
 * Tuskfish and take over management of your site.
 *
 * @copyright	Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license		https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since		2.0
 * @package		installation
 */

// Enable strict type declaration.
declare(strict_types=1);

// Include installation language files
include_once "./english.php";

// Check PHP version 8.3+
if (PHP_VERSION_ID < 80300) {
    echo TFISH_PHP_VERSION_TOO_LOW;
    exit;
}

// Check path to mainfile.
if (\is_readable("../mainfile.php")) {
    require_once '../mainfile.php';
} else {
    echo TFISH_PATH_TO_MAINFILE_INVALID;
    exit;
}

$logger = new \Tfish\Logger();
$fileHandler = new \Tfish\FileHandler();

$metadata = new stdClass();
$metadata->language = 'en';
$metadata->siteName = 'Tuskfish CMS';
$metadata->title = 'Tuskfish CMS';
$metadata->description = 'A cutting edge micro-CMS';
$metadata->author = '';
$metadata->copyright = '';
$metadata->robots = 'noindex,nofollow';

$template['metadata'] = $metadata;

// Set error reporting levels and custom error handler.
\ini_set('display_errors', '1');
\ini_set('log_errors', '1');
\error_reporting(E_ALL & ~E_NOTICE);
\set_error_handler([$logger, "logError"]);

$template = [];
$page = '';

/**
 * Helper function to grab the site URL and protocol during installation.
 *
 * @return string Site URL.
 */
function getUrl() {
    $url = @(!isset($_SERVER['HTTPS']) || $_SERVER["HTTPS"] != 'on') ? 'http://'
            . $_SERVER["SERVER_NAME"] : 'https://' . $_SERVER["SERVER_NAME"];
    $url .= ($_SERVER["SERVER_PORT"] != 80 && $_SERVER["SERVER_PORT"] != 443) ? ":"
            . $_SERVER["SERVER_PORT"] : "";
    $url .= '/';

    return $url;
}

// Begin buffer.
\ob_start();

// Initialise default content variable.
$content = ['output' => ''];
$template['output'] = '';

// Test and save database credentials.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    ////////////////////////////////////
    ////////// VALIDATE INPUT //////////
    ////////////////////////////////////

    // Check that form was completed.
    if (empty($_POST['dbName']) || empty($_POST['adminEmail']) || empty($_POST['adminPassword'])) {
        $template['output'] .= '<p>' . TFISH_INSTALLATION_COMPLETE_FORM . '</p>';
    }

    // Database name is restricted to alphanumeric and underscore characters only.
    $dbName = \trimString($_POST['dbName']);
    if (!\isAlnumUnderscore($dbName)) {
        $template['output'] .= '<p>' . TFISH_INSTALLATION_DB_ALNUMUNDERSCORE . '</p>';
    }

    // Admin email must conform to email specification.
    $adminEmail = \trimString($_POST['adminEmail']);
    if (!\isEmail($adminEmail)) {
        $template['output'] .= '<p>' . TFISH_INSTALLATION_BAD_EMAIL . '</p>';
    }

    // There are no restrictions on what characters you use for a password. Only only on what you
    // don't use!
    $adminPassword = \trimString($_POST['adminPassword']);

    // Check password length and quality.
    $passwordQuality = \checkPasswordStrength($adminPassword);

    if ($passwordQuality['strong'] === false) {
        $template['output'] .= '<p>' . TFISH_INSTALLATION_WEAK_PASSWORD . '</p>';
        unset($passwordQuality['strong']);
        $template['output'] .= '<ul>';

        foreach ($passwordQuality as $weakness) {
            $template['output'] .= '<li>' . $weakness . '</li>';
        }

        $template['output'] .= '</ul>';
    }

    // Report errors.
    if (!empty($template['output'])) {
        $template['output'] = '<h1 class="text-center">' . TFISH_INSTALLATION_WARNING . '</h1>'
                . $template['output'];
        \extract($template);
        \ob_start();
        include "./dbCredentialsForm.html";
        $page = \ob_get_clean();
    // All input validated, proceed to process and set up database.
    } else {
        $passwordHash = hashPassword($adminPassword);
        $fileHandler = new \Tfish\FileHandler();

        ////////////////////////////////////
        // INITIALISE THE SQLITE DATABASE //
        ////////////////////////////////////
        $database = new \Tfish\Database($logger, $fileHandler);
        $dbPath = $database->create($dbName);

        if ($dbPath && !\defined("TFISH_DATABASE")) {
            \define("TFISH_DATABASE", $dbPath);
        }

        $sql = "CREATE TABLE IF NOT EXISTS `user` (
            `id` INTEGER PRIMARY KEY,
            `adminEmail` TEXT NOT NULL UNIQUE,
            `passwordHash` TEXT NOT NULL,
            `userGroup` INTEGER NOT NULL,
            `yubikeyId` TEXT NOT NULL,
            `yubikeyId2` TEXT NOT NULL,
            `yubikeyId3` TEXT NOT NULL,
            `loginErrors` INTEGER NOT NULL,
            `onlineStatus` INTEGER NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        $sql = "CREATE TABLE IF NOT EXISTS `preference` (
            `id` INTEGER PRIMARY KEY,
            `title` TEXT NOT NULL,
            `value` TEXT NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        $sql = "CREATE TABLE IF NOT EXISTS `session` (
            `id` INTEGER PRIMARY KEY,
            `lastActive` INTEGER NOT NULL,
            `data` TEXT NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        $sql = "CREATE TABLE `content` (
            `type` TEXT NOT NULL,
            `template` TEXT NOT NULL,
            `id` INTEGER PRIMARY KEY,
            `title` TEXT NOT NULL,
            `teaser` TEXT NOT NULL,
            `description` TEXT NOT NULL,
            `media` TEXT NOT NULL,
            `format` TEXT NOT NULL,
            `fileSize` INTEGER NOT NULL,
            `externalMedia` TEXT NOT NULL,
            `creator` TEXT NOT NULL,
            `image` TEXT NOT NULL,
            `caption` TEXT NOT NULL,
            `date` TEXT NOT NULL,
            `parent` INTEGER NOT NULL,
            `language` TEXT NOT NULL,
            `rights` INTEGER NOT NULL,
            `publisher` TEXT NOT NULL,
            `inFeed` INTEGER NOT NULL,
            `onlineStatus` INTEGER NOT NULL,
            `submissionTime` INTEGER NOT NULL,
            `lastUpdated` INTEGER NOT NULL,
            `expiresOn` INTEGER NOT NULL,
            `counter` INTEGER NOT NULL,
            `metaTitle` TEXT NOT NULL,
            `metaDescription` TEXT NOT NULL,
            `metaSeo` TEXT NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        $sql = "CREATE TABLE IF NOT EXISTS `taglink` (
            `id` INTEGER PRIMARY KEY,
            `tagId` INTEGER NOT NULL,
            `contentType` TEXT NOT NULL,
            `contentId` INTEGER NOT NULL,
            `module` TEXT NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        // Insert admin user's details to database.
        $userData = [
            'adminEmail' => $adminEmail,
            'passwordHash' => $passwordHash,
            'userGroup' => 1,
            'yubikeyId' => '',
            'yubikeyId2' => '',
            'yubikeyId3' => '',
            'loginErrors' => 0,
            'onlineStatus' => 1
        ];
        $query = $database->insert('user', $userData);

        // Insert default preferences to database.
        $preferenceData = [
            ['title' => 'siteName', 'value' => 'Tuskfish CMS'],
            ['title' => 'siteDescription', 'value' => 'A cutting edge micro CMS'],
            ['title' => 'siteAuthor', 'value' => 'Tuskfish'],
            ['title' => 'siteEmail', 'value' => $adminEmail],
            ['title' => 'siteCopyright', 'value' => 'Copyright all rights reserved'],
            ['title' => 'closeSite', 'value' => '0'],
            ['title' => 'serverTimezone', 'value' => '0'],
            ['title' => 'siteTimezone', 'value' => '0'],
            ['title' => 'minSearchLength', 'value' => '3'],
            ['title' => 'searchPagination', 'value' => '20'],
            ['title' => 'userPagination', 'value' => '10'],
            ['title' => 'adminPagination', 'value' => '20'],
            ['title' => 'galleryPagination', 'value' => '20'],
            ['title' => 'collectionPagination', 'value' => '20'],
            ['title' => 'paginationElements', 'value' => '5'],
            ['title' => 'minimumViews', 'value' => '0'],
            ['title' => 'rssPosts', 'value' => '10'],
            ['title' => 'sessionName', 'value' => 'tfish'],
            ['title' => 'sessionLife', 'value' => '20'],
            ['title' => 'defaultLanguage', 'value' => 'en'],
            ['title' => 'dateFormat', 'value' => 'j F Y'],
            ['title' => 'enableCache', 'value' => '0'],
            ['title' => 'cacheLife', 'value' => '86400'],
            ['title' => 'mapsApiKey', 'value' => ''],
            ['title' => 'adminTheme', 'value' => 'admin'],
            ['title' => 'defaultTheme', 'value' => 'yeti']
        ];

        foreach ($preferenceData as $preference) {
            $database->insert('preference', $preference, 'id');
        }

        // Insert a "General" tag content object.
        $contentData = [
            "type" => "TfTag",
            "template" => 'tag',
            "title" => "General",
            "teaser" => "Default content tag.",
            "description" => "Default content tag, please edit it to something useful.",
            "media" => '',
            "format" => '',
            "fileSize" => '',
            "externalMedia" => '',
            "creator" => '',
            "image" => '',
            "caption" => '',
            "date" => \date('Y-m-d'),
            "parent" => 0,
            "language" => "en",
            "rights" => 1,
            "publisher" => '',
            "inFeed" => "1",
            "onlineStatus" => "1",
            "submissionTime" => \time(),
            "lastUpdated" => 0,
            "expiresOn" => 0,
            "counter" => "0",
            "metaTitle" => "General",
            "metaDescription" => "General information.",
            "metaSeo" => "general"];
        $query = $database->insert('content', $contentData);

        // Create an experts table - not required in public release.
        /*$expertColumns = [
            "id" => "INTEGER",
            "type" => "TEXT",
            "salutation" => "INTEGER",
            "firstName" => "TEXT",
            "midName" => "TEXT",
            "lastName" => "TEXT",
            "gender" => "INTEGER",
            "job" => "TEXT",
            "experience" => "TEXT",
            "projects" => "TEXT",
            "publications" => "TEXT",
            "businessUnit" => "TEXT",
            "organisation" => "TEXT",
            "address" => "TEXT",
            "country" => "INTEGER",
            "email" => "TEXT",
            "mobile" => "TEXT",
            "fax" => "TEXT",
            "profileLink" => "TEXT",
            "image" => "TEXT",
            "submissionTime" => "INTEGER",
            "lastUpdated" => "INTEGER",
            "expiresOn" => "INTEGER",
            "counter" => "INTEGER",
            "onlineStatus" => "INTEGER",
            "metaTitle" => "TEXT",
            "metaDescription" => "TEXT",
            "metaSeo" => "TEXT"
        ];
        $database->createTable('expert', $expertColumns, 'id');*/

        // Create a blocks table - future public release.
        $sql = "CREATE TABLE IF NOT EXISTS `block` (
            `id` INTEGER PRIMARY KEY,
            `type` TEXT NOT NULL,
            `position` TEXT NOT NULL,
            `title` TEXT NOT NULL,
            `html` TEXT NOT NULL,
            `config` TEXT NOT NULL,
            `weight` INTEGER NOT NULL,
            `template` TEXT NOT NULL,
            `onlineStatus` INTEGER NOT NULL
        );";
        $statement = $database->preparedStatement($sql);
        $statement->execute();

        // Create a blockRoute table - future public release.
        $sql = "CREATE TABLE IF NOT EXISTS `blockRoute` (
            `id` INTEGER PRIMARY KEY,
            `blockId` INTEGER NOT NULL,
            `route` TEXT NOT NULL
        );";

        $statement = $database->preparedStatement($sql);
        $statement->execute();

        // Close the database connection.
        $database->close();

        // Report on status of database creation.
        if ($dbPath && $query) {
            $template['pageTitle'] = TFISH_INSTALLATION_COMPLETE;
            $template['output'] .= '<div class="row"><div class="text-left col-8 offset-2 mt-3"><h3><i class="fas fa-exclamation-triangle text-danger"></i> ' . TFISH_INSTALLATION_SECURE_YOUR_SITE . '</h3></div></div>';
            $template['output'] .= '<div class="row"><div class="text-left col-8 offset-2">' . TFISH_INSTALLATION_SECURITY_INSTRUCTIONS . '</div></div>';
            \extract($template);
            \ob_start();
            include "./success.html";
            $page = \ob_get_clean();
        } else {
            // If database creation failed, complain and display data entry form again.
            $template['output'] .= '<p>' . TFISH_INSTALLATION_DATABASE_FAILED . '</p>';
            \extract($template);
            \ob_start();
            include "./dbCredentialsForm.html";
            $page = \ob_get_clean();
        }
    }
} else {
    /**
     * Preflight checks
     */
    $template['output'] .= '<div class="row"><div class="col-xs-6 offset-xs-3 col-lg-4 offset-md-4 text-left">';

    $requiredExtentions = ['sqlite3', 'PDO', 'pdo_sqlite', 'gd'];
    $loadedExtensions = \get_loaded_extensions();
    $presentList = '';
    $missingList = '';

    // Check PHP version 7.2+
    if (PHP_VERSION_ID < 80300) {
        $missingList = '<li><i class="fas fa-times text-danger"></i> ' . TFISH_PHP_VERSION_TOO_LOW . '</li>';
    } else {
        $presentList = '<li><i class="fas fa-check text-success"></i> ' . TFISH_PHP_VERSION_OK . '</li>';
    }

    // Check extensions.
    foreach ($requiredExtentions as $extension) {
        if (\in_array($extension, $loadedExtensions, true)) {
            $presentList .= '<li><i class="fas fa-check text-success"></i> ' . $extension . ' '
                    . TFISH_EXTENSION . '</li>';
        } else {
            $missingList .= '<li><i class="fas fa-times text-danger"></i> ' . $extension . ' '
                    . TFISH_EXTENSION . '</li>';
        }
    }

    // Check path to mainfile.
    if (\is_readable("../mainfile.php")) {
        $presentList .= '<li><i class="fas fa-check text-success"></i> ' . TFISH_PATH_TO_MAINFILE_OK . '</li>';
    }

    // Check root_path.
    if (\defined("TFISH_ROOT_PATH") && \is_readable(TFISH_ROOT_PATH)) {
        $presentList .= '<li><i class="fas fa-check text-success"></i> ' . TFISH_ROOT_PATH_OK . '</li>';
    } else {
        $missingList .= '<li><i class="fas fa-times text-danger"></i> ' . TFISH_ROOT_PATH_INVALID . '</li>';
    }

    // Check trust_path.
    if (\defined("TFISH_TRUST_PATH") && \is_readable(TFISH_TRUST_PATH)) {
        $presentList .= '<li><i class="fas fa-check text-success"></i> ' . TFISH_TRUST_PATH_OK . '</li>';
    } else {
        $missingList .= '<li><i class="fas fa-times text-danger"></i> ' . TFISH_TRUST_PATH_INVALID . '</li>';
    }

    if ($presentList) {
        $presentList = '<ul class="fa-ul">' . $presentList . '</ul>';
        $template['output'] .= '<p><b>' . TFISH_SYSTEM_REQUIREMENTS_MET . '</b></p>'
                . $presentList;
    }

    if ($missingList) {
        $missingList = '<ul class="fa-ul">' . $missingList . '</ul>';
        $template['output'] .= '<p><b>' . TFISH_SYSTEM_REQUIREMENTS_NOT_MET . '</b></p>'
                . $missingList;
    }

    $template['output'] .= '</div></div>';

    // Display data entry form.
    $template['pageTitle'] = TFISH_INSTALLATION_TUSKFISH;
    $template['rootPath'] = \realpath('../') . '/';
    \extract($template);
    \ob_start();
    $page = include "./dbCredentialsForm.html";
    $page = \ob_get_clean();
}

include TFISH_THEMES_PATH . "yeti/layout.html";
\ob_end_flush();

/**
 * Evaluates the strength of a password to resist brute force cracking.
 *
 * Issues warnings if deficiencies are found. Requires a minimum length of 15 characters.
 * Due to revision of advice on best practices most requirements have been relaxed, as user
 * behaviour tends to be counter-productive. Basically, it's up to you, the admin, to choose
 * a sane password.
 *
 * @param string $password Input password.
 * @return array Array of evaluation warnings as strings.
 */
function checkPasswordStrength(string $password): array
{
    $evaluation = ['strong' => true];

    // Length must be > 15 characters to prevent brute force search of the keyspace.
    if (\mb_strlen($password, 'UTF-8') < 15) {
        $evaluation['strong'] = false;
        $evaluation[] = TFISH_PASSWORD_MINIMUM_LENGTH_WEAKNESS;
    }

    return $evaluation;
}

/**
 * Hashes and salts a password to harden it against dictionary attacks.
 *
 * Uses the default password hashing algorithm, which wa bcrypt as of PHP 7.2, with a cost
 * of 11. If logging in is too slow, you could consider reducing this to 10 (the default value).
 * Lowering it further will weaken the security of the hash.
 *
 * @param string $password Input password.
 * @return string Password hash, incorporating algorithm and difficulty information.
 */
function hashPassword(string $password): string
{
    $options = ['cost' => 11];
    $password = \password_hash($password, PASSWORD_DEFAULT, $options);

    return $password;
}

/**
 * Check that a string is comprised solely of alphanumeric characters and underscores.
 *
 * Accented regional characters are rejected. This method is designed to be used to check
 * database identifiers or object property names.
 *
 * @param string $alnumUnderscore Input to be tested.
 * @return bool True if valid alphanumerical or underscore string, false otherwise.
 */
function isAlnumUnderscore(string $alnumUnderscore): bool
{
    if (\mb_strlen($alnumUnderscore, 'UTF-8') > 0) {
        return \preg_match('/[^a-z0-9_]/i', $alnumUnderscore) ? false : true;
    } else {
        return false;
    }
}

/**
 * Check if an email address is valid.
 *
 * Note that valid email addresses can contain database-unsafe characters such as single quotes.
 *
 * @param string $email Input to be tested.
 * @return bool True if a valid email address, otherwise false.
 */
function isEmail(string $email): bool
{
    // Trim whitespace from the email address.
    $email = trim($email);

    // Check if the email address meets minimum length requirements.
    if (strlen($email) < 3) {
        return false;
    }

    // FILTER_VALIDATE_EMAIL has some really stupid behaviour:
    // If the email is valid, it returns the email as a string (not 'true').
    // If the email is an invalid string, or does not contain '@', it returns null (not 'false')
    if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
            filter_var($email, FILTER_VALIDATE_EMAIL) !== null) {
        return true;
    }

    return false;
}

/**
 * Check if the character encoding of text is UTF-8.
 *
 * All strings received from external sources must be passed through this function, particularly
 * prior to storage in the database.
 *
 * @param string $text Input string to check.
 * @return bool True if string is UTF-8 encoded otherwise false.
 */
function isUtf8(string $text): bool
{
    return \mb_check_encoding($text, 'UTF-8');
}

/**
 * Cast to string, check UTF-8 encoding and strip trailing whitespace and control characters.
 *
 * Removes trailing whitespace and control characters (ASCII <= 32 / UTF-8 points 0-32 inclusive),
 * checks for UTF-8 character set and casts input to a string. Note that the data returned by
 * this function still requires escaping at the point of use; it is not database or XSS safe.
 *
 * As the input is cast to a string do NOT apply this function to non-string types (int, float,
 * bool, object, resource, null, array, etc).
 *
 * @param mixed $text Input to be trimmed.
 * @return string Trimmed and UTF-8 validated string.
 */
function trimString($text): string
{
    $text = (string) $text;

    if (\isUtf8($text)) {
        return \trim($text, "\x00..\x20");
    } else {
        return '';
    }
}

/**
 * Universal XSS output escape function for use in templates.
 *
 * Encodes quotes (but not double encode).
 *
 * @param   string $value Value to be XSS escaped for output.
 */
function xss($value): string
{
    $value = (string) $value;
    return \htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
}
