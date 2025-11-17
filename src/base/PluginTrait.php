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
     * Register plugin components
     */
    private function _setPluginComponents(): void
    {
        $this->setComponents([
            'favourite' => FavouriteService::class,
            'collection' => CollectionService::class,
        ]);
    }

    /**
     * Get the favourite service
     *
     * @return FavouriteService
     */
    public function getFavourite(): FavouriteService
    {
        return $this->get('favourite');
    }

    /**
     * Get the collection service
     *
     * @return CollectionService
     */
    public function getCollection(): CollectionService
    {
        return $this->get('collection');
    }
}

