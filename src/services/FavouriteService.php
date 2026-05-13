<?php
namespace amici\SuperFavourite\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\elements\User;
use craft\events\ModelEvent;

use amici\SuperFavourite\elements\FavouriteItem;
use amici\SuperFavourite\elements\Collection;

/**
 * Favourite Service
 *
 * Handles all favourite-related operations including adding, removing, and toggling favourites.
 * Provides event hooks for extensibility and uses direct database queries for reliability.
 */
class FavouriteService extends Component
{
    /**
     * Event fired before a favourite is added
     * Use this to validate, modify, or cancel the operation by setting $event->isValid = false
     */
    const EVENT_BEFORE_ADD_FAVOURITE = 'beforeAddFavourite';

    /**
     * Event fired after a favourite has been successfully added
     * Use this for notifications, logging, or updating statistics
     */
    const EVENT_AFTER_ADD_FAVOURITE = 'afterAddFavourite';

    /**
     * Event fired before a favourite is removed
     * Use this to validate or cancel the removal by setting $event->isValid = false
     */
    const EVENT_BEFORE_REMOVE_FAVOURITE = 'beforeRemoveFavourite';

    /**
     * Event fired after a favourite has been successfully removed
     * Use this for cleanup, notifications, or updating statistics
     */
    const EVENT_AFTER_REMOVE_FAVOURITE = 'afterRemoveFavourite';

    /**
     * Event fired before a favourite is moved to another collection
     * Use this to validate the move or cancel it by setting $event->isValid = false
     */
    const EVENT_BEFORE_MOVE_FAVOURITE = 'beforeMoveFavourite';

    /**
     * Event fired after a favourite has been successfully moved
     * Use this to update collection statistics or perform related operations
     */
    const EVENT_AFTER_MOVE_FAVOURITE = 'afterMoveFavourite';

    /**
     * Finds an existing favourite with the same user, collection, and element.
     *
     * @param int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     * @param int $elementId The ID of the Craft element being favourited or checked.
     *
     * @return ?FavouriteItem The existing favourite item, or null when no duplicate exists.
     */
    public function checkDuplicate(int $userId, ?int $collectionId, int $elementId): ?FavouriteItem
    {
        return FavouriteItem::find()
            ->userId($userId)
            ->collectionId($collectionId)
            ->elementId($elementId)
            ->one();
    }

