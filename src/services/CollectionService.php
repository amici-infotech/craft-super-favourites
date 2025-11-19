<?php
namespace amici\SuperFavourite\services;

use Craft;
use craft\base\Component;
use craft\events\ModelEvent;

use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Collection Service
 *
 * Manages collection-related operations including creation, deletion, and retrieval.
 * Collections are containers that organize favourite items by user or globally.
 * Provides event hooks for extensibility.
 */
class CollectionService extends Component
{
    /**
     * Event fired before a collection is created
     * Use this to validate, modify, or cancel creation by setting $event->isValid = false
     */
    const EVENT_BEFORE_CREATE_COLLECTION = 'beforeCreateCollection';

    /**
     * Event fired after a collection has been successfully created
     * Use this for initialization, notifications, or related operations
     */
    const EVENT_AFTER_CREATE_COLLECTION = 'afterCreateCollection';

    /**
     * Event fired before a collection is deleted
     * Use this to validate or cancel deletion by setting $event->isValid = false
     */
    const EVENT_BEFORE_DELETE_COLLECTION = 'beforeDeleteCollection';

    /**
     * Event fired after a collection has been successfully deleted
     * Use this for cleanup, notifications, or cascading operations
     */
    const EVENT_AFTER_DELETE_COLLECTION = 'afterDeleteCollection';
    /**
     * Create a new collection for a user
     *
     * Creates a new collection element with the specified properties. Collections can be
     * user-specific or global (when userId is null). The handle is auto-generated if not provided.
     * Triggers BEFORE and AFTER events for extensibility.
     *
     * @param int $userId The user ID (null for global collections)
     * @param string $name The collection name (required)
     * @param string|null $handle URL-friendly handle (auto-generated from name if null)
     * @param string|null $description Optional description text
     * @param bool $isDefault Whether this should be the default collection for the user
     * @return Collection|false The created collection, or false on failure
     */
    public function createCollection(
        int $userId,
        string $name,
        ?string $handle = null,
        ?string $description = null,
        bool $isDefault = false
    ) {
        // Create new collection element
        $collection = new Collection();
        $collection->userId = $userId;
        $collection->name = $name;
        $collection->handle = $handle;
        $collection->description = $description;
        $collection->isDefault = $isDefault;

        // Trigger before event - allows validation or modification
        if ($this->hasEventHandlers(self::EVENT_BEFORE_CREATE_COLLECTION)) {
            $event = new ModelEvent(['sender' => $collection]);
            $this->trigger(self::EVENT_BEFORE_CREATE_COLLECTION, $event);
            if (!$event->isValid) {
                return false;
            }
        }

        // Save the collection element
        if (!Craft::$app->getElements()->saveElement($collection)) {
            return false;
        }

        // Trigger after event - useful for initialization or notifications
        if ($this->hasEventHandlers(self::EVENT_AFTER_CREATE_COLLECTION)) {
            $this->trigger(self::EVENT_AFTER_CREATE_COLLECTION, new ModelEvent(['sender' => $collection]));
        }

        return $collection;
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
     * Removes a collection from the system. Can optionally delete all favourite items
     * within the collection as a cascading operation. Triggers BEFORE and AFTER events.
     * The BEFORE event can prevent deletion by setting $event->isValid = false.
     *
     * @param int $collectionId The ID of the collection to delete
     * @param bool $deleteItems If true, also deletes all favourite items in this collection
     * @return bool True if deletion was successful, false otherwise
     */
    public function deleteCollection(int $collectionId, bool $deleteItems = false): bool
    {
        // Find the collection to delete
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            return false;
        }

        // Trigger before event - allows cancellation (e.g., prevent deletion of default collections)
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_COLLECTION)) {
            $event = new ModelEvent(['sender' => $collection]);
            $this->trigger(self::EVENT_BEFORE_DELETE_COLLECTION, $event);
            if (!$event->isValid) {
                return false;
            }
        }

        // Optionally delete all items in the collection (cascading delete)
        if ($deleteItems) {
            $items = FavouriteItem::find()
                ->collectionId($collectionId)
                ->all();

            foreach ($items as $item) {
                Craft::$app->getElements()->deleteElement($item);
            }
        }

        // Delete the collection element
        if (!Craft::$app->getElements()->deleteElement($collection)) {
            return false;
        }

        // Trigger after event - useful for cleanup or notifications
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_COLLECTION)) {
            $this->trigger(self::EVENT_AFTER_DELETE_COLLECTION, new ModelEvent(['sender' => $collection]));
        }

        return true;
    }

    /**
     * Get all collections for a user (global + user-specific)
     *
     * Returns collections accessible by the specified user, including both:
     * - Global collections (userId = null) that are available to all users
     * - User-specific collections (userId = specified user)
     *
     * Results are ordered with global collections first, then user collections,
     * and within each group by sortOrder.
     *
     * @param int|null $userId The user ID (defaults to current logged-in user)
     * @return array Array of Collection elements
     */
    public function getUserCollections(?int $userId = null): array
    {
        // Use current logged-in user if no user ID provided
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return [];
            }
            $userId = $currentUser->id;
        }

        // Query for both global and user-specific collections
        // Global collections (userId IS NULL) are shown first, then user collections
        return Collection::find()
            ->where([
                'or',
                ['super_favourite_collections.userId' => null],  // Global collections
                ['super_favourite_collections.userId' => $userId] // User's collections
            ])
            ->orderBy('CASE WHEN super_favourite_collections.userId IS NULL THEN 0 ELSE 1 END, super_favourite_collections.sortOrder ASC')
            ->all();
    }

    /**
     * Get a collection by handle for a user
     */
    public function getCollectionByHandle(string $handle, ?int $userId = null)
    {
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
     */
    public function getDefaultCollection(?int $userId = null)
    {
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
     */
    public function setDefaultCollection(int $collectionId): bool
    {
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            return false;
        }

        $otherDefaults = Collection::find()
            ->userId($collection->userId)
            ->isDefault(true)
            ->id('not ' . $collectionId)
            ->all();

        foreach ($otherDefaults as $other) {
            $other->isDefault = false;
            Craft::$app->getElements()->saveElement($other);
        }

        $collection->isDefault = true;
        return Craft::$app->getElements()->saveElement($collection);
    }

    /**
     * Get collection count for a user
     */
    public function getCollectionCount(?int $userId = null): int
    {
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

