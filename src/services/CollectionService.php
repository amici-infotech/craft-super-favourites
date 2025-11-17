<?php
namespace amici\SuperFavourite\services;

use Craft;
use craft\base\Component;

use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Collection Service
 *
 * Handles all collection-related operations
 */
class CollectionService extends Component
{
    /**
     * Create a new collection for a user
     *
     * @param int $userId The user ID
     * @param string $name The collection name
     * @param string|null $handle The collection handle (auto-generated if null)
     * @param string|null $description The collection description
     * @param bool $isDefault Whether this is the default collection
     * @return Collection|false
     */
    public function createCollection(
        int $userId,
        string $name,
        ?string $handle = null,
        ?string $description = null,
        bool $isDefault = false
    ) {
        $collection = new Collection();
        $collection->userId = $userId;
        $collection->name = $name;
        $collection->handle = $handle;
        $collection->description = $description;
        $collection->isDefault = $isDefault;

        if (Craft::$app->getElements()->saveElement($collection)) {
            return $collection;
        }

        return false;
    }

    /**
     * Update a collection
     *
     * @param int $collectionId The collection ID
     * @param array $attributes Attributes to update
     * @return Collection|false
     */
    public function updateCollection(int $collectionId, array $attributes)
    {
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            return false;
        }

        foreach ($attributes as $key => $value) {
            if (property_exists($collection, $key)) {
                $collection->$key = $value;
            }
        }

        if (Craft::$app->getElements()->saveElement($collection)) {
            return $collection;
        }

        return false;
    }

    /**
     * Delete a collection and optionally its items
     *
     * @param int $collectionId The collection ID
     * @param bool $deleteItems Whether to delete items in the collection
     * @return bool
     */
    public function deleteCollection(int $collectionId, bool $deleteItems = false): bool
    {
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            return false;
        }

        // Optionally delete all items in the collection
        if ($deleteItems) {
            $items = FavouriteItem::find()
                ->collectionId($collectionId)
                ->all();

            foreach ($items as $item) {
                Craft::$app->getElements()->deleteElement($item);
            }
        }

        return Craft::$app->getElements()->deleteElement($collection);
    }

    /**
     * Get all collections for a user (global + user-specific)
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @return array
     */
    public function getUserCollections(?int $userId = null): array
    {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return [];
            }
            $userId = $currentUser->id;
        }

        // Get global collections (userId is null) + user-specific collections
        return Collection::find()
            ->where([
                'or',
                ['super_favourite_collections.userId' => null],
                ['super_favourite_collections.userId' => $userId]
            ])
            ->orderBy('CASE WHEN super_favourite_collections.userId IS NULL THEN 0 ELSE 1 END, super_favourite_collections.sortOrder ASC')
            ->all();
    }

    /**
     * Get a collection by handle for a user
     *
     * @param string $handle The collection handle
     * @param int|null $userId The user ID (defaults to current user)
     * @return Collection|null
     */
    public function getCollectionByHandle(string $handle, ?int $userId = null)
    {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return null;
            }
            $userId = $currentUser->id;
        }

        return Collection::find()
            ->userId($userId)
            ->handle($handle)
            ->one();
    }

    /**
     * Get the default collection for a user
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @return Collection|null
     */
    public function getDefaultCollection(?int $userId = null)
    {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return null;
            }
            $userId = $currentUser->id;
        }

        return Collection::find()
            ->userId($userId)
            ->isDefault(true)
            ->one();
    }

    /**
     * Set a collection as default for a user
     *
     * @param int $collectionId The collection ID
     * @return bool
     */
    public function setDefaultCollection(int $collectionId): bool
    {
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            return false;
        }

        // Unset any other default collections for this user
        $otherDefaults = Collection::find()
            ->userId($collection->userId)
            ->isDefault(true)
            ->id('not ' . $collectionId)
            ->all();

        foreach ($otherDefaults as $other) {
            $other->isDefault = false;
            Craft::$app->getElements()->saveElement($other);
        }

        // Set this as default
        $collection->isDefault = true;
        return Craft::$app->getElements()->saveElement($collection);
    }

    /**
     * Get collection count for a user
     *
     * @param int|null $userId The user ID (defaults to current user)
     * @return int
     */
    public function getCollectionCount(?int $userId = null): int
    {
        // Get current user if not specified
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return 0;
            }
            $userId = $currentUser->id;
        }

        return Collection::find()
            ->userId($userId)
            ->count();
    }

    /**
     * Reorder collections
     *
     * @param array $collectionIds Array of collection IDs in desired order
     * @return bool
     */
    public function reorderCollections(array $collectionIds): bool
    {
        $success = true;

        foreach ($collectionIds as $index => $collectionId) {
            $collection = Collection::find()->id($collectionId)->one();

            if ($collection) {
                $collection->sortOrder = $index;
                if (!Craft::$app->getElements()->saveElement($collection)) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}

