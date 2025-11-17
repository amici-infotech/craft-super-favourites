<?php
namespace amici\SuperFavourite\models;

use craft\base\Model;

/**
 * Super Favourite Settings Model
 */
class Settings extends Model
{
    /**
     * @var string The plugin name as shown in the CP
     */
    public string $pluginName = 'Super Favourite';

    /**
     * @var int Maximum number of collections per user (0 = unlimited)
     */
    public int $maxCollectionsPerUser = 0;

    /**
     * @var int Maximum number of favourites per collection (0 = unlimited)
     */
    public int $maxFavouritesPerCollection = 0;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['pluginName'], 'string'],
            [['maxCollectionsPerUser', 'maxFavouritesPerCollection'], 'integer', 'min' => 0],
        ];
    }
}

