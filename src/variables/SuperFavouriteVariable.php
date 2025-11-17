<?php
namespace amici\SuperFavourite\variables;

use Craft;
use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Super Favourite Variable
 *
 * Provides template-level access to favourite functionality
 *
 * Usage in templates:
 * {% set favouriteIds = craft.superFavourite.getFavouritedElementIds() %}
 * {% set collections = craft.superFavourite.getCollections() %}
 * {% if craft.superFavourite.isFavourited(entry.id) %}...{% endif %}
 */
class SuperFavouriteVariable
{
    /**
     * Check if an element is favourited by the current user
     *
     * @param int $elementId
     * @param int|null $collectionId
     * @return bool
     */
    public function isFavourited(int $elementId, ?int $collectionId = null): bool
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return false;
        }

        return Plugin::getInstance()->favourite->isFavourited(
            $elementId,
            $currentUser->id,
            $collectionId
        );
    }

    /**
     * Get all favourited element IDs for the current user
     *
     * @param int|null $collectionId
     * @param string|null $elementType
     * @return array
     */
    public function getFavouritedElementIds(?int $collectionId = null, ?string $elementType = null): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return [];
        }

        return Plugin::getInstance()->favourite->getFavouritedElementIds(
            $currentUser->id,
            $collectionId,
            $elementType
        );
    }

    /**
     * Get all favourite items for the current user
     *
     * @param int|null $collectionId
     * @param string|null $elementType
     * @return array
     */
    public function getFavourites(?int $collectionId = null, ?string $elementType = null): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return [];
        }

        return Plugin::getInstance()->favourite->getFavourites(
            $currentUser->id,
            $collectionId,
            $elementType
        );
    }

    /**
     * Get count of favourites for the current user
     *
     * @param int|null $collectionId
     * @return int
     */
    public function getFavouriteCount(?int $collectionId = null): int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return 0;
        }

        return Plugin::getInstance()->favourite->getFavouriteCount(
            $currentUser->id,
            $collectionId
        );
    }

    /**
     * Get all collections for the current user
     *
     * @return array
     */
    public function getCollections(): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return [];
        }

        return Plugin::getInstance()->collection->getUserCollections($currentUser->id);
    }

    /**
     * Get a collection by handle for the current user
     *
     * @param string $handle
     * @return Collection|null
     */
    public function getCollectionByHandle(string $handle): ?Collection
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return null;
        }

        return Plugin::getInstance()->collection->getCollectionByHandle($handle, $currentUser->id);
    }

    /**
     * Get the default collection for the current user
     *
     * @return Collection|null
     */
    public function getDefaultCollection(): ?Collection
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return null;
        }

        return Plugin::getInstance()->collection->getDefaultCollection($currentUser->id);
    }

    /**
     * Get collection count for the current user
     *
     * @return int
     */
    public function getCollectionCount(): int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return 0;
        }

        return Plugin::getInstance()->collection->getCollectionCount($currentUser->id);
    }

    /**
     * Query favourite items
     *
     * @return \craft\elements\db\ElementQueryInterface
     */
    public function favourites()
    {
        return FavouriteItem::find();
    }

    /**
     * Query collections
     *
     * @return \craft\elements\db\ElementQueryInterface
     */
    public function collections()
    {
        return Collection::find();
    }

    /**
     * Check if current user is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return !Craft::$app->getUser()->getIsGuest();
    }

    /**
     * Get the current user ID
     *
     * @return int|null
     */
    public function getCurrentUserId(): ?int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        return $currentUser ? $currentUser->id : null;
    }
}

