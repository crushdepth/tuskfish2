<?php

declare(strict_types=1);

namespace Tfish\Entity;

/**
 * \Tfish\Entity\Preference class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

/**
 * Holds Tuskfish site configuration (preference) data.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 * @uses        trait \Tfish\Traits\Language to obtain a list of available translations.
 * @uses        trait \Tfish\Traits\Timezones	Provides an array of time zones.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         string $siteName Name of website.
 * @var         string $siteDescription Meta description of website.
 * @var         string $siteAuthor Author of website.
 * @var         string $siteEmail Administrative contact email for website.
 * @var         string $siteCopyright Copyright notice.
 * @var         int $closeSite Toggle to close this site.
 * @var         int $serverTimezone Timezone of server location.
 * @var         int $siteTimezone Timezone for main audience location.
 * @var         int $minSearchLength Minimum length of search terms.
 * @var         int $searchPagination Number of search results to show on a page.
 * @var         int $userPagination Number of content objects to show on public index page.
 * @var         int $adminPagination Number of content objects to show on admin index page.
 * @var         int $galleryPagination Number of images to show in admin gallery.
 * @var         int $rssPosts Number of items to include in RSS feeds.
 * @var         int $paginationElements Number of slots to include on pagination controls.
 * @var         string $sessionName Name of session.
 * @var         int $sessionLife Expiry timer for inactive sessions (minutes).
 * @var         string $defaultLanguage Default language of site.
 * @var         string $dateFormat Format to display dates, as per PHP date() function.
 * @var         int $enableCache Enable site cache.
 * @var         int $cacheLife Expiry timer for site cache (seconds).
 * @var         string $mapsApiKey Google Maps API key (optional, if you want to use their API to generate maps.)
 */
class Preference
{
    use \Tfish\Traits\Language;
    use \Tfish\Traits\Timezones;
    use \Tfish\Traits\ValidateString;

    private \Tfish\Database $database;

    private string $siteName = ''; // global
    private string $siteDescription = ''; // viewmodel
    private string $siteAuthor = ''; // viewmodel
    private string $siteEmail = ''; // viewmodel
    private string $siteCopyright = ''; // viewmodel
    private int $closeSite = 0; // global
    private int $serverTimezone = 0; // global
    private int $siteTimezone = 0; // global
    private int $minSearchLength = 0; // model
    private int $searchPagination = 0; // viewmodel
    private int $userPagination = 0; // viewmodel
    private int $adminPagination = 0; // viewmodel
    private int $galleryPagination = 0; // viewmodel
    private int $collectionPagination = 0; // viewmodel
    private int $rssPosts = 0; // viewmodel
    private int $paginationElements = 0; // viewmodel
    private int $minimumViews = 0;
    private string $sessionName = ''; // global
    private int $sessionLife = 0; // global
    private string $defaultLanguage = ''; // ??
    private string $dateFormat = ''; // global
    private int $enableCache = 0; // global
    private int $cacheLife = 0; // global
    private string $mapsApiKey = ''; // global
    private string $adminTheme = ''; // global
    private string $defaultTheme = ''; // global

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     */
    function __construct(\Tfish\Database $database)
    {
        $this->database = $database;
        $preferences = $this->readPreferences();
        $this->load($preferences);
    }

    /**
     * Converts the preference object to an array suitable for insert/update calls to the database.
     *
     * Note that the output is not XSS escaped and should not be sent to display.
     *
     * @return array Array of object property/values.
     */
    public function getPreferencesAsArray(): array
    {
        unset($this->database);

        $preferences = [];

        foreach ($this as $key => $value) {
            $preferences[$key] = $value;
        }

        return $preferences;
    }

