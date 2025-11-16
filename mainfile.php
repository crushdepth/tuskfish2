<?php

declare(strict_types=1);

/**
 * Tuskfish mainfile script.
 *
 * Access trust path, DB credentials and read preferences. Must be included in ALL pages.
 *
 * @copyright	Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license		https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since		1.0
 * @package		core
 */
/**
 * Example paths (yours may vary):
 *
 * TFISH_ROOT_PATH is the file path to your web root, eg: /home/youraccount/public_html/
 * TFISH_TRUST_PATH is the file path to your trust_path directory eg.: /home/youraccount/trust_path/
 * TFISH_URL is simply your domain with a trailing slash, eg: https://yourdomain.com/
 * Docker containers: The root/trust paths must reflect their location in the container, not the host!
 *
 * When done, you MUST set the access permissions for this file (CHMOD) to 0400.
 */

////////// You must configure the following paths //////////
\define("TFISH_ROOT_PATH", "");
\define("TFISH_TRUST_PATH", "");
\define("TFISH_URL", "");
//////////////////// End configuration /////////////////////

\define("TFISH_PATH", TFISH_TRUST_PATH . "libraries/tuskfish/");
\define("TFISH_CONFIGURATION_PATH", TFISH_TRUST_PATH . "configuration/config.php");

require_once TFISH_CONFIGURATION_PATH;
