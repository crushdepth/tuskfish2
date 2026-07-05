<?php

declare(strict_types=1);

namespace Tfish\Bookshelf\ViewModel;

/**
 * \Tfish\Bookshelf\ViewModel\Bookshelf class file.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 */

/**
 * ViewModel for the Bookshelf module.
 *
 * Presents a hand-curated grid of book covers grouped by subject. The subject list drives both the
 * section headings and the (client-side) filter select in the template. Renders in the site's
 * default theme; the module ships its own template (modulePath) so it works with any theme that
 * lacks a bookshelf.html override. The template is styled for the CSS-grid theme family, which
 * shares the .gallery-grid vocabulary it builds on.
 *
 * @copyright   Simon Wilkinson 2024+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     Bookshelf
 * @uses        trait \Tfish\Traits\ResizeImage Builds cached, downscaled image variants + srcsets (the same resizer the content stream uses).
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @uses        trait \Tfish\Traits\Viewable Provides a standard implementation of the \Tfish\Interface\Viewable interface.
 * @var         object $model Instance of the model used to display this page.
 */
class Bookshelf implements \Tfish\Interface\Viewable
{
    use \Tfish\Traits\ResizeImage;
    use \Tfish\Traits\TraversalCheck;
    use \Tfish\Traits\ValidateString;
    use \Tfish\Traits\Viewable;

    private object $model;

    /** @var string Image path relative to TFISH_IMAGE_PATH; the property \Tfish\Traits\ResizeImage reads. Set transiently by coverImage(). */
    private string $image = '';

    /**
     * Constructor
     *
     * @param   object $model Instance of a model class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish preference class.
     */
    public function __construct(object $model, \Tfish\Entity\Preference $preference)
    {
        $this->model = $model;
        $this->pageTitle = 'Bookshelf';
        $this->theme = $preference->defaultTheme();
        $this->template = 'bookshelf';
        $this->modulePath = TFISH_BOOKSHELF_TEMPLATE_PATH;
        $this->setMetadata(['title' => 'Bookshelf']);
    }

    /** Actions. */

    /**
     * Display the bookshelf. State is set in the constructor, so this is a no-op hook that keeps
     * the ViewModel action symmetric with the rest of the framework.
     */
    public function displayBookshelf(): void
    {
    }

    /* Getters. */

    /**
     * Return the curated bookshelf sections (subject + books) for the template.
     *
     * @return array
     */
    public function sections(): array
    {
        return $this->model->sections();
    }

    /**
     * Return a slug for a subject, used as the section's filter key / anchor.
     *
     * Lower-cased, non-alphanumeric runs collapsed to a single hyphen. Stable for a given subject
     * string so the <select> option value matches its section's data-subject attribute.
     *
     * @param   string $subject Subject heading.
     * @return  string URL/DOM-safe slug.
     */
    public function slug(string $subject): string
    {
        $slug = \strtolower($this->trimString($subject));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return \trim($slug, '-');
    }

