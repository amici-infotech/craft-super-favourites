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
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('super-favourite', 'Favourite Element');
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['favouritedElementType'];
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var FavouriteItemQuery $query */
        $query->favouritedElementType($this->getValues());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var FavouriteItem $element */
        return $this->matchValue($element->elementType);
    }
}

