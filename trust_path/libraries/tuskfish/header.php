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

// Security headers. Content-Security-Policy may need to be customised for any non-standard fonts and scripts you are using.
// \header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload'); // Enable once you have a SSL/TLS certificate installed.
/**\header(
    "Content-Security-Policy: " .
    "img-src 'self' data: maps.gstatic.com *.googleapis.com *.ggpht.com; " .
    "font-src 'self' fonts.gstatic.com; " .
    "media-src 'self' www.youtube.com; " .
    "frame-src www.youtube.com; " .
    "script-src 'self' 'unsafe-inline' maps.googleapis.com maps.gstatic.com; " .
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com; " .
    "object-src 'none'; " .
    "frame-ancestors 'none';"
);*/
\header('X-Content-Type-Options: nosniff');
\header("X-Frame-Options: DENY"); // Fallback for old browsers that don't support frame-ancestors.
\header("Referrer-Policy: strict-origin-when-cross-origin");
\header("Cross-Origin-Opener-Policy: same-origin");
\header("Cross-Origin-Resource-Policy: same-site");

// Lock charset to UTF-8.
\header('Content-Type: text/html; charset=utf-8');
\mb_internal_encoding('UTF-8');
\mb_http_output('UTF-8');

// Set error reporting levels and custom error handler.
\ini_set('display_errors', '1'); // Needs to be set to 0 for production.
\ini_set('log_errors', '1');
\error_reporting(E_ALL);

// Make core language files available.
include TFISH_DEFAULT_LANGUAGE;

// Content module block constants.
\define("TFISH_CONTENT_BLOCK_PATH", TFISH_PATH . 'class/Tfish/Content/Block/');
\define("\Tfish\Content\Block\RecentContent", TFISH_BLOCK_RECENT_CONTENT);
\define("\Tfish\Content\Block\Spotlight", TFISH_BLOCK_SPOTLIGHT);
\define("\Tfish\Content\Block\Html", TFISH_BLOCK_HTML);

// Block constants - move to config.php
\define("TFISH_ADMIN_BLOCK_URL", TFISH_URL . 'admin/blocks/');

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

// Set custom error and exception handlers.
$logger = $dice->create('\\Tfish\\Logger');
\set_error_handler([$logger, "logError"]);
\set_exception_handler([$logger, "throwException"]);

/**
 * Universal XSS output escape function for use in templates.
 *
 * Encodes entities (but does not double encode). Do not use on HTML markup,
 * only on plain text (HTML should be input filtered with HTMLPurifier).
 * Also swaps out invalid UTF-8 sequences and disallowed unicode characters,
 * with entity set and parsing rules matched to the HTML5 spec.
 *
 * @param   string $value Unescaped output.
 * @return  string XSS-escaped output safe for display.
 */
function xss($value): string
{
    $value = (string) $value;
    return \htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED | ENT_HTML5, 'UTF-8', false);
}
