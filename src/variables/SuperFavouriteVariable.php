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
     * Returns a favourite item query
     *
     * @return \amici\SuperFavourite\elements\db\FavouriteItemQuery
     */
    public function favouriteItems()
    {
        return FavouriteItem::find();
    }

    /**
     * Get all available element types
     *
     * Returns an array of element types with their display names
     * Format: [['value' => 'craft\\elements\\Entry', 'label' => 'Entries'], ...]
     *
     * @return array
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
            \amici\SuperFavourite\elements\Collection::class,
            \amici\SuperFavourite\elements\FavouriteItem::class,
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
     * Create an element query for a given element type
     * Returns the element query (::find()) so it can be enhanced from the template
     *
     * @param string $elementType The element type class name
     * @return \craft\elements\db\ElementQuery|null
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

