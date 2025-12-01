<?php
namespace amici\SuperFavourite\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * Favourite Item Query
 *
 * Provides query methods for FavouriteItem elements
 */
class FavouriteItemQuery extends ElementQuery
{
    public $userId;
    public $collectionId;
    public $elementId;
    public $favouritedElementType;
    public $sortOrder;

    /**
     * Filter by user ID
     */
    public function userId($value)
    {
        $this->userId = $value;
        return $this;
    }

    /**
     * Filter by collection ID
     */
    public function collectionId($value)
    {
        $this->collectionId = $value;
        return $this;
    }

    /**
     * Filter by favourited element ID
     */
    public function elementId($value)
    {
        $this->elementId = $value;
        return $this;
    }

    /**
     * Filter by favourited element type (the type of element that was favourited)
     */
    public function favouritedElementType($value)
    {
        $this->favouritedElementType = $value;
        return $this;
    }

    /**
     * Order by sort order
     */
    public function sortOrder($value)
    {
        $this->sortOrder = $value;
        return $this;
    }

    /**
     * Filter by the user who owns the favourites (the author)
     * This is an alias for userId() that's more intuitive to use
     *
     * @param mixed $value User element(s), user ID(s), or null
     * @return static
     */
    public function author($value)
    {
        if ($value instanceof \craft\elements\User) {
            $this->userId = $value->id;
        } elseif (is_array($value)) {
            $userIds = [];
            foreach ($value as $user) {
                if ($user instanceof \craft\elements\User) {
                    $userIds[] = $user->id;
                } elseif (is_numeric($user)) {
                    $userIds[] = (int)$user;
                }
            }
            $this->userId = $userIds;
        } else {
            $this->userId = $value;
        }
        return $this;
    }

    /**
     * Filter by the favourited element (what was favourited)
     * This allows passing element instances instead of just IDs
     *
     * @param mixed $value Element(s), element ID(s), or null
     * @return static
     */
    public function favouritedElement($value)
    {
        if ($value instanceof \craft\base\ElementInterface) {
            $this->elementId = $value->id;
        } elseif (is_array($value)) {
            $elementIds = [];
            foreach ($value as $element) {
                if ($element instanceof \craft\base\ElementInterface) {
                    $elementIds[] = $element->id;
                } elseif (is_numeric($element)) {
                    $elementIds[] = (int)$element;
                }
            }
            $this->elementId = $elementIds;
        } else {
            $this->elementId = $value;
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable('super_favourite_items');

        $this->query->select([
            'super_favourite_items.userId',
            'super_favourite_items.collectionId',
            'super_favourite_items.elementId',
            'super_favourite_items.elementType',
            'super_favourite_items.sortOrder',
            'super_favourite_items.notes',
        ]);

        if ($this->userId !== null) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.userId', $this->userId));
        }

        if ($this->collectionId !== null) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.collectionId', $this->collectionId));
        }

        if ($this->elementId) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.elementId', $this->elementId));
        }

        if ($this->favouritedElementType) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.elementType', $this->favouritedElementType));
        }

        if ($this->sortOrder !== null) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.sortOrder', $this->sortOrder));
        }

        return parent::beforePrepare();
    }
}

