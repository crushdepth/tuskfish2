<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\ResizeImage trait file (libvips implementation).
 *
 * Drop-in alternative to the GD and ImageMagick variants of the ResizeImage trait. To use it,
 * make this the active ResizeImage.php (e.g. rename the GD/Imagick file out of the way and rename
 * this one to ResizeImage.php). The public API (cachedImage()) is identical to the other variants.
 *
 * Why libvips: it is roughly 4x faster than ImageMagick and uses about an order of magnitude less
 * memory, because it decodes images at a reduced scale on load ("shrink-on-load") and streams them
 * through a demand-driven pipeline rather than loading the whole bitmap into RAM. It is also
 * colour-managed: unlike GD it converts the source to sRGB so thumbnails do not come out muddy.
 *
 * ---------------------------------------------------------------------------------------------
 * REQUIREMENTS (configure your server / Docker image)
 * ---------------------------------------------------------------------------------------------
 * 1. Install the libvips command-line tools. On Debian/Ubuntu (e.g. the php:apache image):
 *
 *        apt-get install -y --no-install-recommends libvips-tools
 *
 *    This provides the `vipsthumbnail` and `vips` binaries (and pulls in the libvips runtime).
 *
 * 2. PHP's proc_open() must NOT be listed in `disable_functions` in php.ini. This trait runs
 *    libvips via proc_open() ONLY (never exec()/shell_exec()/system()/passthru()/popen()), so you
 *    can — and should — keep all of those other process functions disabled.
 *
 *    To check, run `php -i | grep disable_functions` (or inspect php.ini) and confirm `proc_open`
 *    is absent from the list. If it is disabled, thumbnail generation fails and the error log shows
 *    "Unable to start libvips process (is proc_open disabled?)".
 *
 * 3. No sRGB ICC profile file is required. libvips ships a built-in sRGB profile, so unlike the
 *    ImageMagick variant there is no `sRGB.icc` path to configure (and none to get wrong).
 *
 * Tune the output by editing the configuration constants at the top of the trait below.
 *
 * ---------------------------------------------------------------------------------------------
 * SECURITY
 * ---------------------------------------------------------------------------------------------
 * - No shell is ever invoked. Commands are passed to proc_open() as an ARRAY of arguments, which
 *   executes the binary directly (execvp semantics) with no /bin/sh in between, so shell command
 *   injection is impossible by construction.
 * - The destination is constrained to the public cache directory. Every output path is checked
 *   with the TraversalCheck trait AND verified, via realpath(), to resolve inside the cache root
 *   before anything is written. Thumbnails can be written nowhere else.
 * - Only whitelisted MIME types (jpeg, png, gif) are processed; anything else is rejected before
 *   a process is spawned.
 *
 * Concurrency: each thumbnail is written to a unique temporary file and then atomically renamed
 * into place (rename() is atomic on a single filesystem). Concurrent requests for the same image
 * can therefore never read or serve a half-written cache file.
 *
 * ---------------------------------------------------------------------------------------------
 * LIMITATIONS (read before deploying)
 * ---------------------------------------------------------------------------------------------
 * - GIF output is build-dependent. Writing GIF requires libvips compiled with GIF save support
 *   (cgif). Current distro packages (e.g. Debian Trixie's libvips42) include it, but older or
 *   minimal builds may not — in that case GIF thumbnails fail (logged via trigger_error) while
 *   JPEG and PNG continue to work. If you must support GIF on such a build, keep the GD or
 *   ImageMagick variant of this trait for GIFs.
 * - No resource policy. Unlike ImageMagick (which you constrain with policy.xml), libvips has no
 *   per-operation resource limit, and these calls carry no execution timeout. libvips' shrink-on-
 *   load keeps memory low for large images, but the safeguard against hostile or oversized uploads
 *   must live in your upload validation (enforce maximum dimensions and file size there).
 * - The optional sharpening pass (sharpenSigma > 0) uses `vips sharpen`. Verify the result on your
 *   own libvips build before enabling it in production; the default (0.0, disabled) relies on
 *   libvips' already-crisp downscaling.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 * @uses        trait \Tfish\Traits\TraversalCheck Restricts thumbnail output to the cache directory.
 */

/**
 * Resize and cache copies of image files to allow them to be used at different sizes in templates.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     core
 */

trait ResizeImage
{
    use TraversalCheck;

    // -----------------------------------------------------------------------------------------
    // CONFIGURATION — adjust thumbnail quality and sharpening here.
    // -----------------------------------------------------------------------------------------

    /**
     * Unsharp-mask sigma applied to JPEG and PNG thumbnails after resizing.
     *
     * libvips' thumbnail operation does not sharpen, so sharpening is a deliberate second pass via
     * `vips sharpen`. Disabled by default (0.0): libvips' downscaling is already crisp, and the
     * sharpen pass should be verified on your own libvips build before you rely on it. A value
     * around 0.5 approximates the ImageMagick variant's old `-sharpen 0x0.5`. GIFs are never
     * sharpened.
     */
    private float $sharpenSigma = 0.0;

    /** JPEG output quality, 1-100. Higher is better quality and larger files. */
    private int $jpegQuality = 80;

    /** PNG zlib compression level, 0 (none) to 9 (maximum). 9 is a good default for thumbnails. */
    private int $pngCompression = 9;

    /**
     * Resizes and caches an associated image and returns a URL to the cached copy.
     *
     * Allows arbitrary sized thumbnails to be produced from the object's image property. These are
     * saved in the cache for future lookups. Image proportions are always preserved, so if both
     * width and height are specified, the larger dimension will take precedence for resizing and
     * the other will be ignored.
     *
     * Usually, you want to produce an image of a specific width or (less commonly) height to meet
     * a template/presentation requirement.
     *
     * @param int $width Width of the cached image output.
     * @param int $height Height of the cached image output.
     * @return string|bool URL to the cached image, or false on failure.
     */
    public function cachedImage(int $width = 0, int $height = 0)
    {
        // Validate parameters; and at least one must be set.
        $width = (int)$width;
        $height = (int)$height;
        $cleanWidth = ($width > 0) ? $width : 0;
        $cleanHeight = ($height > 0) ? $height : 0;

        if (!$cleanWidth && !$cleanHeight) {
            return false;
        }

        // Check if this object actually has an associated image, and that it is readable.
        if (!$this->image || !\is_readable(TFISH_IMAGE_PATH . $this->image)) {
            return false;
        }

        // Check if a cached copy of the requested dimensions already exists in the cache and return
        // URL. CONVENTION: Thumbnail name should follow the pattern:
        // imageFileName . '-' . $width . 'x' . $height
        $filename = \pathinfo($this->image, PATHINFO_FILENAME);
        $extension = '.' . \pathinfo($this->image, PATHINFO_EXTENSION);
        $cachedPath = TFISH_PUBLIC_CACHE_PATH . $filename . '-';
        $cachedUrl = TFISH_CACHE_URL . $filename . '-';
        $originalPath = TFISH_IMAGE_PATH . $filename . $extension;

        if ($cleanWidth > $cleanHeight) {
            $cachedPath .= $cleanWidth . 'w' . $extension;
            $cachedUrl .= $cleanWidth . 'w' . $extension;
        } else {
            $cachedPath .= $cleanHeight . 'h' . $extension;
            $cachedUrl .= $cleanHeight . 'h' . $extension;
        }

        // Security check - is the cachedPath actually pointing at the cache directory? Because
        // if it isn't, then we don't want to cooperate by returning anything.
        if (\is_readable($cachedPath)) {
            return \htmlspecialchars($cachedUrl, ENT_QUOTES, 'UTF-8');
        }

        // Get the size. Note that:
        // $properties['mime'] holds the mimetype, eg. 'image/jpeg'.
        // $properties[0] = width, [1] = height, [2] = width = "x" height = "y" which is useful
        // for outputting size attribute.
        $properties = \getimagesize($originalPath);

        // Guard against corrupt/unreadable images (and division by zero below).
        if (!$properties || (int)$properties[0] < 1 || (int)$properties[1] < 1) {
            return false;
        }

        // In order to preserve proportions, need to calculate the size of the other dimension.
        if ($cleanWidth > $cleanHeight) {
            $width = $cleanWidth;
            $height = (int) (($cleanWidth / $properties[0]) * $properties[1]);
        } else {
            $width = (int) (($cleanHeight / $properties[1]) * $properties[0]);
            $height = $cleanHeight;
        }

        // Final sanity on computed dimensions.
        if ($width < 1 || $height < 1) {
            return false;
        }

        $result = $this->scaleAndCacheImage($properties, $originalPath, $cachedPath,
            $width, $height);

        if (!$result) {
            return false;
        }

        return \htmlspecialchars($cachedUrl, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Scales an image to the specified dimensions and caches it using libvips.
     *
     * Validates the MIME type and confirms that libvips is available before doing any work. The
     * destination path is constrained to the cache directory (see assertWithinCacheDir()).
     *
     * @param array $properties Array of image properties as returned by getimagesize() including dimensions and MIME type.
     * @param string $originalPath The file path to the original image.
     * @param string $cachedPath The file path where the cached scaled image will be saved.
     * @param int $width The target width for the scaled image.
     * @param int $height The target height for the scaled image.
     * @return bool True on successful scaling and caching, false on failure.
     */
    private function scaleAndCacheImage(
        array $properties,
        string $originalPath,
        string $cachedPath,
        int $width,
        int $height)
    {
        // Only accept .jpeg, .png and .gif.
        $mime = $properties['mime'] ?? '';

        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_WARNING);
            return false;
        }

        // Defence in depth: reject any traversal/null byte in the source path, and confirm the
        // destination resolves inside the cache directory. Throws on an attempted traversal.
        if ($this->hasTraversalorNullByte($originalPath)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        $this->assertWithinCacheDir($cachedPath);

        // libvips treats "[" in a filename as the start of load/save options (on BOTH the input and
        // output paths). A path containing "[" or "]" cannot be passed unambiguously, so reject it
        // rather than risk misparsing. (Tuskfish does not strip these characters from upload names.)
        if (\strpbrk($originalPath, '[]') !== false || \strpbrk($cachedPath, '[]') !== false) {
            \trigger_error(TFISH_ERROR_ILLEGAL_VALUE, E_USER_WARNING);
            return false;
        }

        // Confirm libvips is installed before attempting to use it.
        if (!$this->vipsIsAvailable()) {
            \trigger_error("libvips (vipsthumbnail) is not available; cannot generate thumbnail.",
                E_USER_WARNING);
            return false;
        }

        return $this->createThumbnailWithVips($mime, $originalPath, $cachedPath, $width, $height);
    }

    /**
     * Creates a resized thumbnail using libvips' `vipsthumbnail` (and `vips sharpen` if enabled).
     *
     * Resizing fits the image within the target box while preserving aspect ratio. The source is
     * converted to the built-in sRGB profile (`--export-profile srgb`) so colours render correctly
     * in browsers, then metadata is stripped (`strip`) — the pixels are already sRGB, so browsers,
     * which assume sRGB, display them correctly, and stripping removes EXIF (including any GPS data)
     * and shrinks the file. EXIF orientation is honoured automatically by vipsthumbnail before the
     * profile is stripped.
     *
     * When $sharpenSigma > 0, JPEG and PNG thumbnails get a second unsharp-mask pass via
     * `vips sharpen`. To avoid double JPEG compression, the resize stage writes a lossless temporary
     * libvips file (.v) which the sharpen stage reads and encodes to the final format.
     *
     * The finished thumbnail is always encoded to a UNIQUE temporary file and then atomically
     * renamed onto $cachedPath. This means concurrent requests for the same image cannot collide on
     * a shared temp name, and a reader can never see a partially written cache file (rename() is
     * atomic on a single filesystem). Temp files share $cachedPath's already-validated directory.
     *
     * @param string $mime The validated MIME type of the source image.
     * @param string $originalPath The file path to the original image.
     * @param string $cachedPath The file path where the resized image will be saved.
     * @param int $width The target width for the resized image.
     * @param int $height The target height for the resized image.
     * @return bool True on successful resizing and caching, false on failure.
     */
    private function createThumbnailWithVips(
        string $mime,
        string $originalPath,
        string $cachedPath,
        int $width,
        int $height)
    {
        // Per-format save options appended to the output filename, e.g. "out.jpg[Q=80,strip]".
        switch ($mime) {
            case 'image/jpeg':
                $saveOptions = '[Q=' . $this->jpegQuality . ',strip]';
                break;
            case 'image/png':
                $saveOptions = '[compression=' . $this->pngCompression . ',strip]';
                break;
            case 'image/gif':
            default:
                $saveOptions = '[strip]';
                break;
        }

        $size = $width . 'x' . $height;
        $sharpen = ($this->sharpenSigma > 0)
            && \in_array($mime, ['image/jpeg', 'image/png'], true);

        // Encode to a unique temp file with the SAME extension as the final file, so vips selects
        // the correct encoder. It is atomically renamed onto $cachedPath once complete.
        $extension = \pathinfo($cachedPath, PATHINFO_EXTENSION);
        $encodeTemp = $this->tempSibling($cachedPath, $extension);

        if (!$sharpen) {
            $ok = $this->runVips([
                'vipsthumbnail',
                $originalPath,
                '--size', $size,
                '--export-profile', 'srgb',
                '-o', $encodeTemp . $saveOptions,
            ]);
        } else {
            // Resize to a unique lossless intermediate, then sharpen + encode to the final-format
            // temp. This avoids double JPEG compression.
            $resizeTemp = $this->tempSibling($cachedPath, 'v');

            $resized = $this->runVips([
                'vipsthumbnail',
                $originalPath,
                '--size', $size,
                '--export-profile', 'srgb',
                '-o', $resizeTemp,
            ]);

            if (!$resized) {
                $this->unlinkIfExists($resizeTemp);
                return false;
            }

            $ok = $this->runVips([
                'vips', 'sharpen',
                $resizeTemp,
                $encodeTemp . $saveOptions,
                '--sigma', (string) $this->sharpenSigma,
            ]);

            $this->unlinkIfExists($resizeTemp);
        }

        if (!$ok) {
            $this->unlinkIfExists($encodeTemp);
            return false;
        }

        // Atomically move the finished thumbnail into place. Same directory => atomic on POSIX, and
        // overwrites any copy a concurrent request produced in the meantime (both are valid).
        if (!@\rename($encodeTemp, $cachedPath)) {
            \trigger_error("Unable to move thumbnail into the cache: " . $cachedPath,
                E_USER_WARNING);
            $this->unlinkIfExists($encodeTemp);
            return false;
        }

        return true;
    }

    /**
     * Confirms the destination path resolves inside the public cache directory.
     *
     * Combines the TraversalCheck trait (rejects raw/encoded "../" segments and null bytes) with a
     * realpath() containment check on the destination's parent directory. Because the file does not
     * exist yet, we resolve its directory rather than the file itself. Throws if the path escapes
     * the cache directory — thumbnails may be written nowhere else.
     *
     * @param string $path The intended destination file path.
     * @return void
     * @throws \InvalidArgumentException If the path contains a traversal/null byte or escapes the cache.
     */
    private function assertWithinCacheDir(string $path): void
    {
        if ($this->hasTraversalorNullByte($path)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        $cacheRoot = \realpath(TFISH_PUBLIC_CACHE_PATH);
        $targetDir = \realpath(\dirname($path));

        if ($cacheRoot === false || $targetDir === false) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }

        // Normalise with a trailing separator so "/cacheFOO" cannot masquerade as "/cache".
        $cacheRoot .= DIRECTORY_SEPARATOR;
        $targetDir .= DIRECTORY_SEPARATOR;

        if (!\str_starts_with($targetDir, $cacheRoot)) {
            throw new \InvalidArgumentException(TFISH_ERROR_TRAVERSAL_OR_NULL_BYTE);
        }
    }

    /**
     * Checks once (per request) whether the libvips command-line tools are installed.
     *
     * @return bool True if `vipsthumbnail` responds to --version, otherwise false.
     */
    private function vipsIsAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $available = $this->runVips(['vipsthumbnail', '--version']);
        }

        return $available;
    }

    /**
     * Runs a libvips command directly, with NO shell, capturing its exit status.
     *
     * The command is passed to proc_open() as an array, so the binary is executed directly without
     * /bin/sh. There is therefore no shell metacharacter interpretation and no command-injection
     * surface. stdout/stderr are captured; stderr is logged on failure.
     *
     * @param array $command Program name followed by its arguments, e.g. ['vipsthumbnail', $src, ...].
     * @return bool True if the command exited with status 0, otherwise false.
     */
    private function runVips(array $command): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'], // stdin (unused)
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $pipes = [];

        $process = \proc_open($command, $descriptors, $pipes);

        if (!\is_resource($process)) {
            \trigger_error("Unable to start libvips process (is proc_open disabled?).",
                E_USER_WARNING);
            return false;
        }

        \fclose($pipes[0]);
        \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        if ($exitCode !== 0) {
            \trigger_error("libvips command failed: " . \trim((string) $stderr), E_USER_WARNING);
            return false;
        }

        return true;
    }

    /**
     * Builds a unique, collision-resistant temporary path beside the final cache file.
     *
     * The temp lives in the same (already validated) cache directory as $finalPath, so the atomic
     * rename onto $finalPath stays within one filesystem. A random component guarantees concurrent
     * requests for the same image use distinct temp files. The requested extension is appended so
     * libvips selects the correct encoder from the filename.
     *
     * @param string $finalPath The eventual destination path (inside the cache directory).
     * @param string $extension Extension the temp file should carry (e.g. 'jpg', 'png', 'v').
     * @return string A unique temporary file path in the same directory as $finalPath.
     */
    private function tempSibling(string $finalPath, string $extension): string
    {
        return $finalPath . '.' . \bin2hex(\random_bytes(8)) . '.' . $extension;
    }

    /**
     * Deletes a file if it exists, used to clean up the intermediate temp file.
     *
     * @param string $path File to remove.
     * @return void
     */
    private function unlinkIfExists(string $path): void
    {
        if (\is_file($path)) {
            @\unlink($path);
        }
    }
}
