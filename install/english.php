<?php

/**
 * English language file for Tuskfish installer script.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       2.0
 * @package     installation
 */
\define("TFISH_CMS", "Tuskfish CMS");
\define("TFISH_INSTALLATION_TUSKFISH", "Installation");
\define("TFISH_INSTALLATION_GUIDE", "Please refer to the <a href=\"https://tuskfish.biz/installation\""
        . " target=\"_blank\">Installation Guide</a> for detailed instructions.");
\define("TFISH_INSTALLATION_DESCRIPTION", "Script to install the Tuskfish CMS. Delete from server "
        . "after use.");
\define("TFISH_INSTALLATION_DB_NAME", "Database name (no spaces or symbols)");
\define("TFISH_INSTALLATION_DATABASE_SUCCESS", "Database successfully created.");
\define("TFISH_INSTALLATION_DATABASE_FAILED", "Failed to create database. Please check the script "
        . "has write permission to /your_trust_path/database");
\define("TFISH_INSTALLATION_HOME_PAGE", "home page");
\define("TFISH_INSTALLATION_COMPLETE", "Installation complete!");
\define("TFISH_INSTALLATION_SECURE_YOUR_SITE", "Secure your site");
\define("TFISH_INSTALLATION_SECURITY_INSTRUCTIONS", "<ul>"
        . "<li><strong>Delete</strong> the /public_html/<strong>install/</strong> directory.</li>"
        . "<li>Set the file permissions for /public_html/<strong>mainfile.php</strong> to <strong>0400</strong>.</li>"
        . "<li>Set the file permissions for /your_trust_path/database/<strong>yourdatabase.db</strong> to "
        . "<strong>0600</strong>.</li>"
        . "<li>Set the file permissions for /your_trust_path/configuration/<strong>config.php</strong> to "
        . "<strong>0400</strong>.</li>"
        . "</ul>"
        . "<p>Please note that you may need to use the cPanel File Manager or shell access to set "
        . "file permissions to these levels, as your FTP client probably can't.</p>"
        . "<p>Then please <strong><a href='../login/'>login</a></strong> and configure your "
        . "site preferences. The <strong><a href='https://tuskfish.biz/manual/' "
        . "target='_blank'>Tuskfish User Manual</a></strong> can help you. </p>");
\define("TFISH_INSTALLATION_DIRECTORY_DELETED", "Successfully removed the installation directory.");
\define("TFISH_INSTALLATION_REMOVE_DIRECTORY", "Removal of the installation directory failed. Please "
        . "delete it manually as it can be used to overwrite your site.");
\define("TFISH_INSTALLATION_ADMIN_EMAIL", "Admin email address");
\define("TFISH_INSTALLATION_SOME_EMAIL", "youremail@somedomain.com");
\define("TFISH_INSTALLATION_ADMIN_PASSWORD", "Admin password (minimum 15 characters)");
\define("TFISH_INSTALLATION_GRC", "Get one from: https://grc.com/passwords/");
\define("TFISH_INSTALLATION_STRONG_PASSWORD", "Password is strong.");
\define("TFISH_INSTALLATION_URL", "Domain with trailing slash");
\define("TFISH_INSTALLATION_ROOT_PATH", "File path to web root");
\define("TFISH_INSTALLATION_TRUST_PATH", "File path to trust_path");
\define("TFISH_INSTALLATION_TRAILING_SLASH", "With trailing slash /");
\define("TFISH_INSTALLATION_WARNING", "Error(s)");
\define("TFISH_INSTALLATION_WEAK_PASSWORD", "This password is <strong>WEAK</strong>, please try "
        . "again. For maximum strength your password needs to incorporate the following attributes:");
\define("TFISH_PASSWORD_MINIMUM_LENGTH_WEAKNESS", "At least 15 characters long to resist exhaustive "
        . "searches of the keyspace.");
\define("TFISH_PASSWORD_LOWER_CASE_WEAKNESS", "At least one lower case letter.");
\define("TFISH_PASSWORD_UPPER_CASE_WEAKNESS", "At least one upper case letter.");
\define("TFISH_PASSWORD_NUMBERIC_WEAKNESS", "At least one number.");
\define("TFISH_PASSWORD_SYMBOLIC_WEAKNESS", "At least one non-alphanumeric character.");
\define("TFISH_WELCOME", "Welcome to Tuskfish CMS");
\define("TFISH_LOGIN", "Login");
\define("TFISH_SUBMIT", "Submit");
\define("TFISH_LANGUAGE", "Language");

// Errors
\define("TFISH_ERROR_BAD_PATH", "Bad file path.");
\define("TFISH_ERROR_FAILED_TO_DELETE_DIRECTORY.", "Failed to delete directory");
\define("TFISH_INSTALLATION_COMPLETE_FORM", "You must complete the installation form.");
\define("TFISH_INSTALLATION_DB_ALNUMUNDERSCORE", "Database name is restricted to <strong>alphanumeric and underscore</strong> characters.");
\define("TFISH_INSTALLATION_BAD_EMAIL", "Bad email address.");
\define("TFISH_ERROR_FAILED_TO_APPEND_FILE", "Failed to append to file.");

// Constants used in theme, to prevent errors.
\define("TFISH_SEARCH", "Search");
\define("TFISH_RSS", "RSS");
\define("TFISH_KEYWORDS", "Keywords");
\define("TFISH_IMAGE_GALLERY", "Gallery");

// Pre-flight file path checks.
\define("TFISH_PREFLIGHT_CHECKS", "Pre-flight system checks");
\define("TFISH_PHP_VERSION_TOO_LOW", "PHP version too low, please upgrade to minimum system requirements (PHP 7.2+).");
\define("TFISH_PHP_VERSION_OK", "PHP version ok (minimum 7.2)");
\define("TFISH_PATH_TO_MAINFILE_OK", "Path to mainfile is good");
\define("TFISH_PATH_TO_MAINFILE_INVALID", "mainfile.php is missing, please check that all files were "
        . "uploaded as installation cannot proceed");
\define("TFISH_ROOT_PATH_OK", "TFISH_ROOT_PATH set");
\define("TFISH_ROOT_PATH_INVALID", "TFISH_ROOT_PATH is invalid, please set the path in mainfile.php");
\define("TFISH_TRUST_PATH_OK", "TFISH_TRUST_PATH set");
\define("TFISH_TRUST_PATH_INVALID", "TFISH_TRUST_PATH is invalid, please set the path in mainfile.php");
\define("TFISH_SYSTEM_REQUIREMENTS_MET", "System requirements met");
\define("TFISH_SYSTEM_REQUIREMENTS_NOT_MET", "System requirements failed");
\define("TFISH_EXTENSION", "extension present");
