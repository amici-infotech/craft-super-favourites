<?php
namespace amici\SuperFavourite\conditions;

use craft\elements\conditions\ElementCondition;
use amici\SuperFavourite\conditions\rules\CollectionUserConditionRule;

/**
 * Collection Condition
 *
 * Defines available condition rules for filtering collections in the element index
 */
class CollectionCondition extends ElementCondition
{
    /**
     * Runs the `selectableConditionRules()` method for this plugin class.
     *
     * @return array The requested array of data.
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            CollectionUserConditionRule::class,
        ]);
    }
}

