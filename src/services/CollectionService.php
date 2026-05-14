<?php
namespace amici\SuperFavourite\services;

use Craft;
use craft\base\Component;
use craft\events\ModelEvent;

use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\jobs\DeleteFavouriteItemsForCollection;

/**
 * Collection Service
 *
 * Manages collection-related operations including creation, deletion, and retrieval.
 * Collections are containers that organize favourite items by user or globally.
 * Provides event hooks for extensibility.
 */
class CollectionService extends Component
{
    private ?string $_lastError = null;

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
     * Returns the last service-level error message.
     *
     * @return ?string The most recent error, or null when none exists.
     */
    public function getLastError(): ?string
    {
        return $this->_lastError;
    }

    /**
     * Creates a new collection element.
     *
     * @param int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param string $name The collection name, or source text used to generate a handle.
     * @param ?string $handle The collection handle to save, filter by, or test for uniqueness.
     * @param ?string $description Optional collection description text.
     * @param bool $isDefault Whether the collection should be the default collection.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Updates a collection with supplied attributes.
     *
     * @param int $collectionId The ID of the collection element.
     * @param array $attributes Key/value attributes to apply to the collection.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Deletes a collection and optionally queues deletion of its favourite items.
     *
     * @param int|Collection $collection The collection element or its ID.
     * @param bool $deleteItems Whether favourite items in the collection should also be deleted by a queue job.
     *
     * @return bool True when deleted or queued; false when the collection cannot be deleted.
     */
    public function deleteCollection(int|Collection $collection, bool $deleteItems = false): bool
    {
        $this->_lastError = null;

        $collection = is_int($collection)
            ? Collection::find()->id($collection)->one()
            : $collection;

        if (!$collection) {
            $this->_lastError = Craft::t('super-favourite', 'Collection not found.');
            return false;
        }

        $collectionId = (int)$collection->id;

        // Trigger before event - allows cancellation (e.g., prevent deletion of default collections)
        if ($this->hasEventHandlers(self::EVENT_BEFORE_DELETE_COLLECTION)) {
            $event = new ModelEvent(['sender' => $collection]);
            $this->trigger(self::EVENT_BEFORE_DELETE_COLLECTION, $event);
            if (!$event->isValid) {
                $this->_lastError = Craft::t('super-favourite', 'Collection deletion was cancelled.');
                /** @var \craft\base\Model $collection */
                $collection->addError('id', $this->_lastError);
                /** @var Collection $collection */
                return false;
            }
        }

        if ($deleteItems) {
            $collection->allowDeleteWithFavouriteItems = true;
        }

        if (!Craft::$app->getElements()->deleteElement($collection)) {
            $this->_lastError = $this->modelErrorsToString(
                $collection,
                Craft::t('super-favourite', 'Failed to delete collection.')
            );
            return false;
        }

        if ($deleteItems) {
            Craft::$app->getQueue()->push(new DeleteFavouriteItemsForCollection([
                'collectionId' => $collectionId,
            ]));
        }

        // Trigger after event - useful for cleanup or notifications
        if ($this->hasEventHandlers(self::EVENT_AFTER_DELETE_COLLECTION)) {
            $this->trigger(self::EVENT_AFTER_DELETE_COLLECTION, new ModelEvent(['sender' => $collection]));
        }

        return true;
    }

    /**
     * Formats model validation errors for controller responses and flash messages.
     *
     * @param Collection $collection The collection model that failed.
     * @param string $fallback The fallback message when no validation errors exist.
     *
     * @return string A readable error message.
     */
    private function modelErrorsToString(Collection $collection, string $fallback): string
    {
        /** @var \craft\base\Model $collection */
        $errors = $collection->getErrors();

        if (empty($errors)) {
            return $fallback;
        }

        $messages = [];
        foreach ($errors as $fieldErrors) {
            $messages[] = implode(', ', $fieldErrors);
        }

        return implode(' ', $messages);
    }

    /**
     * Returns global and user-owned collections visible to a user.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return array The requested array of data.
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
     * Returns a collection by handle for a user.
     *
     * @param string $handle The collection handle to save, filter by, or test for uniqueness.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Returns the default collection, or null if none exists.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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
     * Marks a collection as default and clears competing defaults.
     *
     * @param int $collectionId The ID of the collection element.
     *
     * @return bool True on success or when the condition matches; false otherwise.
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
     * Counts collections for a user.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return int The requested integer value.
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
     * Persists the requested collection order.
     *
     * @param array $collectionIds Collection IDs in the desired display order.
     *
     * @return bool True on success or when the condition matches; false otherwise.
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

