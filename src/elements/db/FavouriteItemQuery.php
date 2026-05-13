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
    public mixed $userId = null;
    public mixed $collectionId = null;
    public mixed $elementId = null;
    public mixed $favouritedElementType = null;
    public mixed $sortOrder = null;

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
        return $this;
    }

    /**
     * Runs the `collectionId()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function collectionId($value)
    {
        $this->collectionId = $value;
        return $this;
    }

    /**
     * Runs the `elementId()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function elementId($value)
    {
        $this->elementId = $value;
        return $this;
    }

    /**
     * Runs the `favouritedElementType()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function favouritedElementType($value)
    {
        $this->favouritedElementType = $value;
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
     * Runs the `author()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Runs the `favouritedElement()` method for this plugin class.
     *
     * @param mixed $value Filter value accepted by Craft query parameter syntax.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Joins plugin tables and applies custom filters before Craft runs the query.
     *
     * @return bool True on success or when the condition matches; false otherwise.
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

