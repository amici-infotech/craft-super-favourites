<?php
namespace amici\SuperFavourite\variables;

use Craft;
use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;
use amici\SuperFavourite\elements\db\FavouriteItemQuery;

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
     * Checks whether a user has favourited an element.
     *
     * @param int $elementId The ID of the Craft element being favourited or checked.
     * @param ?int $collectionId The ID of the collection element.
     *
     * @return bool True on success or when the condition matches; false otherwise.
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
     * Returns IDs for elements favourited by a user.
     *
     * @param ?int $collectionId The ID of the collection element.
     * @param ?string $elementType The fully qualified class name of the Craft element type.
     *
     * @return array The requested array of data.
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
     * Returns favourite item elements for a user and optional filters.
     *
     * @param ?int $collectionId The ID of the collection element.
     * @param ?string $elementType The fully qualified class name of the Craft element type.
     *
     * @return array The requested array of data.
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
     * Counts favourite items for a user and optional collection.
     *
     * @param ?int $collectionId The ID of the collection element.
     *
     * @return int The requested integer value.
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
     * Returns the collections value.
     *
     * @return array The requested array of data.
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
     * Returns a collection by handle for a user.
     *
     * @param string $handle The collection handle to save, filter by, or test for uniqueness.
     *
     * @return ?Collection The `?Collection` value produced by this method.
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
     * Returns the default collection, or null if none exists.
     *
     * @return ?Collection The `?Collection` value produced by this method.
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
     * Counts collections for a user.
     *
     * @return int The requested integer value.
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
     * Creates a favourite item query for Twig templates.
     *
     * @return mixed A favourite item query ready for template chaining.
     */
    public function favourites()
    {
        return FavouriteItem::find();
    }

    /**
     * Creates a collection query for Twig templates.
     *
     * @return mixed A collection query ready for template chaining.
     */
    public function collections()
    {
        return Collection::find();
    }

    /**
     * Creates a favourite item query for Twig templates.
     *
     * @return mixed A favourite item query ready for template chaining.
     */
    public function favouriteItems()
    {
        return FavouriteItem::find();
    }

    /**
     * Returns element types that can be favourited.
     *
     * @return array The requested array of data.
     */
    public function getAvailableElementTypes(): array
    {
        $elementTypes = [];

        // Get all registered element types
        $allTypes = Craft::$app->getElements()->getAllElementTypes();

        // Define excluded element types (internal/system types that shouldn't be favourited)
        $excludedTypeClasses = [
            // Craft core internal types
            \craft\elements\GlobalSet::class,
            \craft\elements\ContentBlock::class,
            \craft\elements\ElementCollection::class,
            \craft\elements\NestedElementManager::class,
            // Our plugin types
            Collection::class,
            FavouriteItem::class,
            // Commerce internal types
            \craft\commerce\elements\Variant::class,
            \craft\commerce\elements\Donation::class,
            \craft\commerce\elements\Transfer::class,
        ];

        // Filter to only classes that exist
        $excludedTypes = array_filter($excludedTypeClasses, function($class) {
            return class_exists($class);
        });

        // Build array using each element type's displayName() method
        foreach ($allTypes as $elementType) {
            // Skip excluded types
            if (in_array($elementType, $excludedTypes)) {
                continue;
            }

            $elementTypes[] = [
                'value' => $elementType,
                'label' => $elementType::displayName(),
            ];
        }

        // Sort by label
        usort($elementTypes, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $elementTypes;
    }

    /**
     * Creates an element query for a requested element type.
     *
     * @param string $elementType The fully qualified class name of the Craft element type.
     *
     * @return mixed An element query for the type, or null when invalid.
     */
    public function createElementQuery(string $elementType)
    {
        // Validate the element type exists
        if (!class_exists($elementType)) {
            return null;
        }

        // Check if it's a valid element class
        if (!is_subclass_of($elementType, \craft\base\ElementInterface::class)) {
            return null;
        }

        try {
            // Call the static find() method on the element type
            return $elementType::find();
        } catch (\Exception $e) {
            Craft::error('Failed to create element query for type: ' . $elementType . ' - ' . $e->getMessage(), __METHOD__);
            return null;
        }
    }
}