    /**
     * Update the preference object from an external data source (eg. form submission).
     *
     * The preference object will conduct its own internal data type validation and range checks.
     *
     * @param array $input Usually $_REQUEST data.
     */
    public function load(array $input)
    {
        $this->setSiteName($input['siteName'] ?? '');
        $this->setSiteDescription($input['siteDescription'] ?? '');
        $this->setSiteAuthor($input['siteAuthor'] ?? '');
        $this->setSiteEmail($input['siteEmail'] ?? '');
        $this->setSiteCopyright($input['siteCopyright'] ?? '');
        $this->setCloseSite((int) ($input['closeSite'] ?? 0));
        $this->setServerTimezone((int) $input['serverTimezone'] ?? '0');
        $this->setSiteTimezone((int) $input['siteTimezone'] ?? '0');
        $this->setMinSearchLength((int) ($input['minSearchLength'] ?? 3));
        $this->setSearchPagination((int) ($input['searchPagination'] ?? 20));
        $this->setUserPagination((int) ($input['userPagination'] ?? 10));
        $this->setAdminPagination((int) ($input['adminPagination'] ?? 20));
        $this->setGalleryPagination((int) ($input['galleryPagination'] ?? 20));
        $this->setCollectionPagination((int) ($input['collectionPagination'] ?? 20));
        $this->setRssPosts((int) ($input['rssPosts'] ?? 10));
        $this->setPaginationElements((int) ($input['paginationElements'] ?? 5));
        $this->setMinimumViews((int) ($input['minimumViews'] ?? 0));
        $this->setSessionName($input['sessionName'] ?? 'tfish');
        $this->setSessionLife((int) ($input['sessionLife'] ?? 20));
        $this->setDefaultLanguage($input['defaultLanguage'] ?? 'en');
        $this->setDateFormat($input['dateFormat'] ?? 'j F Y');
        $this->setEnableCache((int) ($input['enableCache'] ?? 0));
        $this->setCacheLife((int) ($input['cacheLife'] ?? 86400));
        $this->setMapsApiKey((string) $input['mapsApiKey'] ?? '');
        $this->setAdminTheme((string) $input['adminTheme'] ?? 'admin');
        $this->setDefaultTheme((string) $input['defaultTheme'] ?? 'default');
    }

    /**
     * Read out the site preferences into an array.
     *
     * @return array Array of site preferences.
     */
    public function readPreferences(): array
    {
        $preferences = [];
        $result = $this->database->select('preference');

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $preferences[$row['title']] = $row['value'];
        }

