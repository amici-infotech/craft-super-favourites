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
    public FavouriteService $favourite;
    public CollectionService $collection;
    public Settings $settings;

    /**
     * Initializes the plugin/component and wires together its dependent services or registrations.
     *
     * @return void Nothing is returned.
     */
    public function init(): void
    {
        parent::init();

        $this->favourite = new FavouriteService();
        $this->collection = new CollectionService();
        $this->settings = new Settings();
    }
}

