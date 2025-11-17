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

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_items.userId', $this->userId));
        }

        if ($this->collectionId) {
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