        return $preferences;
    }

    public function adminPagination(): int
    {
        return $this->adminPagination;
    }

    /**
     * Set the number of objects to display in a single admin page view.
     *
     * @param int $value Number of objects to view on a single page.
     */
    public function setAdminPagination(int $value)
    {
        if ($value < 1) \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);

        $this->adminPagination = $value;
    }

    /**
     * Returns selected admin theme.
     *
     * @return string
     */
    public function adminTheme(): string
    {
        return $this->adminTheme;
    }

    /**
     * Set admin theme.
     *
     * @param string $value Name of admin theme directory.
     * @return void
     */
    public function setAdminTheme(string $value)
    {
        $this->adminTheme = $this->trimString($value);
    }

    /**
     * Return site author
     *
     * @return string Name of the site author.
     */
    public function siteAuthor(): string
    {
        return $this->siteAuthor;
    }

    /**
     * Set the name of the site author. Used to population page meta author tag.
     *
     * @param string $value Name of the site author.
     */
    public function setSiteAuthor(string $value)
    {
        $this->siteAuthor = $this->trimString($value);
    }

    /**
     * Return cache life.
     *
     * @return int Cache life in seconds.
     */
    public function cacheLife(): int
    {
        return $this->cacheLife;
    }

    /**
     * Set life of items in cache (seconds).
     *
     * Items that expire will be rebuilt and re-written to the cache the next time the page is
     * requested.
     *
     * @param int $value Expiry timer on cached items (seconds).
     */
    public function setCacheLife(int $value)
    {
        if ($value < 0) \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);

        $this->cacheLife = $value;
    }

    /**
     * Return the value of the site closed preference.
     *
     * @return int Open (0) or closed (1).
     */
    public function closeSite(): int
    {
        return $this->closeSite;
    }

    /**
     * Open our close the site.
     *
     * @param int $value Site open (0) or closed (1).
     */
    public function setCloseSite(int $value)
    {
        if ($value !== 0 && $value !== 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->closeSite = $value;
    }

    /**
     * Return date format.
     *
     * @return string  Ttemplate as per the PHP date() function.
     */
    public function dateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * Set the date format, used to convert timestamps to human readable form.
     *
     * See the PHP manual for date formatting templates: http://php.net/manual/en/function.date.php
     *
     * @param string $value Template for formatting dates.
     */
    public function setDateFormat(string $value)
    {
        $this->dateFormat = $this->trimString($value);
    }

    /**
     * Return default language.
     *
     * @return  string Two-letter ISO language code.
     */
    public function defaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    /**
     * Set the default language for this Tuskfish installation.
     *
     * @param string $value ISO 639-1 two-letter language codes.
     */
    public function setDefaultLanguage(string $value)
    {
        $value = $this->trimString($value);

        if (!$this->isAlpha($value)) {
            \trigger_error(TFISH_ERROR_NOT_ALPHA, E_USER_ERROR);
        }

        $languageWhitelist = $this->listLanguages();

        if (!\array_key_exists($value, $languageWhitelist)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_ERROR);
        }

        $this->defaultLanguage = $value;
    }

    /**
     * Returns current default theme.
     *
     * @return string
     */
    public function defaultTheme(): string
    {
        return $this->defaultTheme;
    }

    /**
     * Set default theme.
     *
     * @param string $value Name of theme directory.
     * @return void
     */
    public function setDefaultTheme(string $value)
    {
        $this->defaultTheme = $this->trimString($value);
    }

    /**
     * Return site description.
     *
     * @return  string
     */
    public function siteDescription(): string
    {
        return $this->siteDescription;
    }

    /**
     * Set the site description. Used in meta description tag.
     *
     * @param string $value Site description.
     */
    public function setSiteDescription(string $value)
    {
        $this->siteDescription = $this->trimString($value);
    }

    /**
     * Return site email.
     *
     * @return  string
     */
    public function siteEmail(): string
    {
        return $this->siteEmail;
    }

    /**
     * Set the admin email address for the site.
     *
     * Used in RSS feeds to populate the managingEditor and webmaster tags.
     *
     * @param string $value Email address.
     */
    public function setSiteEmail(string $value)
    {
        $value = $this->trimString($value);

        if (!$this->emailIsValid($value)) {
            \trigger_error(TFISH_ERROR_NOT_EMAIL, E_USER_ERROR);
        }

        $this->siteEmail = $value;
    }

    /**
     * Validate email address meets specification.
     *
     * @return bool True if valid, false if invalid.
     */
    private function emailIsValid(string $email)
    {
        if (\mb_strlen($email, 'UTF-8') > 2) {
            return \filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        return false;
    }

    /**
     * Return enableCache.
     *
     * @return int Enabled (1) or disabled (0).
     */
    public function enableCache(): int
    {
        return $this->enableCache;
    }

    /**
     * Enable or disable the cache.
     *
     * @param int $value Enabled (1) or disabled (0).
     */
    public function setEnableCache(int $value)
    {
        if ($value !== 0 && $value !== 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->enableCache = $value;
    }

    /**
     * Return gallery pagination.
     *
     * @return int Number of objects to display on a single page view.
     */
    public function galleryPagination(): int
    {
        return $this->galleryPagination;
    }

    /**
     * Set number of objects to display on the gallery page.
     *
     * @param int $value Number of objects to display on a single page view.
     */
    public function setGalleryPagination(int $value)
    {
        if ($value < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->galleryPagination = $value;
    }

    /**
     * Return collection pagination.
     *
     * @return int Number of child objects to display on a single collection view.
     */
    public function collectionPagination(): int
    {
        return $this->collectionPagination;
    }

    /**
     * Set number of child objects to display on a collection page view.
     *
     * @param int $value Number of objects to display on a single page view.
     */
    public function setCollectionPagination(int $value)
    {
        if ($value < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->collectionPagination = $value;
    }

    public function mapsApiKey(): string
    {
        return $this->mapsApiKey;
    }

    public function setMapsApiKey(string $value)
    {
        $value = $this->trimString($value);

        $this->mapsApiKey = $value;
    }

    /**
     * Return minimum search length.
     *
     * @return  int Number of characters in a search term.
     */
    public function minSearchLength(): int
    {
        return $this->minSearchLength;
    }

    /**
     * Set the minimum length of search terms (characters).
     *
     * Search terms less than this number of characters will be discarded. It is usually best to
     * allow a minimum length of 3 characters; this allows searching for common acronyms without
     * returning massive numbers of hits.
     *
     * @param int $value Minimum number of characters.
     */
    public function setMinSearchLength(int $value)
    {
        if ($value < 3) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->minSearchLength = $value;
    }

    /**
     * Return number of elements in a pagination control.
     *
     * @return int
     */
    public function paginationElements(): int
    {
        return $this->paginationElements;
    }

    /**
     * Set the default number of page slots to display in pagination elements.
     *
     * Can be overridden manually in PaginationControl.
     *
     * @param int $value Number of page slots to display in pagination control.
     */
    public function setPaginationElements(int $value)
    {
        if ($value < 3) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->paginationElements = $value;
    }

    /**
     * Return the minimum views/downloads required to display the view/downloads counter.
     *
     * @return int
     */
    public function minimumViews(): int
    {
        return $this->minimumViews;
    }

    /**
     * Set the number of views required before the views counter of a content object is displayed.
     *
     * @param integer $value Number of views before showing counter.
     * @return void
     */
    public function setMinimumViews(int $value)
    {
        if ($value < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->minimumViews = $value;
    }

    /**
     * Return the number of items to include in a RSS feed.
     *
     * @return int
     */
    public function rssPosts(): int
    {
        return $this->rssPosts;
    }

    /**
     * Set number of items to display in RSS feeds.
     *
     * @param int $value Number of items to include in feed.
     */
    public function setRssPosts(int $value)
    {
        if ($value < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->rssPosts = $value;
    }

    /**
     * Number of items to include in a search page view.
     *
     * @return int
     */
    public function searchPagination(): int
    {
        return $this->searchPagination;
    }

    /**
     * Set number of results to display on a search page view.
     *
     * @param int $value Number of objects to display in a single page view.
     */
    public function setSearchPagination(int $value)
    {
        if ($value < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->searchPagination = $value;
    }

    /**
     * Return server timezone.
     *
     * @return  int
     */
    public function serverTimezone(): int
    {
        return (int) $this->serverTimezone;
    }

    /**
     * Set the server timezone.
     *
     * @param int $value Timezone.
     */
    public function setServerTimezone(int $value)
    {
        $this->serverTimezone = $value;
    }

    /**
     * Return session life (minutes).
     *
     * @return  int
     */
    public function sessionLife(): int
    {
        return $this->sessionLife;
    }

    /**
     * Set the life of an idle session.
     *
     * User will be logged out after being idle for this many minutes.
     *
     * @param int $value Session life (minutes).
     */
    public function setSessionLife(int $value)
    {
        if ($value < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->sessionLife = $value;
    }

    /**
     * Return session name.
     *
     * @return string
     */
    public function sessionName(): string
    {
        return $this->sessionName;
    }

    /**
     * Set the name (prefix) used to identify Tuskfish sessions.
     *
     * If you change it, use something unpredictable.
     *
     * @param string $value Session name.
     */
    public function setSessionName(string $value)
    {
        $value = $this->trimString($value);

        if (!$this->isAlnum($value)) {
            \trigger_error(TFISH_ERROR_NOT_ALNUM, E_USER_ERROR);
        }

        $this->sessionName = $value;
    }

    /**
     * Return site copyright.
     *
     * @return  string
     */
    public function siteCopyright(): string
    {
        return $this->siteCopyright;
    }

    /**
     * Set the site meta copyright.
     *
     * Used to populate the dcterms.rights meta tag in the theme. Can be overridden in the
     * theme.html file.
     *
     * @param string $value Copyright statement.
     */
    public function setSiteCopyright(string $value)
    {
        $this->siteCopyright = $this->trimString($value);
    }

    /**
     * Return site name.
     *
     * @return  string
     */
    public function siteName(): string
    {
        return $this->siteName;
    }

    /**
     * Set the title of the page.
     *
     * Used to populate the meta title tag. Usually each page / object will specify a title, but
     * if none is set this value will be used as the default. The title can be manually overriden
     * on each page using the Metadata object (see comments at the bottom of controller
     * scripts).
     *
     * @param string $value
     */
    public function setSiteName(string $value)
    {
        $this->siteName = $this->trimString($value);
    }

    /**
     * Return site timezone.
     *
     * @return string
     */
    public function siteTimezone(): int
    {
        return (int) $this->siteTimezone;
    }

    /**
     * Set the site timezone.
     *
     * This is normally the timezone for your principal target audience.
     *
     * @param int $value Timezone.
     */
    public function setSiteTimezone(int $value)
    {
        $this->siteTimezone = $value;
    }

    /**
     * Number of items to display on a single user-side page.
     *
     * @return  int
     */
    public function userPagination(): int
    {
        return $this->userPagination;
    }

    /**
     * Set the number of objects to display in a single page view on the public facing side of the
     * site.
     *
     * @param int $value Number of objects to display.
     */
    public function setUserPagination(int $value)
    {
        if ($value < 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->userPagination = $value;
    }
}
