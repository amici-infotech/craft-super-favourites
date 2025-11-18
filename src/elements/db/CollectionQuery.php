<?php
namespace amici\SuperFavourite\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * Collection Query
 *
 * Provides query methods for Collection elements
 */
class CollectionQuery extends ElementQuery
{
    public $userId;
    public $name;
    public $handle;
    public $isDefault;
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
     * Filter by collection name
     */
    public function name($value)
    {
        $this->name = $value;
        return $this;
    }

    /**
     * Filter by collection handle
     */
    public function handle($value)
    {
        $this->handle = $value;
        return $this;
    }

    /**
     * Filter by default status
     */
    public function isDefault($value = true)
    {
        $this->isDefault = $value;
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
        $this->joinElementTable('super_favourite_collections');

        $this->query->select([
            'super_favourite_collections.userId',
            'super_favourite_collections.name',
            'super_favourite_collections.handle',
            'super_favourite_collections.description',
            'super_favourite_collections.isDefault',
            'super_favourite_collections.allowedElementTypes',
            'super_favourite_collections.sortOrder',
        ]);

        if ($this->userId) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.userId', $this->userId));
        }

        if ($this->name) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.name', $this->name));
        }

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.handle', $this->handle));
        }

        if ($this->isDefault !== null) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.isDefault', $this->isDefault));
        }

        if ($this->sortOrder !== null) {
            $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.sortOrder', $this->sortOrder));
        }

        return parent::beforePrepare();
    }
}

