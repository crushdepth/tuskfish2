<?php

declare(strict_types=1);

namespace Tfish\Content\Model;

/**
 * \Tfish\Content\Model\Listing class file.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 */

/**
 * Model for listing content objects.
 *
 * @copyright   Simon Wilkinson 2019+ (https://tuskfish.biz)
 * @license     https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html GNU General Public License (GPL) V2
 * @author      Simon Wilkinson <simon@isengard.biz>
 * @version     Release: 2.0
 * @since       2.0
 * @package     content
 * @uses        trait \Tfish\Traits\Content\ContentTypes	Provides definition of permitted content object types.
 * @uses        trait \Tfish\Traits\TagRead Retrieve tag information for display.
 * @uses        trait \Tfish\Traits\ValidateString  Provides methods for validating UTF-8 character encoding and string composition.
 * @var         \Tfish\Database $database Instance of the Tuskfish database class.
 * @var         \Tfish\CriteriaFactory $criteriaFactory A factory class that returns instances of Criteria and CriteriaItem.
 * @var         \Tfish\Entity\Preference Instance of the Tfish site preferences class.
 * @var         \Tfish\Session Instance of the Tfish session management class.
 */
class Listing
{
    use \Tfish\Content\Traits\ContentTypes;
    use \Tfish\Traits\TagRead;
    use \Tfish\Traits\ValidateString;

    private $database;
    private $criteriaFactory;
    private $preference;
    private $session;

    /**
     * Constructor.
     *
     * @param   \Tfish\Database $database Instance of the Tuskfish database class.
     * @param   \Tfish\CriteriaFactory $criteriaFactory Instance of the criteria factory class.
     * @param   \Tfish\Entity\Preference $preference Instance of the Tuskfish site preferences class.
     * @param   \Tfish\Session $session Instance of the Tuskfish session manager class.
     */
    public function __construct(
        \Tfish\Database $database,
        \Tfish\CriteriaFactory $criteriaFactory,
        \Tfish\Entity\Preference $preference,
        \Tfish\Session $session
        )
    {
        $this->database = $database;
        $this->criteriaFactory = $criteriaFactory;
        $this->preference = $preference;
        $this->session = $session;
    }

    /** Actions. */

    /**
     * Get a single content object.
     *
     * @param   int $id ID of the content object to retrieve.
     * @return  \Tfish\Content\Entity\Content|bool
     */
    public function getObject(int $id): \Tfish\Content\Entity\Content|bool
    {
        $params = [];

        if ($id < 1) {
            return false;
        }

        $params['id'] = $id;

        if (!$this->session->isAdmin()) { // NOT admin.
            $params['onlineStatus'] = 1;
        }

        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);
        $statement = $this->database->select('content', $criteria);

        $content = $statement->fetchObject('\Tfish\Content\Entity\Content');

        $statement->closeCursor();

        if ($content && $content->type() !== 'TfDownload') {
            $this->updateCounter($cleanParams['id']);
        }

        // Pass in the minimum views preference value.
        if ($content) {
            $content->setMinimumViews($this->preference->minimumViews());
        }

