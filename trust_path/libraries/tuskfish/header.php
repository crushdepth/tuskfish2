<?php

declare(strict_types=1);

/**
 * Tuskfish header script, must be included on every page.
 *
 * Establishes connection with database and initialises dependencies.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

// Lock charset to UTF-8.
\header('Content-Type: text/html; charset=utf-8');
\header("X-Frame-Options: DENY");
\header("Content-Security-Policy: frame-ancestors 'none'");
\mb_internal_encoding('UTF-8');
\mb_http_output('UTF-8');

// Set error reporting levels and custom error handler.
\ini_set('display_errors', '0'); // Needs to be set to 0 for production.
\ini_set('log_errors', '1');
\error_reporting(E_ALL);

// Make core language files available.
include TFISH_DEFAULT_LANGUAGE;

// Initialise dependencies via DICE dependency injection container.
$dice = new \Dice\Dice;
$rules = [
    '\\Tfish\\Logger' => ['shared' => true],
    '\\Tfish\\FileHandler' => ['shared' => true],
    '\\Tfish\\Database' => ['shared' => true],
    '\\Tfish\\CriteriaFactory' => ['shared' => true],
    '\\Tfish\\Entity\\Preference' => ['shared' => true],
    '\\Tfish\\Entity\\Metadata' => ['shared' => true],
    '\\Tfish\\Cache' => ['shared' => true],
    '\\Tfish\\Session' => ['shared' => true],
    '\\Tfish\\Route' => ['shared' => true],
    '\\Tfish\\Pagination' => ['shared' => true]
];
$dice = $dice->addRules($rules);

// Set custom error handler.
$logger = $dice->create('\\Tfish\\Logger');
\set_error_handler([$logger, "logError"]);

/**
 * Universal XSS output escape function for use in templates.
 *
 * Encodes entities (but does not double encode). Do not use on HTML markup,
 * only on plain text (HTML should be input filtered with HTMLPurifier).
 *
 * @param   string $value Unescaped output.
 * @return  string XSS-escaped output safe for display.
 */
function xss($value): string
{
    $value = (string) $value;
    return \htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);
}
