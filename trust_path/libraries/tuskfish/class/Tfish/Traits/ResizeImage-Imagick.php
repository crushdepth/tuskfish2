<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\ResizeImage trait file.
 *
 * Supports both GD2 (automatically) and ImageMagick V6 (requires configuration of
 * createThumbnailWithExec() below).
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.
 * @since       2.0
 * @package     core
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
     * Requires GD library.
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
            return $cachedUrl;
        }

        // Get the size. Note that:
        // $properties['mime'] holds the mimetype, eg. 'image/jpeg'.
        // $properties[0] = width, [1] = height, [2] = width = "x" height = "y" which is useful
        // for outputting size attribute.
        $properties = \getimagesize($originalPath);

        if (!$properties) {
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
        $result = $this->scaleAndCacheImage($properties, $originalPath, $cachedPath,
            $width, $height);
        if (!$result) {
            return false;
        }

        return $cachedUrl;
    }

    /**
     * Scales an image to the specified dimensions and caches it.
     *
     * This function attempts to scale and cache the image at the specified path using the preferred
     * Imagick library if available, or falls back to the GD library otherwise. The scaled image is
     * saved at the specified cache path. Returns a boolean indicating success or failure.
     *
     * @param array $properties Array of image properties as returned by getimagesize() including dimensions and MIME type.
     * @param string $originalPath The file path to the original image.
     * @param string $cachedPath The file path where the cached scaled image will be saved.
     * @param int $width The target width for the scaled image.
     * @param int $height The target height for the scaled image.
     *
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

        if (!\in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
            \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_WARNING);
            return false;
        }

        // Check if ImageMagick's `convert` is available.
        $output = [];
        $returnCode = 1;
        \exec("convert -version > /dev/null 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            // ImageMagick is available, so use it to create the thumbnail.
            return $this->createThumbnailWithExec($properties, $originalPath, $cachedPath, $width, $height);
        } else {
            // Fall back to using GD if ImageMagick is not available.
            return $this->createThumbnailWithGd($properties, $originalPath, $cachedPath, $width, $height);
        }
    }

    /**
     * Creates a resized thumbnail of an image using ImageMagick V6 via exec().
     *
     * This function resizes the image specified in the original path to the target width and height,
     * applies optional sharpening, and saves it to the cached path. For JPEG images, a default
     * compression quality is applied. Returns true on success or false if an error occurs. Only
     * one dimension (width or height) needs to be specified, the image will be scaled proportionally.
     *
     * YOU MUST CONFIGURE THE FILE PATH TO sRGB.icc BELOW! You can also adjust the level of
     * compression and sharpening in the case statement.
     *
     * @param array $properties Original image size properties as returned by getimagesize().
     * @param string $originalPath The file path to the original image.
     * @param string $cachedPath The file path where the resized image will be saved.
     * @param int $width The target width for the resized image.
     * @param int $height The target height for the resized image.
     *
     * @return bool True on successful resizing and caching, false on failure.
     */
    private function createThumbnailWithExec(
        array $properties,
        string $originalPath,
        string $cachedPath,
        int $width = 0,
        int $height = 0)
    {
        // The path to the sRGB colour profile must be configured below, for Linux it is usually:
        $profilePath = '/usr/share/color/icc/sRGB.icc';

        // Resize image while maintaining proportions.
        $resizeOption = $width . 'x' . $height;

        // Determine image format to adjust compression and sharpening
        $mime = $properties['mime'] ?? '';

        // Set thumbnail parameters for JPEG, PNG, and GIF.
        $command = 'convert ' . \escapeshellarg($originalPath);

        // Set flags depending on mime type. Colour space, compression and sharpening are handled differently.
        switch ($mime) {
            case 'image/jpeg':
                $command .= ' -profile ' . \escapeshellarg($profilePath);
                $command .= ' -thumbnail ' . \escapeshellarg($resizeOption);
                $command .= ' -sharpen 0x0.5 -quality 75';
                break;

            case 'image/png':
                $command .= ' -thumbnail ' . \escapeshellarg($resizeOption);
                $command .= ' -sharpen 0x0.5 -define png:compression-level=9';
                break;

            case 'image/gif':
                $command .= ' -colorspace sRGB';
                $command .= ' -thumbnail ' . \escapeshellarg($resizeOption);
                break;

            default:
                \trigger_error(TFISH_ERROR_ILLEGAL_TYPE, E_USER_WARNING);
                return false;
        }

        $command .= ' ' . \escapeshellarg($cachedPath);

        // Execute the command and capture the return status
        \exec($command, $output, $returnVar);

        // Log output if there is an error
        if ($returnVar !== 0) {
            \trigger_error("ImageMagick convert command failed: " . \implode("\n", $output), E_USER_WARNING);
        }
        // Return true on success (exit status 0)
        return $returnVar === 0;
    }

    /**
     * Generates thumbnails of content->image property and saves them to the image cache.
     *
     * @param array $properties Original image size properties as returned by getimagesize().
     * @param string $originalPath Path to the original image file stored on the server.
     * @param string $cachedPath Path to the scaled version of the image, stored in the image cache.
     * @param int $width Width to scale image to.
     * @param int $height Height to scale image to.
     * @return boolean True on success, false on failure.
     */
    private function createThumbnailWithGd(
        array $properties,
        string $originalPath,
        string $cachedPath,
        int $width,
        int $height
        )
    {
        // Create a blank (black) image RESOURCE of the specified size.
        $thumbnail = \imagecreatetruecolor($width, $height);

        $result = false;
        switch ($properties['mime']) {
            case "image/jpeg":
                $original = \imagecreatefromjpeg($originalPath);
                if (!$original) { \imagedestroy($thumbnail); return false; }
                \imagecopyresampled($thumbnail, $original, 0, 0, 0, 0, $width,
                        $height, $properties[0], $properties[1]);

                // Optional third quality argument 0-99, higher is better quality.
                $result = \imagejpeg($thumbnail, $cachedPath, 80);
                \imagedestroy($original);
                break;

            case "image/png":
            case "image/gif":
                if ($properties['mime'] === "image/gif") {
                    $original = \imagecreatefromgif($originalPath);
                    if (!$original) { \imagedestroy($thumbnail); return false; }
                } else {
                    $original = \imagecreatefrompng($originalPath);
                    if (!$original) { \imagedestroy($thumbnail); return false; }
                }
                /**
                 * Handle transparency
                 *
                 * The following code block (only) is a derivative of
                 * the PHP_image_resize project by Nimrod007, which is a fork of the
                 * smart_resize_image project by Maxim Chernyak. The source code is available
                 * from the link below, and it is distributed under the following license terms:
                 *
                 * Copyright © 2008 Maxim Chernyak
                 *
                 * Permission is hereby granted, free of charge, to any person obtaining a copy
                 * of this software and associated documentation files (the "Software"), to deal
                 * in the Software without restriction, including without limitation the rights
                 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
                 * copies of the Software, and to permit persons to whom the Software is
                 * furnished to do so, subject to the following conditions:
                 *
                 * The above copyright notice and this permission notice shall be included in
                 * all copies or substantial portions of the Software.
                 *
                 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
                 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
                 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
                 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
                 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
                 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
                 * THE SOFTWARE.
                 *
                 * https://github.com/Nimrod007/PHP_image_resize
                 */
                // Sets the transparent colour in the given image, using a colour identifier
                // created with imagecolorallocate().
                $transparency = \imagecolortransparent($original);
                $numberOfColours = \imagecolorstotal($original);
                if ($transparency >= 0 && $transparency < $numberOfColours) {
                    // Get the colours for an index.
                    $transparentColour = \imagecolorsforindex($original, $transparency);
                    // Allocate a colour for an image. The first call to imagecolorallocate()
                    // fills the background colour in palette-based images created using
                    // imagecreate().
                    $transparency = \imagecolorallocate(
                        $thumbnail,
                        $transparentColour['red'],
                        $transparentColour['green'],
                        $transparentColour['blue']
                    );
                    // Flood fill with the given colour starting at the given coordinate
                    // (0,0 is top left).
                    \imagefill($thumbnail, 0, 0, $transparency);
                    // Define a colour as transparent.
                    \imagecolortransparent($thumbnail, $transparency);
                }
                // Bugfix from original: Changed next block to be an independent if, instead of
                // an elseif linked to previous block. Otherwise PNG transparency doesn't work.
                if ($properties['mime'] === "image/png") {
                    // Set the blending mode for an image.
                    \imagealphablending($thumbnail, false);
                    // Allocate a colour for an image ($image, $red, $green, $blue, $alpha).
                    $colour = imagecolorallocatealpha($thumbnail, 0, 0, 0, 127);
                    // Flood fill again.
                    \imagefill($thumbnail, 0, 0, $colour);
                    // Set the flag to save full alpha channel information (as opposed to single
                    // colour transparency) when saving png images.
                    \imagesavealpha($thumbnail, true);
                }
                /**
                 * End code derived from PHP_image_resize project.
                 */
                // Copy and resize part of an image with resampling.
                \imagecopyresampled($thumbnail, $original, 0, 0, 0, 0, $width,
                        $height, $properties[0], $properties[1]);
                // Output a useable png or gif from the image resource.
                if ($properties['mime'] === "image/gif") {
                    $result = \imagegif($thumbnail, $cachedPath);
                } else {
                    // Quality is controlled through an optional third argument (0-9).
                    // 0 = no compression, 9 = max compression, 6 is a good medium.
                    // Do not use compression = 0, it creates massive file size.
                    $result = \imagepng($thumbnail, $cachedPath, 6);
                }
                \imagedestroy($original);
                break;

            // Anything else, no can do.
            default:
                return false;
        }
        if (!$result) {
            return false;
        }

        \imagedestroy($thumbnail); // Free memory.

        return true;
    }
}
