<?php

declare(strict_types=1);

namespace Tfish;

/**
 * Tuskfish core language constants (English).
 *
 * Translate this file to convert Tuskfish to another language. To actually use a translated language
 * file, edit /trust_path/masterfile.php and change the TFISH_DEFAULT_LANGUAGE constant to point at
 * your translated language file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     language
 */
/** First things. */
\define("TUSKFISH_CMS", "Tuskfish CMS");

/** System wide and generic constants. */
\define("TFISH_ID", "ID");
\define("TFISH_TYPE", "Type");
\define("TFISH_TEMPLATE", "Template");
\define("TFISH_TITLE", "Title");
\define("TFISH_TEASER", "Teaser");
\define("TFISH_DESCRIPTION", "Description");
\define("TFISH_DATE", "Date");
\define("TFISH_ONLINE_STATUS", "Status");
\define("TFISH_ONLINE", "Online");
\define("TFISH_OFFLINE", "Offline");
\define("TFISH_TAGS", "Tags");
\define("TFISH_SUBMISSION_TIME", "Submitted");
\define("TFISH_LAST_UPDATED", "Last updated");
\define("TFISH_COUNTER", "Counter");
\define("TFISH_VIEWS", "views");
\define("TFISH_META_TITLE", "Title");
\define("TFISH_META_DESCRIPTION", "Description");
\define("TFISH_SEO", "SEO");

// Login.
\define("TFISH_LOGIN", "Login");
\define("TFISH_LOGIN_NOTED", "New Tuskfish login");
\define("TFISH_LOGIN_NOTED_MESSAGE", "A login has been noted on your account: ");
\define("TFISH_LOGOUT", "Logout");
\define("TFISH_PASSWORD", "Password");
\define("TFISH_EMAIL", "Email");
\define("TFISH_ACTION", "Action");
\define("TFISH_YOU_ARE_ALREADY_LOGGED_IN", "You are already logged in.");
\define("TFISH_YUBIKEY", "Yubikey");

// Admin.
\define("TFISH_ADMIN", "Admin");
\define("TFISH_SELECT_STATUS", "- Select status -");
\define("TFISH_SELECT_TAGS", "- Select tag -");
\define("TFISH_SELECT_TYPE", "- Select type -");
\define("TFISH_SELECT_PARENT", "- Select parent -");
\define("TFISH_SELECT_BOX_ZERO_OPTION", "---");
\define("TFISH_META_TAGS", "Meta tags");
\define("TFISH_CHANGE_PASSWORD", "Change password");
\define("TFISH_CHANGE_PASSWORD_EXPLANATION", "Please enter and confirm your new administrative "
        . "password in the form below to change it. Passwords must be at least 15 characters long.");
define("TFISH_MINIMUM_CHARACTERS", "Minimum 15 characters");
\define("TFISH_FLUSH_CACHE", "Flush cache");
\define("TFISH_DO_YOU_WANT_TO_FLUSH_CACHE", "Do you want to flush the cache?");
\define("TFISH_CONFIRM", "Are you sure?");
\define("TFISH_CACHE_WAS_FLUSHED", "Cache was flushed.");
\define("TFISH_CACHE_FLUSH_FAILED", "Cache flush failed.");
\define("TFISH_CACHE_FLUSH_FAILED_TO_UNLINK", "Cache flush failed, could not unlink file(s).");
\define("TFISH_CACHE_FLUSH_FAILED_BAD_PATH", "Cache flush failed due to bad file path(s).");
\define("TFISH_SETTINGS", "Settings");
\define("TFISH_UPDATE_SITEMAP", "Update sitemap");
\define("TFISH_DO_YOU_WANT_TO_UPDATE_SITEMAP", "Do you want to update the sitemap?");
\define("TFISH_SITEMAP_UPDATED", "Sitemap updated.");
\define("TFISH_SITEMAP_UPDATE_FAILED", "Sitemap update failed, check file permissions.");

// Gallery
\define("TFISH_IMAGE_GALLERY", "Gallery");

// Password reset.
\define("TFISH_NEW_PASSWORD", "Enter new password");
\define("TFISH_CONFIRM_PASSWORD", "Re-enter new password to confirm");
\define("TFISH_PASSWORD_MINIMUM_LENGTH_WEAKNESS", "Password must be at least 15 characters long to "
        . "resist exhaustive searches of the keyspace.");
\define("TFISH_PASSWORD_CHANGE_FAILED", "Sorry, password change failed. Please review the requirements and try again.");
\define("TFISH_PASSWORD_CHANGED_SUCCESSFULLY", "Password successfully changed.");

