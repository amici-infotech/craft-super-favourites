<?php
namespace amici\SuperFavourite\conditions\rules;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\db\CollectionQuery;

/**
 * Collection User Condition Rule
 *
 * Allows filtering collections by the user who owns them
 */
class CollectionUserConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return Craft::t('super-favourite', 'User');
    }

    /**
     * @inheritdoc
     */
    protected function elementType(): string
    {
        return User::class;
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
        return ['userId'];
    }

    /**
     * @inheritdoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var CollectionQuery $query */
        $query->userId($this->getElementIds());
    }

    /**
     * @inheritdoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Collection $element */
        return $this->matchValue($element->userId);
    }
}

