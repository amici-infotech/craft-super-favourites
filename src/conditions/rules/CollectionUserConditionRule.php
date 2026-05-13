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
     * Returns the label shown for this condition rule.
     *
     * @return string The requested string value.
     */
    public function getLabel(): string
    {
        return Craft::t('super-favourite', 'User');
    }

    /**
     * Returns the element type that this select rule should target.
     *
     * @return string The requested string value.
     */
    protected function elementType(): string
    {
        return User::class;
    }

    /**
     * Tells Craft whether multiple selections are allowed for this rule.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    protected function allowMultiple(): bool
    {
        return true;
    }

    /**
     * Returns query params that this condition rule owns.
     *
     * @return array The requested array of data.
     */
    public function getExclusiveQueryParams(): array
    {
        return ['userId'];
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
        /** @var CollectionQuery $query */
        $query->userId($this->getElementIds());
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
        /** @var Collection $element */
        return $this->matchValue($element->userId);
    }
}