// Home page stream.
\define("TFISH_LATEST_POSTS", "Latest posts");

// Preferences.
\define("TFISH_PREFERENCE", "Preference");
\define("TFISH_PREFERENCES", "Preferences");
\define("TFISH_PREFERENCE_EDIT_PREFERENCES", "Edit preferences");
\define("TFISH_PREFERENCE_VALUE", "Value");
\define("TFISH_PREFERENCE_SITE_NAME", "Site name");
\define("TFISH_PREFERENCE_SITE_EMAIL", "Site email");
\define("TFISH_PREFERENCE_CLOSE_SITE", "Close site");
\define("TFISH_PREFERENCE_SERVER_TIMEZONE", "Server timezone");
\define("TFISH_PREFERENCE_SITE_TIMEZONE", "Site timezone");
\define("TFISH_PREFERENCE_MIN_SEARCH_LENGTH", "Min. search length (characters)");
\define("TFISH_PREFERENCE_SEARCH_PAGINATION", "Search pagination");
\define("TFISH_PREFERENCE_ADMIN_PAGINATION", "Admin-side pagination");
\define("TFISH_PREFERENCE_GALLERY_PAGINATION", "Gallery pagination");
\define("TFISH_PREFERENCE_RSS_POSTS", "RSS posts in feed");
\define("TFISH_PREFERENCE_MINIMUM_VIEWS", "Minimum views to display counter");
\define("TFISH_PREFERENCE_SESSION_NAME", "Session name");
\define("TFISH_PREFERENCE_DEFAULT_LANGUAGE", "Default language");
\define("TFISH_PREFERENCE_DATE_FORMAT",
        "<a href=\"http://php.net/manual/en/function.date.php\">Date format</a>");
\define("TFISH_PREFERENCE_PAGINATION_ELEMENTS", "Max. pagination elements");
\define("TFISH_PREFERENCE_USER_PAGINATION", "User-side pagination");
\define("TFISH_PREFERENCE_SITE_DESCRIPTION", "Site description");
\define("TFISH_PREFERENCE_SITE_AUTHOR", "Site author / publisher");
\define("TFISH_PREFERENCE_SITE_COPYRIGHT", "Site copyright");
\define("TFISH_PREFERENCE_ENABLE_CACHE", "Enable cache");
\define("TFISH_PREFERENCE_CACHE_LIFE", "Cache life (seconds)");
\define("TFISH_PREFERENCE_SESSION_LIFE", "Session life (minutes)");
\define("TFISH_PREFERENCE_MAPS_API_KEY", "Google Maps API key");

// Users
\define("TFISH_USERS", "Users");
\define("TFISH_USER_ADD", "Add user");
\define("TFISH_EDIT_USER", "Edit user");
\define("TFISH_USER_RESET_PASSWORD", "Reset password");
\define("TFISH_USER_GROUP", "Group");
\define("TFISH_USER_YUBIKEY", "Yubikey ID");
\define("TFISH_USER_LOGIN_ERRORS", "Login errors");
\define("TFISH_USER_SUPER_USER", "Admin");
\define("TFISH_USER_EDITOR", "Editor");
\define("TFISH_YUBIKEY_ID", "Yubikey ID");
\define("TFISH_YUBIKEY_FIRST_12_CHARACTERS", "First 12 characters of Yubikey output (alpha only)");
\define("TFISH_USER_PRIVILEGES", "Privileges");
\define("TFISH_USER_ACTIVE", "Active");
\define("TFISH_USER_SUSPENDED", "Suspended");

// Search.
\define("TFISH_SEARCH", "Search");
\define("TFISH_ADMIN_SEARCH", "Admin search");
\define("TFISH_KEYWORDS", "Keywords");
\define("TFISH_SEARCH_ENTER_TERMS", "Enter search terms");
\define("TFISH_SEARCH_ALL", "All (AND)");
\define("TFISH_SEARCH_ANY", "Any (OR)");
\define("TFISH_SEARCH_EXACT", "Exact match");
\define("TFISH_SEARCH_NO_RESULTS", "No results.");
\define("TFISH_SEARCH_RESULTS", "result(s)");

// RSS.
\define("TFISH_RSS", "RSS");

// Pagination controls.
\define("TFISH_PAGINATION_FIRST", "First");
\define("TFISH_PAGINATION_LAST", "Last");

// Utilities
\define("TFISH_STATE", "State");

