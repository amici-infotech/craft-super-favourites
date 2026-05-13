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
    public mixed $userId = null;
    public mixed $name = null;
    public mixed $handle = null;
    public mixed $isDefault = null;
    public mixed $sortOrder = null;

    private bool $_userIdSet = false;

    /**
     * Runs the `userId()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function userId($value)
    {
        $this->userId = $value;
        $this->_userIdSet = true;
        return $this;
    }

    /**
     * Runs the `name()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function name($value)
    {
        $this->name = $value;
        return $this;
    }

    /**
     * Runs the `handle()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function handle($value)
    {
        $this->handle = $value;
        return $this;
    }

    /**
     * Runs the `isDefault()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function isDefault($value = true)
    {
        $this->isDefault = $value;
        return $this;
    }

    /**
     * Runs the `sortOrder()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function sortOrder($value)
    {
        $this->sortOrder = $value;
        return $this;
    }

    /**
     * Joins plugin tables and applies custom filters before Craft runs the query.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    protected function beforePrepare(): bool
    {
        if ($this->_userIdSet) {
            if ($this->userId === null) {
                $this->subQuery->andWhere(['super_favourite_collections.userId' => null]);
            } else {
                $this->subQuery->andWhere(Db::parseParam('super_favourite_collections.userId', $this->userId));
            }
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

        return parent::beforePrepare();
    }
}