    /**
     * Creates a favourite item or returns the existing one when it already exists.
     *
     * @param int $elementId The ID of the Craft element being favourited or checked.
     * @param string $elementType The fully qualified class name of the Craft element type.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     * @param ?string $notes Optional notes stored on the favourite item.
     *
     * @return mixed The created/existing favourite item, or false on failure.
     */
    public function addFavourite(
        int $elementId,
        string $elementType,
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $notes = null
    ) {
        // Use current logged-in user if no user ID provided
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return false;
            }
            $userId = $currentUser->id;
        }

        // Get or create default collection if no collection specified
        if ($collectionId === null) {
            $collection = $this->getOrCreateDefaultCollection($userId);
            if (!$collection) {
                return false;
            }
            $collectionId = $collection->id;
        }

        // Check for existing favourite to prevent duplicates
        $existing = $this->checkDuplicate($userId, $collectionId, $elementId);
        if ($existing) {
            return $existing;
        }

        // Create new favourite item
        $favourite = new FavouriteItem();
        $favourite->userId = $userId;
        $favourite->collectionId = $collectionId;
        $favourite->elementId = $elementId;
        $favourite->elementType = $elementType;
        $favourite->notes = $notes;

        // Trigger before event - allows validation or cancellation
        if ($this->hasEventHandlers(self::EVENT_BEFORE_ADD_FAVOURITE)) {
            $event = new ModelEvent(['sender' => $favourite]);
            $this->trigger(self::EVENT_BEFORE_ADD_FAVOURITE, $event);
            if (!$event->isValid) {
                return false;
            }
        }

        // Save the favourite element
        if (!Craft::$app->getElements()->saveElement($favourite)) {
            return false;
        }

        // Trigger after event - useful for notifications, logging, etc.
        if ($this->hasEventHandlers(self::EVENT_AFTER_ADD_FAVOURITE)) {
            $this->trigger(self::EVENT_AFTER_ADD_FAVOURITE, new ModelEvent(['sender' => $favourite]));
        }

        return $favourite;
    }

    /**
     * Removes favourite items matching the supplied filters.
     *
     * @param int $elementId The ID of the Craft element being favourited or checked.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     * @param bool $hardDelete Whether to permanently delete instead of soft-deleting.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function removeFavourite(
        int $elementId,
        ?int $userId = null,
        ?int $collectionId = null,
        bool $hardDelete = true
    ): bool {
        // Use current logged-in user if no user ID provided
        if ($userId === null) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser) {
                return false;
            }
            $userId = $currentUser->id;
        }

        // Build query to find matching favourites
        $query = FavouriteItem::find()
            ->userId($userId)
            ->elementId($elementId);

        // Optionally filter by collection (if null, removes from all collections)
        if ($collectionId !== null) {
            $query->collectionId($collectionId);
        }

        $favourites = $query->all();

        if (empty($favourites)) {
            return false;
        }

        // Delete each matching favourite
        $success = true;
        foreach ($favourites as $favourite) {
            // Trigger before event - allows cancellation
            if ($this->hasEventHandlers(self::EVENT_BEFORE_REMOVE_FAVOURITE)) {
                $event = new ModelEvent(['sender' => $favourite]);
                $this->trigger(self::EVENT_BEFORE_REMOVE_FAVOURITE, $event);
                if (!$event->isValid) {
                    continue;
                }
            }

            // Perform deletion (hard delete recommended to avoid duplicate issues)
            if (!Craft::$app->getElements()->deleteElement($favourite, $hardDelete)) {
                $success = false;
            } else {
                // Trigger after event - useful for cleanup, notifications
                if ($this->hasEventHandlers(self::EVENT_AFTER_REMOVE_FAVOURITE)) {
                    $this->trigger(self::EVENT_AFTER_REMOVE_FAVOURITE, new ModelEvent(['sender' => $favourite]));
                }
            }
        }

        return $success;
    }

    /**
     * Checks whether a user has favourited an element.
     *
     * @param int $elementId The ID of the Craft element being favourited or checked.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function isFavourited(
        int $elementId,
        ?int $userId = null,
        ?int $collectionId = null
    ): bool {
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
     * Returns IDs for elements favourited by a user.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     * @param ?string $elementType The fully qualified class name of the Craft element type.
     *
     * @return array The requested array of data.
     */
    public function getFavouritedElementIds(
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $elementType = null
    ): array {
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
     * Returns favourite item elements for a user and optional filters.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     * @param ?string $elementType The fully qualified class name of the Craft element type.
     *
     * @return array The requested array of data.
     */
    public function getFavourites(
        ?int $userId = null,
        ?int $collectionId = null,
        ?string $elementType = null
    ): array {
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
     * Returns a default collection for the user, creating one if necessary.
     *
     * @param int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return mixed The default collection, or false when creation fails.
     */
    public function getOrCreateDefaultCollection(int $userId)
    {
        $collection = Collection::find()
            ->userId($userId)
            ->isDefault(true)
            ->one();

        if ($collection) {
            return $collection;
        }

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
     * Moves a favourite item into another collection.
     *
     * @param int $favouriteId The ID of the favourite item element.
     * @param int $newCollectionId The ID of the collection the favourite should move into.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function moveFavourite(int $favouriteId, int $newCollectionId): bool
    {
        $favourite = FavouriteItem::find()->id($favouriteId)->one();

        if (!$favourite) {
            return false;
        }

        $oldCollectionId = $favourite->collectionId;

        if ($this->hasEventHandlers(self::EVENT_BEFORE_MOVE_FAVOURITE)) {
            $event = new ModelEvent(['sender' => $favourite]);
            $this->trigger(self::EVENT_BEFORE_MOVE_FAVOURITE, $event);
            if (!$event->isValid) {
                return false;
            }
        }

        $favourite->collectionId = $newCollectionId;

        if (!Craft::$app->getElements()->saveElement($favourite)) {
            return false;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_MOVE_FAVOURITE)) {
            $this->trigger(self::EVENT_AFTER_MOVE_FAVOURITE, new ModelEvent(['sender' => $favourite]));
        }

        return true;
    }

    /**
     * Counts favourite items for a user and optional collection.
     *
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param ?int $collectionId The ID of the collection element.
     *
     * @return int The requested integer value.
     */
    public function getFavouriteCount(
        ?int $userId = null,
        ?int $collectionId = null
    ): int {
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

    /**
     * Returns the current user ID without throwing for guests.
     *
     * @return ?int The requested integer value, or null when none exists.
     */
    protected function getCurrentUserId(): ?int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        return $currentUser ? $currentUser->id : null;
    }

    /**
     * Adds a favourite when absent or removes it when present.
     *
     * @param int $elementId The ID of the Craft element being favourited or checked.
     * @param string $elementType The fully qualified class name of the Craft element type.
     * @param int $collectionId The ID of the collection element.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return array A result array with success, action, IDs, or error information.
     */
    public function toggleFavourite(
        int $elementId,
        string $elementType,
        int $collectionId,
        ?int $userId = null
    ): array {
        // Ensure user is logged in
        if ($userId === null) {
            $userId = $this->getCurrentUserId();
            if (!$userId) {
                return ['success' => false, 'error' => 'User not logged in'];
            }
        }

        // Check if favourite already exists
        $existing = $this->checkDuplicate($userId, $collectionId, $elementId);

        // If exists, remove it
        if ($existing) {
            // Attempt hard delete via element service
            if (Craft::$app->getElements()->deleteElement($existing, true)) {
                return [
                    'success' => true,
                    'action' => 'removed',
                    'favouriteId' => null,
                    'elementId' => $elementId,
                    'elementType' => $elementType,
                    'collectionId' => $collectionId,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to remove favourite',
            ];
        }

        // Double-check before adding to prevent race conditions
        $doubleCheck = $this->checkDuplicate($userId, $collectionId, $elementId);
        if ($doubleCheck) {
            return [
                'success' => false,
                'error' => 'Favourite already exists',
                'favouriteId' => $doubleCheck->id,
            ];
        }

        // Add new favourite
        $favouriteItem = $this->addFavourite($elementId, $elementType, $userId, $collectionId);

        if ($favouriteItem) {
            return [
                'success' => true,
                'action' => 'added',
                'favouriteId' => $favouriteItem->id,
                'elementId' => $elementId,
                'elementType' => $elementType,
                'collectionId' => $collectionId,
            ];
        }

        return ['success' => false, 'error' => 'Failed to add favourite'];
    }

    /**
     * Returns element data with favourite state for a collection.
     *
     * @param string $elementType The fully qualified class name of the Craft element type.
     * @param int $collectionId The ID of the collection element.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     * @param int $limit Maximum number of elements to return.
     *
     * @return array Element data rows with favourite status metadata.
     */
    public function getElementsWithFavouriteStatus(
        string $elementType,
        int $collectionId,
        ?int $userId = null,
        int $limit = 10
    ): array {
        // Get current user if not specified (null for guest users)
        if ($userId === null) {
            $userId = $this->getCurrentUserId();
        }

        // Validate element type class exists and is valid
        if (!class_exists($elementType) || !is_subclass_of($elementType, ElementInterface::class)) {
            return [];
        }

        // Fetch the most recent elements of the specified type
        $elements = $elementType::find()
            ->orderBy(['dateCreated' => SORT_DESC])
            ->limit($limit)
            ->all();

        // Build response array with favourite status for each element
        $elementData = [];
        foreach ($elements as $element) {
            $favourite = null;

            // Check if element is favourited using element query
            if ($userId) {
                $favourite = FavouriteItem::find()
                    ->userId($userId)
                    ->collectionId($collectionId)
                    ->elementId($element->id)
                    ->one();
            }

            // Build element data object
            $data = [
                'id' => $element->id,
                'title' => $element->title ?? 'Untitled',
                'url' => $element->getUrl(),
                'type' => get_class($element),
                'isFavourited' => $favourite !== null,
                'favouriteId' => $favourite?->id ?? null,
            ];

            // Add preview URL for asset elements
            if ($element instanceof \craft\elements\Asset) {
                $data['previewUrl'] = $element->getUrl();
            }

            $elementData[] = $data;
        }

        return $elementData;
    }
}