        return $content;
    }

    /**
     * Get content objects matching filtering criteria.
     *
     * @param   array $params Filtering criteria.
     * @return  array Array of content objects.
     */
    public function getObjects(array $params): array
    {
        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runQuery($criteria);
    }

    /* Utilties. */

    public function getCount(array $params): int
    {
        unset(
            $params['start'],
            $params['limit'],
            $params['sort'],
            $params['order'],
            $params['secondarySort'],
            $params['secondaryOrder']
        );

        $cleanParams = $this->validateParams($params);
        $criteria = $this->setCriteria($cleanParams);

        return $this->runCount($criteria);
    }

    /**
     * Count the number of content objects meeting the filtering criteria.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @return  int Count.
     */
    private function runCount(\Tfish\Criteria $criteria): int
    {
        return $this->database->selectCount('content', $criteria);
    }

    /**
     * Get content objects.
     *
     * @param   \Tfish\Criteria $criteria Filter criteria.
     * @return  array Array of content objects as associative arrays.
     */
    private function runQuery(\Tfish\Criteria $criteria): array
    {
        $statement = $this->database->select('content', $criteria);

        return $statement->fetchAll(\PDO::FETCH_CLASS, '\Tfish\Content\Entity\Content');
    }

    private function runTagQuery(\Tfish\Criteria $criteria): array
    {
        $statement = $this->database->select('content', $criteria);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Set filter criteria for listing content.
     *
     * @param   array $params Filter criteria.
     * @return   \Tfish\Criteria Query composer.
     */
    private function setCriteria(array $cleanParams): \Tfish\Criteria
    {
        $criteria = $this->criteriaFactory->criteria();

        if (isset($cleanParams['onlineStatus']))
            $criteria->add($this->criteriaFactory->item('onlineStatus', $cleanParams['onlineStatus']));

        if (!empty($cleanParams['id'])) {
            $criteria->add($this->criteriaFactory->item('id', $cleanParams['id']));

            return $criteria;
        }

        if (!empty($cleanParams['parent']))
            $criteria->add($this->criteriaFactory->item('parent', $cleanParams['parent']));

        // Unless a specific type is requested, default behaviour is to exclude tags. If you are
        // organising your tags into collections, you may wish to re-enable tags in the stream
        // to facilitate their discovery. Content not marked as 'inFeed' (0) will also be excluded from
        // the news and RSS feeds.
        if (!empty($cleanParams['type'])) {
            $criteria->add($this->criteriaFactory->item('type', $cleanParams['type']));
        } else {
            $criteria->add($this->criteriaFactory->item('inFeed', 1));
            $criteria->add($this->criteriaFactory->item('type', 'TfTag', '!='));
        }

        if (!empty($cleanParams['tag']))
            $criteria->setTag([$cleanParams['tag']]);

        if (!empty($cleanParams['start']))
            $criteria->setOffset($cleanParams['start']);

        if (!empty($cleanParams['sort'])) {
            $criteria->setSort($cleanParams['sort']);
            $criteria->setOrder($cleanParams['order']);
        }

        if (!empty($cleanParams['secondarySort'])) {
            $criteria->setSecondarySort($cleanParams['secondarySort']);
            $criteria->setSecondaryOrder($cleanParams['secondaryOrder']);
        }

        if (!empty($cleanParams['limit'])) {
            $criteria->setLimit($cleanParams['limit']);
        }

        return $criteria;
    }

    /**
     * Increment the view/download counter for a content object.
     *
     * @param   int $id ID of content object.
     */
    private function updateCounter(int $id)
    {
        $this->database->updateCounter($id, 'content', 'counter');
    }

    /**
     * Validate parameters for filtering content.
     *
     * @param   array $params Parameters for filtering content.
     * @return  array Validated parameters.
     */
    private function validateParams(array $params): array
    {
        $cleanParams = [];

        if ($params['id'] ?? 0)
            $cleanParams['id'] = (int) $params['id'];

        if ($params['parent'] ?? 0)
            $cleanParams['parent'] = (int) $params['parent'];

        if ($params['start'] ?? 0)
            $cleanParams['start'] = (int) $params['start'];

        if ($params['limit'] ?? 0) {
            $cleanParams['limit'] = (int) $params['limit'];
        }

        if ($params['tag'] ?? 0)
            $cleanParams['tag'] = (int) ($params['tag']);

        if (isset($params['type']) && \array_key_exists($params['type'], $this->listTypes())) {
            $cleanParams['type'] = $this->trimString($params['type']);
        }

        if (isset($params['onlineStatus'])) {
            $onlineStatus = (int) $params['onlineStatus'];

            if ($onlineStatus == 0 || $onlineStatus == 1) {
                $cleanParams['onlineStatus'] = $onlineStatus;
            }
        }

        if (isset($params['sort']) && $this->isAlnumUnderscore($params['sort'])) {
            $cleanParams['sort'] = $this->trimString($params['sort']);
        }

        if (isset($params['order'])) {

            if ($params['order'] === 'ASC') {
                $cleanParams['order'] = 'ASC';
            } else {
                $cleanParams['order'] = 'DESC';
            }
        }

        if (isset($params['secondarySort']) && $this->isAlnumUnderscore($params['secondarySort'])) {
            $cleanParams['secondarySort'] = $this->trimString($params['secondarySort']);
        }

        if (isset($params['secondaryOrder'])) {

            if ($params['secondaryOrder'] === 'ASC') {
                $cleanParams['secondaryOrder'] = 'ASC';
            } else {
                $cleanParams['secondaryOrder'] = 'DESC';
            }
        }

        return $cleanParams;
    }
}
