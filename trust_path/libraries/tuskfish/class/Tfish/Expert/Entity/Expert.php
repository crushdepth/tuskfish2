<?php

declare(strict_types=1);

namespace Tfish\Expert\Entity;

/**
 * \Tfish\Expert\Entity\Expert class file.
 *
 * @copyright   Simon Wilkinson 2018+(https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.0
 * @package     experts
 */

/**
 * Represents the public profile of an expert.
 *
 * @copyright   Simon Wilkinson 2018+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 1.0
 * @since       1.0
 * @package     experts
 */

class Expert
{

    use \Tfish\Expert\Traits\Options;
    use \Tfish\Traits\EmailCheck;
    use \Tfish\Traits\Language;
    use \Tfish\Traits\Metadata;
    use \Tfish\Traits\Mimetypes;
    use \Tfish\Traits\ResizeImage;
    use \Tfish\Traits\Tag;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\UrlCheck;
    use \Tfish\Traits\ValidateString;

    private $id = 0;
    private $salutation = 0;
    private $firstName = '';
    private $midName = '';
    private $lastName = '';
    private $gender = 0;
    private $job = '';
    private $experience = '';
    private $projects = '';
    private $publications = '';
    private $businessUnit = '';
    private $organisation = '';
    private $address = '';
    private $country = 0;
    private $email = '';
    private $mobile = '';
    private $fax = '';
    private $profileUrl = '';
    private $image = '';
    private $onlineStatus = 0;
    private $submissionTime = 0;
    private $lastUpdated = 0;
    private $expiresOn = 0;
    private $template = '';
    private $module = 'expert';

    /**
     * Load properties.
     *
     * Parameters are validated by the respective setters.
     *
     * @param   array $row Data to load into properties.
     * @param   bool $convertUrlToConstant Convert the TFISH_LINK constant to a URL and vice-versa
     * to aid portability.
     */
    public function load(array $row, bool $convertUrlToConstant = true)
    {
        $this->setId((int) ($row['id'] ?? 0));
        $this->setSalutation((int) ($row['salutation'] ?? 0));
        $this->setFirstName((string) ($row['firstName'] ?? ''));
        $this->setMidName((string) ($row['midName'] ?? ''));
        $this->setLastName((string) ($row['lastName'] ?? ''));
        $this->setGender((int) ($row['gender'] ?? 0));
        $this->setJob((string) ($row['job'] ?? ''));
        $this->setExperience((string) ($row['experience'] ?? ''));
        $this->setProjects((string) ($row['projects'] ?? ''));
        $this->setPublications((string) ($row['publications'] ?? ''));
        $this->setBusinessUnit((string) ($row['businessUnit'] ?? ''));
        $this->setOrganisation((string) ($row['organisation'] ?? ''));
        $this->setAddress((string) ($row['address'] ?? ''));
        $this->setCountry((int) ($row['country'] ?? 0));
        $this->setEmail((string) ($row['email'] ?? ''));
        $this->setMobile((string) ($row['mobile'] ?? ''));
        $this->setFax((string) ($row['fax'] ?? ''));
        $this->setProfileUrl((string) ($row['profileUrl'] ?? ''));
        $this->setImage((string) ($row['image'] ?? ''));
        $this->setOnlineStatus((int) ($row['onlineStatus'] ?? 1));
        $this->setSubmissionTime((int) ($row['submissionTime'] ?? 0));
        $this->setLastUpdated((int) ($row['lastUpdated'] ?? 0));
        $this->setExpiresOn((int) ($row['expiresOn'] ?? 0));
        $this->setTemplate((string) ($row['template'] ?? ''));
        $this->setMetaTitle((string) ($row['metaTitle'] ?? ''));
        $this->setMetaDescription((string) ($row['metaDescription'] ?? ''));
        $this->setMetaSeo((string) ($row['metaSeo'] ?? ''));

        // Convert URLs back to TFISH_LINK for insertion or update, to aid portability.
        // Convert base url to TFISH_LINK (true) or TFISH_LINK to base url (false).
        if (isset($this->experience) && !empty($row['experience'])) {
            $experience = $this->convertBaseUrlToConstant($row['experience'], $convertUrlToConstant);
            $this->setExperience($experience);
        }

        if (isset($this->projects) && !empty($row['projects'])) {
            $projects = $this->convertBaseUrlToConstant($row['projects'], $convertUrlToConstant);
            $this->setProjects($projects);
        }

        if (isset($this->publications) && !empty($row['publications'])) {
            $publications = $this->convertBaseUrlToConstant($row['publications'], $convertUrlToConstant);
            $this->setPublications($publications);
        }
    }

