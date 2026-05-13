<?php
namespace amici\SuperFavourite\conditions\rules;

use Craft;
use craft\base\conditions\BaseMultiSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use amici\SuperFavourite\elements\db\FavouriteItemQuery;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Favourited Element Condition Rule
 *
 * Allows filtering favourite items by the element that was favourited
 * This is a multi-select dropdown of available element types
 */
class FavouritedElementConditionRule extends BaseMultiSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * Returns the label shown for this condition rule.
     *
     * @return string The requested string value.
     */
    public function getLabel(): string
    {
        return Craft::t('super-favourite', 'Favourite Element');
    }

    /**
     * Returns query params that this condition rule owns.
     *
     * @return array The requested array of data.
     */
    public function getExclusiveQueryParams(): array
    {
        return ['favouritedElementType'];
    }

    /**
     * Builds the options for this multi-select condition rule.
     *
     * @return array The requested array of data.
     */
    protected function options(): array
    {
        $options = [];

        // Get all registered element types
        $allElementTypes = Craft::$app->getElements()->getAllElementTypes();

        foreach ($allElementTypes as $elementType) {
            // Skip internal types
            if ($elementType === FavouriteItem::class ||
                $elementType === Collection::class) {
                continue;
            }

            $options[] = [
                'label' => $elementType::displayName(),
                'value' => $elementType,
            ];
        }

        // Sort by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * Applies this condition rule to an element query.
     *
     * @param ElementQueryInterface $query The Craft element query being modified or processed.
     *
     * @return void Nothing is returned.
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var FavouriteItemQuery $query */
        $query->favouritedElementType($this->getValues());
    }

    /**
     * Checks whether a loaded element matches this condition rule.
     *
     * @param ElementInterface $element The Craft element being checked or duplicated.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var FavouriteItem $element */
        return $this->matchValue($element->elementType);
    }
}

