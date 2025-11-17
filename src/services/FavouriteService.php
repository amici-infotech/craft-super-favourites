<?php
namespace amici\SuperFavourite\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\User;

use amici\SuperFavourite\elements\FavouriteItem;
use amici\SuperFavourite\elements\Collection;

/**
 * Favourite Service
 *
 * Handles all favourite-related operations
 */
class FavouriteService extends Component
{
    /**
     * Add an element to favourites
     *
     * @param int $elementId The element to favourite
     * @param string $elementType The element type class name
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (defaults to user's default collection)
     * @param string|null $notes Optional notes
     * @return FavouriteItem|false
     */
    public function addFavourite(
        int $elementId,
        string $elementType,
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $notes = null
    ) {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return false;
            }
            $userId = $currentUser->id;
        }

        // Get or create default collection if not specified
        if ($collectionId === null) {
            $collection = $this->getOrCreateDefaultCollection($userId);
            if (!$collection) {
                return false;
            }
            $collectionId = $collection->id;
        }

        // Check if already favourited
        $existing = FavouriteItem::find()
            ->userId($userId)
            ->collectionId($collectionId)
            ->elementId($elementId)
            ->one();

        if ($existing) {
            return $existing; // Already favourited
        }

        // Create new favourite
        $favourite = new FavouriteItem();
        $favourite->userId = $userId;
        $favourite->collectionId = $collectionId;
        $favourite->elementId = $elementId;
        $favourite->elementType = $elementType;
        $favourite->notes = $notes;

        if (Craft::$app->getElements()->saveElement($favourite)) {
            return $favourite;
        }

        return false;
    }

    /**
     * Remove an element from favourites
     *
     * @param int $elementId The element to unfavourite
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (null = all collections)
     * @return bool
     */
    public function removeFavourite(
        int $elementId,
        ?int $userId = null,
        ?int $collectionId = null
    ): bool {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return false;
            }
            $userId = $currentUser->id;
        }

        // Find the favourite(s)
        $query = FavouriteItem::find()
            ->userId($userId)
            ->elementId($elementId);

        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        $favourites = $query->all();

        if (empty($favourites)) {
            return false;
        }

        // Delete all matching favourites
        $success = true;
        foreach ($favourites as $favourite) {
            if (!Craft::$app->getElements()->deleteElement($favourite)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Check if an element is favourited
     *
     * @param int $elementId The element to check
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (null = any collection)
     * @return bool
     */
    public function isFavourited(
        int $elementId,
        ?int $userId = null,
        ?int $collectionId = null
    ): bool {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return false;
            }
            $userId = $currentUser->id;
        }

        $query = FavouriteItem::find()
            ->userId($userId)
            ->elementId($elementId);

        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        return $query->exists();
    }

    /**
     * Get all favourited element IDs for a user
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (null = all collections)
     * @param string|null $elementType Filter by element type
     * @return array
     */
    public function getFavouritedElementIds(
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $elementType = null
    ): array {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return [];
            }
            $userId = $currentUser->id;
        }

        $query = FavouriteItem::find()
            ->userId($userId)
            ->select(['super_favourite_items.elementId']);

        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        if ($elementType !== null) {
            $query->favouritedElementType($elementType);
        }

        return $query->column();
    }

    /**
     * Get all favourite items for a user
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (null = all collections)
     * @param string|null $elementType Filter by element type
     * @return array
     */
    public function getFavourites(
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $elementType = null
    ): array {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return [];
            }
            $userId = $currentUser->id;
        }

        $query = FavouriteItem::find()
            ->userId($userId);

        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        if ($elementType !== null) {
            $query->favouritedElementType($elementType);
        }

        return $query->all();
    }

    /**
     * Get or create a default collection for a user
     *
     * @param int $userId The user ID
     * @return Collection|false
     */
    public function getOrCreateDefaultCollection(int $userId)
    {
        // Try to find existing default collection
        $collection = Collection::find()
            ->userId($userId)
            ->isDefault(true)
            ->one();

        if ($collection) {
            return $collection;
        }

        // Create default collection
        $collection = new Collection();
        $collection->userId = $userId;
        $collection->name = 'My Favourites';
        $collection->handle = 'default';
        $collection->isDefault = true;
        $collection->description = 'Default collection for favourites';

        if (Craft::$app->getElements()->saveElement($collection)) {
            return $collection;
        }

        return false;
    }

    /**
     * Move a favourite to another collection
     *
     * @param int $favouriteId The favourite item ID
     * @param int $newCollectionId The new collection ID
     * @return bool
     */
    public function moveFavourite(int $favouriteId, int $newCollectionId): bool
    {
        $favourite = FavouriteItem::find()->id($favouriteId)->one();

        if (!$favourite) {
            return false;
        }

        $favourite->collectionId = $newCollectionId;

        return Craft::$app->getElements()->saveElement($favourite);
    }

    /**
     * Get count of favourites for a user
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @param int|null $collectionId The collection ID (null = all collections)
     * @return int
     */
    public function getFavouriteCount(
        ?int $userId = null,
        ?int $collectionId = null
    ): int {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return 0;
            }
            $userId = $currentUser->id;
        }

        $query = FavouriteItem::find()
            ->userId($userId);

        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        return $query->count();
    }
}