    /**
     * Build the responsive attributes for a cover image: a cached srcset plus intrinsic pixel size.
     *
     * Uses the shared \Tfish\Traits\ResizeImage resizer directly — the same one the content stream
     * calls as $content->cachedImageSrcset([...]). It offers the browser several candidate widths,
     * generated and cached on demand and never upscaled beyond the master file, so the browser
     * downloads only the smallest file sufficient for the device's slot width and pixel density (see
     * the sizes attribute in the template). It fetches ONE rung per image, and the template's
     * loading="lazy" defers off-screen covers, so a long shelf stays fast.
     *
     * The intrinsic 'width'/'height' let the template stamp width/height attributes on the <img> so
     * the browser reserves the correct box before the (lazy) cover loads — otherwise each cover pops
     * in on scroll and shifts the grid (cumulative layout shift). The CSS still renders the image at
     * width:100%/height:auto; the attributes only supply the aspect ratio used to reserve space.
     *
     * Re-export the masters larger to benefit: today's 251px-wide covers cannot honour a rung above
     * 251w, so they stay soft on wide/HiDPI tiles until replaced with higher-resolution artwork.
     *
     * Returns zeroed/empty values for placeholders (empty cover) or an unreadable/traversal path, in
     * which case the template falls back to a plain src with no dimensions.
     *
     * @param string $cover Cover path as stored by the Model (e.g. '/uploads/image/foo.jpg').
     * @return array{srcset: string, width: int, height: int}
     */
    public function coverImage(string $cover): array
    {
        $empty = ['srcset' => '', 'width' => 0, 'height' => 0];

        $cover = $this->trimString($cover);

        if ($cover === '') {
            return $empty;
        }

        // Covers live flat in uploads/image/; the resizer addresses them by bare filename, so strip
        // that web prefix (with or without a leading slash) off the stored path. The stored path is
        // in URL space, so the prefix is derived from TFISH_IMAGE_URL's path component rather than
        // hardcoded: it tracks the constant if the image directory (or install subdirectory) moves.
        $imageDir = \ltrim(\parse_url(TFISH_IMAGE_URL, PHP_URL_PATH) ?: '', '/'); // e.g. 'uploads/image/'
        $filename = \preg_replace('#^/?' . \preg_quote($imageDir, '#') . '#', '', $cover) ?? '';

        if ($filename === '' || $this->hasTraversalorNullByte(TFISH_IMAGE_PATH . $filename)) {
            return $empty;
        }

        $originalPath = TFISH_IMAGE_PATH . $filename;

        if (!\is_readable($originalPath)) {
            return $empty;
        }

        // Read the master's intrinsic dimensions so the template can reserve the exact box (no CLS).
        // The resizer reads the header again inside cachedImageSrcset(); a couple of header reads per
        // cover are trivial next to the one-off GD resample.
        $size = \getimagesize($originalPath);

        if (!$size || (int) $size[0] < 1 || (int) $size[1] < 1) {
            return $empty;
        }

        // The trait reads $this->image; set it transiently for this call. Rungs span DPR-1 and DPR-2
        // across the 3/2/1-column layout; cachedImageSrcset clamps each to the master width and
        // drops duplicates.
        $this->image = $filename;

        return [
            'srcset' => $this->cachedImageSrcset([300, 450, 600, 900]),
            'width' => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }

    /**
     * Return the web URL of a theme-supplied bookshelf.css, or null to use the bundled default.
     *
     * Mirrors \Tfish\Entity\Template::validPath() for stylesheets: the active theme may override the
     * module's styling by dropping a bookshelf.css into its own (web-served, browser-cacheable)
     * directory. When it does, this returns that URL and the template links it; when it does not,
     * this returns null and the template inlines the module's bundled default instead. On the
     * override path nothing is read from trust_path.
     *
     * $theme comes from site preferences (not request input), but it is traversal-checked anyway for
     * parity with validPath() before being used to build a filesystem path.
     *
     * @return string|null Absolute URL to the theme stylesheet, or null when no theme override exists.
     */
    public function cssHref(): ?string
    {
        $themeCss = TFISH_THEMES_PATH . $this->theme . '/bookshelf.css';

        if ($this->hasTraversalorNullByte($themeCss) || !\is_file($themeCss)) {
            return null;
        }

        return TFISH_THEMES_URL . $this->theme . '/bookshelf.css';
    }

    /**
     * Return whether a URL points off-site (and so should open in a new tab with rel=noopener).
     *
     * Any absolute http(s) URL that does not begin with this site's base URL is treated as external.
     *
     * @param   string $url Link target.
     * @return  bool True if external, false otherwise.
     */
    public function isExternal(string $url): bool
    {
        $url = $this->trimString($url);

        return \preg_match('#^https?://#i', $url) === 1 && \strncmp($url, TFISH_URL, \strlen(TFISH_URL)) !== 0;
    }
}
