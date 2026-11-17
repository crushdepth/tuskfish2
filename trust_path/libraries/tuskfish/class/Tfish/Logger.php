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

    /** Maximum size of the log file in bytes (10 MB). */
    private const MAX_LOG_SIZE = 10485760;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $dir = \dirname(TFISH_ERROR_LOG_PATH);

        if (!\is_dir($dir) || !\is_writable($dir)) {
            \error_log(TFISH_ERROR_LOG_PATH_NOT_WRITEABLE);
        }
    }

    /**
     * Tuskfish custom error logger class.
     *
     * Errors are logged to TFISH_ERROR_LOG_PATH (default is /trust_path/log/tuskfish_log.txt).
     *
     * @param int|null $errno The level of the error raised.
     * @param string $error The error message.
     * @param string $file Name of the file where the error occurred.
     * @param int|null $line Line number the error was raised at.
     * @return bool Returns false delegating error logging to the header \ini_set('log_errors', '1');.
     */
    public function logError(
        int|null $errno = null,
        string $error = '',
        string $file = '',
        int|null $line = null
        ): bool
    {
        $errno = $errno ?? TFISH_ERROR_UNSPECIFIED;
        $error = $error !== '' ? $this->sanitiseLogField($this->trimString($error)) : TFISH_ERROR_UNSPECIFIED;
        $file  = $file  !== '' ? $this->sanitiseLogField($this->trimString($file))  : TFISH_ERROR_UNSPECIFIED;
        $line  = $line  ?? TFISH_ERROR_UNSPECIFIED;

        $message = \date('Y-m-d, H:i:s') . ": [ERROR][$errno][$error][$file:$line]\n";
        $this->writeLog($message);

        return false;
    }

    /**
     * Exception handler.
     *
     * Called by \Tfish\ErrorHandler::userError()
     *
     * @param \Throwable $e Error.
     * @return void
     */
    public function throwException(\Throwable $e): void
    {
        $message  = \date('Y-m-d, H:i:s') . ': [EXCEPTION][' . \get_class($e) . '] ';
        $message .= '[' . $this->sanitiseLogField($this->trimString($e->getMessage())) . ']';
        $message .= '[' . $this->sanitiseLogField($e->getFile()) . ':' . $e->getLine() . "]\n";
        $message .= $this->sanitiseLogField($e->getTraceAsString()) . "\n";

        // Log full, fall back to a one-line summary in PHP's own error log.
        if (!$this->writeLog($message)) {
            \error_log(\strtok($message, "\n"));
        }

        if (!\headers_sent()) {
            \http_response_code(500);
        }
    }

    /**
     * Append a message to the log file, unless it has reached MAX_LOG_SIZE.
     *
     * The log is capped rather than rotated (rename() is not available on all hosts). Delete or
     * truncate the file to resume logging. is_file() avoids a filesize() warning before the log
     * file exists.
     *
     * @param string $message Message to log.
     * @return bool True if written, false if the log is full or could not be written.
     */
    private function writeLog(string $message): bool
    {
        $size = \is_file(TFISH_ERROR_LOG_PATH) ? \filesize(TFISH_ERROR_LOG_PATH) : 0;

        if ($size >= self::MAX_LOG_SIZE) {
            return false;
        }

        return \error_log($message, 3, TFISH_ERROR_LOG_PATH);
    }

}
