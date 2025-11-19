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
     * Check if a favourite already exists using direct database query
     *
     * This method uses a direct database query instead of element queries to avoid
     * caching issues and ensure reliable duplicate detection, especially important
     * for preventing race conditions during rapid favourite toggling.
     *
     * @param int $userId The user ID
     * @param int|null $collectionId The collection ID (can be null)
     * @param int $elementId The element ID being favourited
     * @return array|null Returns the favourite record if exists, null otherwise
     */
    public function checkDuplicate(int $userId, ?int $collectionId, int $elementId): ?array
    {
        return (new \yii\db\Query())
            ->select(['id'])
            ->from('{{%super_favourite_items}}')
            ->where([
                'userId' => $userId,
                'collectionId' => $collectionId,
                'elementId' => $elementId,
            ])
            ->one();
    }

    /**
     * Add an element to favourites
     *
     * Creates a new favourite item linking a user, collection, and element.
     * If the item already exists, returns the existing favourite instead of creating a duplicate.
     * Triggers BEFORE and AFTER events for extensibility.
     *
     * @param int $elementId The ID of the element to favourite
     * @param string $elementType The fully qualified class name of the element type (e.g., craft\elements\Entry)
     * @param int|null $userId The user ID (defaults to current logged-in user)
     * @param int|null $collectionId The collection ID (defaults to user's default collection)
     * @param string|null $notes Optional notes about why this item was favourited
     * @return FavouriteItem|false The created/existing favourite item, or false on failure
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
            return FavouriteItem::find()->id($existing['id'])->one();
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
     * Remove an element from favourites
     *
     * Deletes one or more favourite items matching the criteria. Can perform either
     * soft delete (moves to trash) or hard delete (permanently removes from database).
     * Hard delete is recommended to prevent soft-deleted records from interfering with duplicate checks.
     *
     * @param int $elementId The ID of the element to unfavourite
     * @param int|null $userId The user ID (defaults to current logged-in user)
     * @param int|null $collectionId The collection ID (null = remove from all collections)
     * @param bool $hardDelete Whether to permanently delete (true) or soft delete (false)
     * @return bool True if all matching favourites were successfully removed
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
     * Check if an element is favourited
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
     * Get all favourited element IDs for a user
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
     * Get all favourite items for a user
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
     * Get or create a default collection for a user
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
     * Move a favourite to another collection
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
     * Get count of favourites for a user
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
     * Get current user ID or null
     *
     * Helper method to safely retrieve the current logged-in user's ID.
     *
     * @return int|null The user ID if logged in, null otherwise
     */
    protected function getCurrentUserId(): ?int
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        return $currentUser ? $currentUser->id : null;
    }

    /**
     * Toggle favourite status (add if not exists, remove if exists)
     *
     * This is the main method used by the frontend toggle functionality. It handles
     * the complete toggle logic including duplicate prevention, hard deletion, and
     * fallback SQL deletion if element deletion fails. Uses direct database queries
     * for reliability and to prevent race conditions during rapid clicking.
     *
     * @param int $elementId The ID of the element to toggle
     * @param string $elementType The fully qualified class name of the element type
     * @param int $collectionId The collection ID where the favourite should be toggled
     * @param int|null $userId The user ID (defaults to current logged-in user)
     * @return array Response array with 'success', 'action' (added/removed), and relevant IDs
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

        // Check if favourite already exists using direct database query
        $existing = $this->checkDuplicate($userId, $collectionId, $elementId);

        // If exists, remove it
        if ($existing) {
            // Try to find and delete using element service (includes soft-deleted records)
            $favouriteItem = FavouriteItem::find()
                ->id($existing['id'])
                ->status(null)
                ->trashed(null)
                ->one();

            // Attempt hard delete via element service first
            if ($favouriteItem && Craft::$app->getElements()->deleteElement($favouriteItem, true)) {
                return [
                    'success' => true,
                    'action' => 'removed',
                    'favouriteId' => null,
                    'elementId' => $elementId,
                    'elementType' => $elementType,
                    'collectionId' => $collectionId,
                ];
            }

            // Fallback: Direct SQL deletion if element service fails
            // This ensures the record is fully removed from database
            (new \yii\db\Query())
                ->createCommand()
                ->delete('{{%super_favourite_items}}', ['id' => $existing['id']])
                ->execute();

            (new \yii\db\Query())
                ->createCommand()
                ->delete('{{%elements}}', ['id' => $existing['id']])
                ->execute();

            return [
                'success' => true,
                'action' => 'removed',
                'favouriteId' => null,
                'elementId' => $elementId,
                'elementType' => $elementType,
                'collectionId' => $collectionId,
            ];
        }

        // Double-check before adding to prevent race conditions
        $doubleCheck = $this->checkDuplicate($userId, $collectionId, $elementId);
        if ($doubleCheck) {
            return [
                'success' => false,
                'error' => 'Favourite already exists',
                'favouriteId' => $doubleCheck['id'],
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
     * Get elements with their favourite status for a user
     *
     * Retrieves a list of elements of the specified type with additional information about
     * whether each element is favourited by the user in the given collection. This is
     * optimized for frontend display where we need both element data and favourite status.
     * Uses direct database queries for favourite checking to ensure reliability.
     *
     * @param string $elementType The fully qualified class name of the element type to fetch
     * @param int $collectionId The collection ID to check favourite status against
     * @param int|null $userId The user ID (defaults to current logged-in user, null for guest)
     * @param int $limit Maximum number of elements to return (default 10)
     * @return array Array of element data with favourite status, each containing:
     *               - id: Element ID
     *               - title: Element title
     *               - url: Element URL
     *               - isFavourited: Boolean indicating if favourited
     *               - favouriteId: The favourite item ID if favourited, null otherwise
     *               - previewUrl: For assets, the URL for preview image
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

            // Check if element is favourited using direct database query
            if ($userId) {
                $favourite = (new \yii\db\Query())
                    ->select(['id'])
                    ->from('{{%super_favourite_items}}')
                    ->where([
                        'userId' => $userId,
                        'collectionId' => $collectionId,
                        'elementId' => $element->id,
                    ])
                    ->one();
            }

            // Build element data object
            $data = [
                'id' => $element->id,
                'title' => $element->title ?? 'Untitled',
                'url' => $element->getUrl(),
                'isFavourited' => $favourite !== null,
                'favouriteId' => $favourite['id'] ?? null,
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