// Base intellectual property licenses.
\define("TFISH_RIGHTS_COPYRIGHT", "Copyright, all rights reserved.");
\define("TFISH_RIGHTS_ATTRIBUTION", "Creative Commons Attribution.");
\define("TFISH_RIGHTS_ATTRIBUTION_SHARE_ALIKE", "Creative Commons Attribution-ShareAlike.");
\define("TFISH_RIGHTS_ATTRIBUTION_NO_DERIVS", "Creative Commons Attribution-NoDerivs");
\define("TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL", "Creative Commons Attribution-NonCommercial.");
\define("TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL_SHARE_ALIKE", "Creative Commons "
        . "Attribution-NonCommercial-ShareAlike.");
\define("TFISH_RIGHTS_ATTRIBUTION_NON_COMMERCIAL_NO_DERIVS", "Creative Commons "
        . "Attribution-NonCommercial-NoDerivs.");
\define("TFISH_RIGHTS_GPL2", "GNU General Public License Version 2.");
\define("TFISH_RIGHTS_GPL3", "GNU General Public License Version 3.");
\define("TFISH_RIGHTS_PUBLIC_DOMAIN", "Public domain.");

// Actions and confirmation messages.
\define("TFISH_ADD", "Add");
\define("TFISH_EDIT", "Edit");
\define("TFISH_BACK", "Back");
\define("TFISH_SUBMIT", "Submit");
\define("TFISH_UPDATE", "Update");
\define("TFISH_CANCEL", "Cancel");
\define("TFISH_DO_YOU_WANT_TO_DELETE", "Do you want to delete");
\define("TFISH_YES", "Yes");
\define("TFISH_NO", "No");
\define("TFISH_SUCCESS", "Success");
\define("TFISH_FAILED", "Failed");
\define("TFISH_OBJECT_WAS_INSERTED", "The object was successfully inserted.");
\define("TFISH_OBJECT_INSERTION_FAILED", "Object insertion failed.");
\define("TFISH_OBJECT_WAS_DELETED", "The object was successfully deleted.");
\define("TFISH_OBJECT_DELETION_FAILED", "Object deletion failed");
\define("TFISH_OBJECT_WAS_UPDATED", "The object was successfully updated.");
\define("TFISH_OBJECT_UPDATE_FAILED", "Object update failed.");
\define("TFISH_PREFERENCES_WERE_UPDATED", "Preferences were successfully updated.");
\define("TFISH_PREFERENCES_UPDATE_FAILED", "Preference update failed.");

// ERROR MESSAGES.
\define("TFISH_ERROR", "Oops...");
\define("TFISH_ERROR_SORRY_PAGE_DOES_NOT_EXIST", "Sorry, the page you requested does not exist.");
\define("TFISH_ERROR_NO_SUCH_OBJECT", "Object does not exist.");
\define("TFISH_ERROR_NO_RESULT", "Database query did not return a statement; query failed.");
\define("TFISH_ERROR_NOT_ALPHA", "Illegal characters: Non-alpha.");
\define("TFISH_ERROR_NOT_ALNUM", "Illegal characters: Non-alnum.");
\define("TFISH_ERROR_NOT_ALNUMUNDER", "Illegal characters: Non-alnumunder.");
\define("TFISH_ERROR_INSERTION_FAILED", "Insertion to the database failed.");
\define("TFISH_ERROR_NOT_ARRAY", "Not an array.");
\define("TFISH_ERROR_NOT_ARRAY_OR_EMPTY", "Not an array, or array empty.");
\define("TFISH_ERROR_REQUIRED_PROPERTY_NOT_SET", "Required object property not set.");
\define("TFISH_ERROR_COUNT_MISMATCH", "Count mismatch.");
\define("TFISH_ERROR_NOT_CRITERIA_ITEM_OBJECT", "Not a CriteriaItem object.");
\define("TFISH_ERROR_ILLEGAL_TYPE", "Illegal data type (not whitelisted).");
\define("TFISH_ERROR_ILLEGAL_TEMPLATE", "Illegal template (not whitelisted).");
\define("TFISH_ERROR_ILLEGAL_VALUE", "Illegal value (not whitelisted).");
\define("TFISH_ERROR_NOT_INT", "Not an integer, or integer range violation.");
\define("TFISH_ERROR_NOT_EMAIL", "Not a valid email.");
\define("TFISH_ERROR_NOT_URL", "Not a valid URL.");
\define("TFISH_ERROR_ILLEGAL_MIMETYPE", "Illegal mimetype.");
\define("TFISH_ERROR_NO_STATEMENT", "No statement object.");
\define("TFISH_ERROR_REQUIRED_PARAMETER_NOT_SET", "Required parameter not set.");
\define("TFISH_ERROR_FILE_UPLOAD_FAILED", "File upload failed.");
\define("TFISH_ERROR_FAILED_TO_APPEND_FILE", "Failed to append to file.");
\define("TFISH_ERROR_FAILED_TO_DELETE_FILE", "Failed to delete file.");
\define("TFISH_ERROR_FAILED_TO_DELETE_DIRECTORY", "Failed to delete directory");
\define("TFISH_ERROR_BAD_PATH", "Bad file path.");
\define("TFISH_ERROR_NO_SUCH_CONTENT", "Sorry, this content is not available.");
\define("TFISH_ERROR_BAD_DATE_DEFAULTING_TO_TODAY", "Bad date supplied, defaulting to today.");
\define("TFISH_ERROR_ROOT_PATH_NOT_DEFINED", "TFISH_ROOT_PATH not defined.");
\define("TFISH_ERROR_CIRCULAR_PARENT_REFERENCE", "Circular reference: Content object cannot declare "
        . "self as parent.");
