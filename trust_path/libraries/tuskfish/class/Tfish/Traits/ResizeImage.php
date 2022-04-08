<?php

declare(strict_types=1);

namespace Tfish\Traits;

/**
 * \Tfish\Traits\ResizeImage trait file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
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
     * @return string $url URL to the cached image.
     */
    public function cachedImage(int $width = 0, int $height = 0)
    {
        // Validate parameters; and at least one must be set.
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

        // In sort to preserve proportions, need to calculate the size of the other dimension.
        if ($cleanWidth > $cleanHeight) {
            $destinationWidth = $cleanWidth;
            $destinationHeight = (int) (($cleanWidth / $properties[0]) * $properties[1]);
        } else {
            $destinationWidth = (int) (($cleanHeight / $properties[1]) * $properties[0]);
            $destinationHeight = $cleanHeight;
        }

        $result = $this->scaleAndCacheImage($properties, $originalPath, $cachedPath,
            $destinationWidth, $destinationHeight);

        if (!$result) {
            return false;
        }

        return $cachedUrl;
    }

    /**
     * Generates thumbnails of content->image property and saves them to the image cache.
     *
     * @param array $properties Original image size properties as returned by getimagesize().
     * @param string $originalPath Path to the original image file stored on the server.
     * @param string $cachedPath Path to the scaled version of the image, stored in the image cache.
     * @param int $destinationWidth Width to scale image to.
     * @param int $destinationHeight Height to scale image to.
     * @return boolean True on success, false on failure.
     */
    private function scaleAndCacheImage(
        array $properties,
        string $originalPath,
        string $cachedPath,
        int $destinationWidth,
        int $destinationHeight
        )
    {
        // Create a blank (black) image RESOURCE of the specified size.
        $thumbnail = \imagecreatetruecolor($destinationWidth, $destinationHeight);

        $result = false;
        switch ($properties['mime']) {
            case "image/jpeg":
                $original = \imagecreatefromjpeg($originalPath);
                \imagecopyresampled($thumbnail, $original, 0, 0, 0, 0, $destinationWidth,
                        $destinationHeight, $properties[0], $properties[1]);

                // Optional third quality argument 0-99, higher is better quality.
                $result = \imagejpeg($thumbnail, $cachedPath, 80);
                break;

            case "image/png":
            case "image/gif":
                if ($properties['mime'] === "image/gif") {
                    $original = \imagecreatefromgif($originalPath);
                } else {
                    $original = \imagecreatefrompng($originalPath);
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
                \imagecopyresampled($thumbnail, $original, 0, 0, 0, 0, $destinationWidth,
                        $destinationHeight, $properties[0], $properties[1]);
                // Output a useable png or gif from the image resource.
                if ($properties['mime'] === "image/gif") {
                    $result = \imagegif($thumbnail, $cachedPath);
                } else {
                    // Quality is controlled through an optional third argument (0-9, lower is
                    // better).
                    $result = \imagepng($thumbnail, $cachedPath, 0);
                }
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
