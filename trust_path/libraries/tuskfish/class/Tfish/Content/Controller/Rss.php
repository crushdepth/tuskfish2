<?php

declare(strict_types=1);

namespace Tfish\Content\Controller;

/**
 * \Tfish\Content\Controller\Rss class file.
 * 
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Generates route objects relevant to incoming requests.
 * 
 * A static routing table is used to return route objects configured to instantiate the relevant
 * view, model, viewModel and controller components.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         object $model Classname of the model used to display this page.
 * @var         object $viewModel Classname of the viewModel used to display this page.
 */

class Rss
{
    use \Tfish\Traits\ValidateString;

    private $model;
    private $viewModel;

    /**
     * Constructor.
     * 
     * @param   object $model Instance of a model class.
     * @param   object $viewModel Instance of a viewModel class.
     */
    public function __construct($model, $viewModel)
    {
        $this->model = $model;
        $this->viewModel = $viewModel;
    }

    /** Actions. */

    /**
     * Display RSS feed, optionally for tag or collection.
     * 
     * @return  array Cache parameters used to locate a cached copy of the feed.
     */
    public function display(): array
    {
        $cacheParams = ['lang' => $_SESSION['lang']];

        // RSS feed for a tag.
        $tag = (int) ($_GET['tag'] ?? 0);

        if ($tag > 0) {
            $this->viewModel->setTag($tag);
            $this->viewModel->listContentForTag();
            $cacheParams['tag'] = $tag;

            return $cacheParams;
        }

        // RSS feed for a collection.
        $id = (int) ($_GET['id'] ?? 0);

        if ($id > 0) {
            $this->viewModel->setCollection($id);
            $cacheParams['id'] = $id;
        }

        $this->viewModel->listContent();

        return $cacheParams;
    }
}
