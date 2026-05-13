<?php
namespace amici\SuperFavourite\base;

use amici\SuperFavourite\services\FavouriteService;
use amici\SuperFavourite\services\CollectionService;

/**
 * Plugin Trait
 *
 * Sets up plugin services
 */
trait PluginTrait
{
    /**
     * Registers the plugin services as Yii components.
     *
     * @return void Nothing is returned.
     */
    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'favourite' => FavouriteService::class,
            'collection' => CollectionService::class,
        ]);
    }

    /**
     * Returns the favourite service used for add/remove/query operations.
     *
     * @return FavouriteService The `FavouriteService` value produced by this method.
     */
    public function getFavourite(): FavouriteService
    {
        return $this->get('favourite');
    }

    /**
     * Returns the related collection element, caching the lookup for this request.
     *
     * @return CollectionService The `CollectionService` value produced by this method.
     */
    public function getCollection(): CollectionService
    {
        return $this->get('collection');
    }
}

