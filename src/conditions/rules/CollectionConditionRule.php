<?php
namespace amici\SuperFavourite\conditions\rules;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;
use amici\SuperFavourite\elements\db\FavouriteItemQuery;

/**
 * Collection Condition Rule
 *
 * Allows filtering favourite items by the collection they belong to
 */
class CollectionConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('super-favourite', 'Favourite Collection');
    }

    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return Collection::class;
    }

    /**
     * @inheritdoc
     */
    protected function allowMultiple(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['collectionId'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var FavouriteItemQuery $query */
        $query->collectionId($this->getElementIds());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var FavouriteItem $element */
        return $this->matchValue($element->collectionId);
    }
}

