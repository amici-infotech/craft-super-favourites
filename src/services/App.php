<?php
namespace amici\SuperFavourite\services;

use craft\base\Component;

/**
 * App Service
 *
 * Container for all plugin services
 */
class App extends Component
{
    public $favourite;
    public $collection;
    public $settings;

    public function init(): void
    {
        parent::init();

        $this->favourite = new FavouriteService();
        $this->collection = new CollectionService();
        $this->settings = new Settings();
    }
}

