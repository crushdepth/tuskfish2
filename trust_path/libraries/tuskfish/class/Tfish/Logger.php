<?php

declare(strict_types=1);

namespace Tfish;

/**
 * \Tfish\Logger class file.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 */

/**
 * Provides a custom error handler which writes to a logfile.
 *
 * Custom error handler functions such as this one should return FALSE; otherwise calls to
 * trigger_error($msg, E_USER_ERROR) will not cause a script to stop execution!
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.0
 * @package     core
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 */

class Logger
{
    use Traits\ValidateString;

    /**
     * Constructor.
     */
    public function __construct(){}

    /**
     * Tuskfish custom error logger class.
     *
     * Errors are logged to TFISH_ERROR_LOG_PATH (default is /trust_path/log/tuskfish_log.txt).
     *
     * @param int|null $errno The level of the error raised.
     * @param string $error The error message.
     * @param string $file Name of the file where the error occurred.
     * @param int|null $line Line number the error was raised at.
     * @return bool Returns false to halt script execution.
     */
    public function logError(
        int|null $errno = null,
        string $error = '',
        string $file = '',
        int|null $line = null
        )
    {
        $errno = isset($errno) ? $this->trimString($errno) : TFISH_ERROR_UNSPECIFIED;
        $error = !empty($error) ? $this->trimString($error) : TFISH_ERROR_UNSPECIFIED;
        $file = !empty($file) ? $this->trimString($file) : TFISH_ERROR_UNSPECIFIED;
        $line = isset($line) ? $this->trimString($line) : TFISH_ERROR_UNSPECIFIED;

        $errno = $errno ?? TFISH_ERROR_UNSPECIFIED;
        $line  = $line ?? TFISH_ERROR_UNSPECIFIED;
        $error = $error !== '' ? $this->trimString($error) : TFISH_ERROR_UNSPECIFIED;
        $file  = $file !== '' ? $this->trimString($file) : TFISH_ERROR_UNSPECIFIED;

        $message = \date("Y-m-d, H:i:s", \time()) . ": [ERROR][$errno][$error]";
        $message .= "[$file:$line]\n";
        \error_log($message, 3, TFISH_ERROR_LOG_PATH);

        return false;
    }
}
