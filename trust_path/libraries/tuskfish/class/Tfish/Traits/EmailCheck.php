<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\EmailCheck trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.1
 * @since       2.1
 * @package     core
 */

 /**
 * Validate that email address conforms to specification.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.1
 * @since       2.1
 * @package     core
 */

trait EmailCheck
{
    /**
     * Validates an email address with rigorous validation of the domain part.
     *
     * Does NOT support internationalised domains.
     *
     * @param string $email The email address to validate.
     * @param bool $checkDns Whether to check for the existence of the domain via DNS records.
     * @return bool True if valid, false otherwise.
     */
    public function isEmail(string $email, bool $checkDns = false): bool
    {
        // Trim the email to remove unwanted whitespace.
        $email = trim($email);

        // Reject empty email input after trimming.
        if ($email === '') {
            return false;
        }

        // Reject email if it contains any non-ASCII characters.
        if (preg_match('/[^\x00-\x7F]/', $email)) {
            return false; // Contains non-ASCII characters.
        }

        // Define the email pattern according to RFC 5322 (simplified).
        $atom             = '[a-zA-Z0-9!#$%&\'*+/=?^_`{|}~-]+';
        $dotAtom          = $atom . '(?:\.' . $atom . ')*';
        $quotedString     = '"(?:\\[\x00-\x7F]|[^"\\])*"';
        $localPartPattern = '(?:' . $dotAtom . '|' . $quotedString . ')';

        $label          = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';
        $domainPattern  = $label . '(?:\.' . $label . ')*\.[a-zA-Z]{2,63}';

        $pattern = '/^' . $localPartPattern . '@' . $domainPattern . '$/';

        // Validate the email format using the regex.
        if (!preg_match($pattern, $email)) {
            return false; // Invalid email format.
        }

        // Split the email into local and domain parts.
        [$localPart, $domainPart] = explode('@', $email, 2);

        if (empty($localPart) || empty($domainPart)) {
            return false; // Invalid email structure.
        }

        // Ensure the local part, domain part, and entire email are within valid length limits.
        if (strlen($localPart) > 64 || strlen($domainPart) > 255 || strlen($email) > 254) {
            return false; // Exceeds length limits.
        }

        // Additional checks on the domain part.
        if ($domainPart[0] === '.' || strpos($domainPart, '..') !== false) {
            return false; // Invalid domain structure.
        }

        // Validate each domain label.
        $labels = explode('.', $domainPart);
        foreach ($labels as $label) {
            $labelLength = strlen($label);
            if ($label === '' || $labelLength < 1 || $labelLength > 63) {
                return false; // Invalid label length or empty label.
            }
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $label)) {
                return false; // Invalid label characters.
            }
            if ($label[0] === '-' || $label[$labelLength - 1] === '-') {
                return false; // Label starts or ends with a hyphen.
            }
        }

        // Check for MX or A records, but only if $checkDns is true.
        if ($checkDns && function_exists('checkdnsrr')) {
            if (!checkdnsrr($domainPart, 'MX') && !checkdnsrr($domainPart, 'A')) {
                return false; // Domain doesn't have valid DNS records.
            }
        }

        return true; // Email format and domain are valid.
    }
}