\define("TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE", "File path contains a traversal or null byte (illegal "
        . "value).");
\define("TFISH_ERROR_BAD_ACTION", "Bad action submitted to front controller, high probability attempted abuse.");
\define("TFISH_ERROR_UNSPECIFIED", "Unspecified error.");

// Token errors.
\define("TFISH_INVALID_TOKEN", "Invalid token error");
\define("TFISH_SORRY_INVALID_TOKEN", "Sorry, the token accompanying your request was invalid. This
    is usually caused by your session timing out, but it can be an indication of a cross-site
    request forgery. As a precaution, your request has not been processed. Please try again.");

// File upload error messages.
\define("TFISH_ERROR_UPLOAD_ERR_INI_SIZE", "Upload failed: File exceeds maximimum permitted .ini "
        . "size.");
\define("TFISH_ERROR_UPLOAD_ERR_FORM_SIZE", "Upload failed: File exceeds maximum size permitted in "
        . "form.");
\define("TFISH_ERROR_UPLOAD_ERR_PARTIAL", "Upload failed: File upload incomplete (partial).");
\define("TFISH_ERROR_UPLOAD_ERR_NO_FILE", "Upload failed: No file to upload.");
\define("TFISH_ERROR_UPLOAD_ERR_NO_TMP_DIR", "Upload failed: No temporary upload directory.");
\define("TFISH_ERROR_UPLOAD_ERR_CANT_WRITE", "Upload failed: Can't write to disk.");

// Browser compatibility error messages.
\define("TFISH_BROWSER_DOES_NOT_SUPPORT_VIDEO", "Your browser does not support the video tag.");
\define("TFISH_BROWSER_DOES_NOT_SUPPORT_AUDIO", "Your browser does not support the audio tag.");

/** Content module. */

// Supported content types
\define("TFISH_TYPE_ARTICLE", "Article");
\define("TFISH_TYPE_AUDIO", "Audio");
\define("TFISH_TYPE_BLOCK", "Block (static)");
\define("TFISH_TYPE_BLOG", "Blog post");
\define("TFISH_TYPE_COLLECTION", "Collection");
\define("TFISH_TYPE_DOWNLOAD", "Download");
\define("TFISH_TYPE_IMAGE", "Image");
\define("TFISH_TYPE_STATIC", "Static");
\define("TFISH_TYPE_TAG", "Tag");
\define("TFISH_TYPE_TRACK", "Track (GPS)");
\define("TFISH_TYPE_VIDEO", "Video");

// Base content object properties. Some reusable terms are defined in the Tuskfish language file.
\define("TFISH_CREATOR", "Author");
\define("TFISH_IMAGE", "Image");
\define("TFISH_CAPTION", "Caption");
\define("TFISH_MEDIA", "Media");
\define("TFISH_EXTERNAL_MEDIA", "External media URL (eg. Youtube share link)");
\define("TFISH_PARENT", "Parent (collection)");
\define("TFISH_LANGUAGE", "Language");
\define("TFISH_RIGHTS", "Rights");
\define("TFISH_PUBLISHER", "Publisher");

// Related and parent works.
\define("TFISH_RELATED", "Related");
\define("TFISH_IN_THIS_COLLECTION", "In this collection");

// Miscellaneous.
\define("TFISH_DOWNLOAD", "Download");
\define("TFISH_DOWNLOADS", "Downloads");
\define("TFISH_ZERO_OPTION", "---");

// Errors.
\define("TFISH_MEDIA_NOT_COMPATIBLE", "The selected media file is not compatible with the current "
        . "content type. Inline media players will not display.");
\define("TFISH_ERROR_PARENT_UPDATE_FAILED", "Attempt to update references to a non-extant collection"
        . " failed.");
