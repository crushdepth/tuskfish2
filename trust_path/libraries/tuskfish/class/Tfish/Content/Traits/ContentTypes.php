<?php

declare(strict_types=1);

namespace Tfish\Content\Traits;

/**
 * \Tfish\Content\Traits\ContentTypes trait file.
 *
 * Provides common content type definition.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @since       1.1
 * @package     content
 */

/**
 * Provides definition of permitted content object types.
 *
 * @copyright   Simon Wilkinson 2013+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       1.1
 * @package     content
 */
trait ContentTypes
{
    /**
     * Returns a list of template names used by specific content types.
     *
     * Used to validate template selections set in jQuery function listed below. The options listed
     * here MUST be kept synchronised with those in:
     *
     * vendor/tuskfish/contentForm.js => loadTemplateOptions()
     *
     * @return  array Array of type-template key values.
     */
    public function listTemplates(): array
    {
        return [
            'TfArticle' => ['article', 'article-left', 'article-right'],
            'TfAudio' => ['audio'],
            'TfCollection' => ['collection', 'collection-compact'],
            'TfDownload' => ['download'],
            'TfImage' => ['image'],
            'TfTag' => ['tag'],
            'TfTrack' => ['track'],
            'TfStatic' => ['static', 'static-centre'],
            'TfVideo' => ['video1x1', 'video4x3', 'video16x9', 'video21x9'],
        ];
    }

    /**
     * Returns a whitelist of permitted content object types.
     *
     * Use this whitelist when dynamically instantiating content objects. If you create additional
     * types of content object (which must be descendants of the TfContentObject class) you
     * must add them to the whitelist below. Otherwise their use will be denied in many parts of
     * the Tuskfish system.
     *
     * @return array Array of whitelisted (permitted) content object types.
     */
    public function listTypes(): array
    {
        return array(
            'TfArticle' => TFISH_TYPE_ARTICLE,
            'TfAudio' => TFISH_TYPE_AUDIO,
            'TfCollection' => TFISH_TYPE_COLLECTION,
            'TfDownload' => TFISH_TYPE_DOWNLOAD,
            'TfImage' => TFISH_TYPE_IMAGE,
            'TfTag' => TFISH_TYPE_TAG,
            'TfTrack' => TFISH_TYPE_TRACK,
            'TfStatic' => TFISH_TYPE_STATIC,
            'TfVideo' => TFISH_TYPE_VIDEO,
        );
    }
}
