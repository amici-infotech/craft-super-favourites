<?php
namespace amici\SuperFavourite\conditions;

use craft\elements\conditions\ElementCondition;
use amici\SuperFavourite\conditions\rules\UserConditionRule;
use amici\SuperFavourite\conditions\rules\CollectionConditionRule;
use amici\SuperFavourite\conditions\rules\FavouritedElementConditionRule;

/**
 * Favourite Item Condition
 *
 * Defines available condition rules for filtering favourite items in the element index
 */
class FavouriteItemCondition extends ElementCondition
{
    /**
     * Runs the `selectableConditionRules()` method for this plugin class.
     *
     * @return array The requested array of data.
     */
    protected function selectableConditionRules(): array
    {
        return array_merge(parent::selectableConditionRules(), [
            UserConditionRule::class,
            CollectionConditionRule::class,
            FavouritedElementConditionRule::class,
        ]);
    }
}