    /** Getters and setters **/

    /**
     * Return ID.
     *
     * @return int
     */
    public function id(): int
    {
        return (int) $this->id;
    }

    /**
     * Set ID
     *
     * @param   int $id ID of content object.
     */
    public function setId(int $id)
    {
        if ($id < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->id = $id;
    }

    /**
     * Return salutation.
     *
     * @return int
     */
    public function salutation(): int
    {
        return (int) $this->salutation;
    }

    /**
     * Set Salutation
     *
     * @param   int $salutation Salutation of expert.
     */
    public function setSalutation(int $salutation)
    {
        if (!\array_key_exists($salutation, $this->salutationList())) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->salutation = $salutation;
    }

    /**
     * Return first name.
     *
     * @return string
     */
    public function firstName(): string
    {
        return $this->firstName;
    }

    /**
     * Set first name.
     *
     * @param string $name
     * @return void
     */
    public function setFirstName(string $name)
    {
        $this->firstName = $this->trimString($name);
    }

    /**
     * Return mid name.
     *
     * @return string
     */
    public function midName(): string
    {
        return $this->midName;
    }

    /**
     * Set middle name.
     *
     * @param string $name
     * @return void
     */
    public function setMidName(string $name)
    {
        $this->midName = $this->trimString($name);
    }

    /**
     * Return last name.
     *
     * @return string
     */
    public function LastName(): string
    {
        return $this->lastName;
    }

    /**
     * set last name.
     *
     * @param string $name
     * @return void
     */
    public function setLastName(string $name)
    {
        $this->LastName = $this->trimString($name);
    }

    /**
     * Return gender.
     *
     * @return int
     */
    public function gender(): int
    {
        return (int) $this->gender;
    }

    /**
     * Set gender
     *
     * @param   int $gender Gender of expert.
     */
    public function setGender(int $gender)
    {
        if (!\array_key_exists($gender, $this->genderList())) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->gender = $gender;
    }

    /**
     * Return job.
     *
     * @return string
     */
    public function job(): string
    {
        return $this->job;
    }

    /**
     * Set Job.
     *
     * @param string $job
     * @return void
     */
    public function setJob(string $job)
    {
        $this->job = $this->trimString($job);
    }

    /**
     * Return experience.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function experience(): string
    {
        return $this->experience;
    }

    /**
     * Return experience with TFISH_LINK constant coverted to URL.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function experienceForDisplay(): string
    {
        $experience = \str_replace('TFISH_LINK', TFISH_LINK, $this->experience);
        return $experience;
    }

    /**
     * Set experience.
     *
     * @param string $experience HTML experience.
     * @return void
     */
    public function setExperience(string $experience)
    {
        $experience = $this->trimString($experience);
    }

    /**
     * Return projects.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function projects(): string
    {
        return $this->projects;
    }

    /**
     * Return projects with TFISH_LINK constant coverted to URL.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function projectsForDisplay(): string
    {
        $projects = \str_replace('TFISH_LINK', TFISH_LINK, $this->projects);
        return $projects;
    }

    /**
     * Set projects.
     *
     * @param string $projects HTML projects.
     * @return void
     */
    public function setProjects(string $projects)
    {
        $projects = $this->trimString($projects);
    }

    /**
     * Return publications.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function publications(): string
    {
        return $this->publications;
    }

    /**
     * Return publications with TFISH_LINK constant coverted to URL.
     *
     * This is a HTML field. It has been input-validated but should not be output escaped.
     *
     * @return string
     */
    public function publicationsForDisplay(): string
    {
        $publications = \str_replace('TFISH_LINK', TFISH_LINK, $this->publications);
        return $publications;
    }

    /**
     * Set publications.
     *
     * @param string $publications HTML publications.
     * @return void
     */
    public function setPublications(string $publications)
    {
        $publications = $this->trimString($publications);
    }

    /**
     * Return business unit.
     *
     * @return string
     */
    public function businessUnit(): string
    {
        return $this->businessUnit;
    }

    /**
     * Set business unit.
     *
     * @param string $businessUnit
     * @return void
     */
    public function setBusinessUnit(string $businessUnit)
    {
        $this->businessUnit = $this->trimString($businessUnit);
    }

    /**
     * Return organisation.
     *
     * @return string
     */
    public function organisation(): string
    {
        return $this->organisation;
    }

    /**
     * Set Organisation.
     *
     * @param string $organisation
     * @return void
     */
    public function setOrganisation(string $organisation)
    {
        $this->organisation = $this->trimString($organisation);
    }

    /**
     * Return address
     *
     * @return string
     */
    public function address(): string
    {
        return $this->address;
    }

    /**
     * Set address.
     *
     * @param string $address
     * @return void
     */
    public function setAddress(string $address)
    {
        $this->address = $this->trimString($address);
    }

    /**
     * Return country.
     *
     * @return int
     */
    public function country(): int
    {
        return (int) $this->country;
    }

    /**
     * Set country
     *
     * @param   int $country Country of expert.
     */
    public function setCountry(int $country)
    {
        if (!\array_key_exists($country, $this->countryList())) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->country = $country;
    }

    /**
     * Return online status.
     *
     * @return int 0 if offline, 1 if online.
     */
    public function onlineStatus(): int
    {
        return (int) $this->onlineStatus;
    }

    /**
     * Set online status.
     *
     * @param   int $status 0 for offline, 1 for online.
     */
    public function setOnlineStatus(int $status)
    {
        if ($status !== 0 && $status !== 1) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->onlineStatus = $status;
    }

    /**
     * Return submission time.
     *
     * @return int Timestamp.
     */
    public function submissionTime(): int
    {
        return (int) $this->submissionTime;
    }

    /**
     * Set submission time.
     *
     * @param   int $timestamp
     */
    public function setSubmissionTime(int $timestamp)
    {
        if ($timestamp < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->submissionTime = $timestamp;
    }

    /**
     * Return last modification time.
     *
     * @return int $timestamp
     */
    public function lastUpdated(): int
    {
        return (int) $this->lastUpdated;
    }

    /**
     * Set last updated time.
     *
     * @param   int $timestamp
     */
    public function setLastUpdated(int $timestamp)
    {
        if ($timestamp < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->lastUpdated = $timestamp;
    }

    /**
     * Return expiry date.
     *
     * Expiry date is not yet implemented.
     *
     * @return int $timestamp
     */
    public function expiresOn(): int
    {
        return (int) $this->expiresOn;
    }

    /**
     * Set expiry time.
     *
     * @param   int $timestamp
     */
    public function setExpiresOn(int $timestamp)
    {
        if ($timestamp < 0) {
            \trigger_error(TFISH_ERROR_NOT_INT, E_USER_ERROR);
        }

        $this->expiresOn = $timestamp;
    }

    /**
     * Return template
     *
     * @return string The user-side template for displaying this object.
     */
    public function template(): string
    {
        return $this->template;
    }

    /**
     * Set template
     *
     * @param string $template Should correspond to file name of template (without extension).
     * @return void
     */
    public function setTemplate(string $template)
    {
        $template = $this->trimString($template);

        if ($this->hasTraversalorNullByte($template)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
        }

        $this->template = $template;
    }

    /**
     * Return email.
     *
     * @return string
     */
    public function email(): string
    {
        return $this->email;
    }

    /**
     * Set email.
     *
     * @param string $email Email address.
     * @return void
     */
    public function setEmail(string $email)
    {
        $email = $this->trimString($email);

        if (!$this->isEmail($email)) {
            \trigger_error(TFISH_ERROR_NOT_EMAIL, E_USER_ERROR);
        }

        $this->email = $email;
    }

    /**
     * Return mobile.
     *
     * @return string
     */
    public function mobile(): string
    {
        return $this->mobile;
    }

    /**
     * Set mobile phone number.
     *
     * @param string $mobile
     * @return void
     */
    public function setMobile(string $mobile)
    {
        $this->mobile = $this->trimString($mobile);
    }

    /**
     * Return fax.
     *
     * @return string
     */
    public function fax(): string
    {
        return $this->fax;
    }

    /**
     * Set fax number.
     *
     * @param string $fax
     * @return void
     */
    public function setFax(string $fax)
    {
        $this->fax = $this->trimString($fax);
    }

    /**
     * Return profile Url.
     *
     * @return string
     */
    public function profileUrl(): string
    {
        return $this->profileUrl;
    }

    /**
     * Set URL for external profile or resume.
     *
     * @param string $url URL of external profile webpage.
     * @return void
     */
    public function setProfileUrl(string $url)
    {
        $url = $this->trimString($url);

        if (!$this->isUrl($url)) {
            \trigger_error(TFISH_ERROR_NOT_URL, E_USER_ERROR);
        }

        $this->url = $url;
    }

    /**
     * Return image name.
     *
     * @return string
     */
    public function image(): string
    {
        return $this->image;
    }

    /**
     * Set image.
     *
     * @param   string $filename Name of image file.
     */
    public function setImage(string $filename)
    {
        $filename = $this->trimString($filename);

        if ($this->hasTraversalorNullByte($filename)) {
            \trigger_error(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE, E_USER_ERROR);
            exit; // Hard stop due to high probability of abuse.
        }

        $whitelist = $this->listImageMimetypes();
        $extension = \mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION), 'UTF-8');

        if (!empty($extension) && !\array_key_exists($extension, $whitelist)) {
            $this->image = '';
            \trigger_error(TFISH_ERROR_ILLEGAL_MIMETYPE, E_USER_ERROR);
        } else {
            $this->image = $filename;
        }
    }

    /**
     * Convert the site base URL to the TFISH_LINK constant and vice versa.
     *
     * This aids site portability. The URL is stored as a constant in the database,
     * but is converted to actual URL on display. If the domain changes at some point
     * all the references to TFISH_LINK will update automatically.
     *
     * @param   string $html HTML field to search and replace.
     * @param   bool $convertToConstant
     */
    private function convertBaseUrlToConstant(string $html, bool $convertToConstant = false)
    {
        if ($convertToConstant === true) {
            $html = \str_replace(TFISH_LINK, 'TFISH_LINK', $html);
        } else {
                $html = \str_replace('TFISH_LINK', TFISH_LINK, $html);
        }

        return $html;
    }

    /**
     * Unset properties that are not stored in the database.
     *
     * @param   array $keyValues Content object as associative array.
     * @return  array Content object with non-persistent properties unset.
     */
    private function unsetNonPersistent(array $keyValues): array
    {
        unset(
            $keyValues['tags'],
            $keyValues['module']
            );

        return $keyValues;
    }

    /**
     * Return a URL (permalink) to a content object.
     *
     * @param   string $customRoute Override to customise the URL.
     * @return  string $url.
     */
    public function url(string $customRoute = ''): string
    {
        $url = empty($customRoute) ? TFISH_PERMALINK_URL : TFISH_URL;

        if (!empty($customRoute)) {
            $url .= $this->trimString($customRoute);
        }

        $url .= '?id=' . $this->id;

        if (!empty($this->metaSeo)) {
            $url .= '&amp;title=' . $this->encodeQueryString($this->metaSeo);
        }

        return $url;
    }

    /**
     * Url-encode the query string segment of a URL.
     *
     * @param   string $url Query string to encode.
     * @return  string Encoded URL.
     */
    private function encodeQueryString(string $url): string
    {
        $url = $this->trimString($url); // Trim control characters, verify UTF-8 character set.
        return \rawurlencode($url); // Encode characters to make them URL safe.
    }

    public function getName()
    {
        $salutationList = $this->salutationList();

        $name = '';

        if ($this->salutation) $name .= $salutationList[$this->salutation] . ' ';
        if ($this->firstName) $name .= $this->firstName . ' ';
        if ($this->midName) $name .= $this->midName . ' ';
        if ($this->lastName) $name .= $this->lastName;

        return $name;
    }

    /**
     * Returns full name and job title, comma seperated, XSS escaped for display.
     *
     * @return string Name and job title.
     */
    public function getNameAndJob()
    {
        $nameAndJob = '';

        $name = $this->getName();
        $job = $this->job();

        $nameAndJob .= $name;

        if ($name && $job) {
            $nameAndJob .= ', ';
        }

        $nameAndJob .= $job;

        return $nameAndJob;
    }

    /**
     * Returns the affiliation of this expert (business unit and organisation) XSS escaped for display.
     *
     * @return string Affiliation.
     */
    public function getAffiliation()
    {
        $affiliation = '';

        $businessUnit = $this->businessUnit();
        $organisation = $this->organisation();

        $affiliation = $businessUnit;

        if ($businessUnit && $organisation) {
            $affiliation .= ', ';
        }

        $affiliation .= $organisation;

        return $affiliation;
    }

    /**
     * Return the full contact details for this expert XSS escaped for display.
     *
     * Includes address, phone, fax and email.
     *
     * @return string Contact details.
     */
    public function getContactDetails()
    {
        $contactDetails = array();

        if ($this->address) {
            $contactDetails[] = nl2br($this->address);
        }

        if ($this->mobile) {
            $contactDetails[] = TFISH_EXPERTS_MOBILE . ': ' . $this->mobile; 
        }

        if ($this->fax) {
            $contactDetails[] = TFISH_EXPERTS_FAX . ': ' . $this->fax;
        }

        if ($this->email) {
            $contactDetails[] = TFISH_EXPERTS_EMAIL . ': ' . $this->email;
        }

        $contactDetails = implode('<br>', $contactDetails);

        return $contactDetails;
    }

}
