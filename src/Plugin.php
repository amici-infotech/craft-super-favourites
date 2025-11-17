<?php
/**
 * Super Favourite plugin for Craft CMS 5.x
 *
 * A powerful wishlist/favourite plugin that allows users to favourite any element type with collections support
 *
 * @link      https://amiciinfotech.com
 * @copyright Copyright (c) 2024 Amici Infotech
 */

namespace amici\SuperFavourite;

use Craft;
use yii\base\Event;

use amici\SuperFavourite\base\PluginTrait;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;
use amici\SuperFavourite\models\Settings;
use amici\SuperFavourite\services\App;
use amici\SuperFavourite\variables\SuperFavouriteVariable;

use craft\base\Model;
use craft\base\Plugin as CraftPlugin;
use craft\console\Application as ConsoleApplication;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\Elements;
use craft\services\UserPermissions;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

/**
 * Super Favourite Plugin
 *
 * @author    Amici Infotech
 * @package   SuperFavourite
 * @since     5.0.0
 *
 * @property  Settings $settings
 * @method    Settings getSettings()
 */
class Plugin extends CraftPlugin
{
    use PluginTrait;

    // Static Properties
    // =========================================================================

    /**
     * @var App
     */
    public static $app;

    /**
     * @var Plugin
     */
    public static CraftPlugin $plugin;

    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public string $schemaVersion = '5.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @var string
     */
    public static string $pluginHandle = 'super-favourite';

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;
        self::$app = new App();

        // Register elements
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = Collection::class;
                $event->types[] = FavouriteItem::class;
            }
        );

        // Add in our console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'amici\SuperFavourite\console\controllers';
        }

        $this->_registerRoutes();
        $this->_registerVariables();
        $this->_setPluginComponents();
        $this->_registerPermissions();

        Craft::info(
            Craft::t(
                'super-favourite',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('super-favourite/settings'));
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $parent = parent::getCpNavItem();
        $parent['label'] = $this->getSettings()->pluginName;

        if (Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            $parent['url'] = 'super-favourite';

            $parent['subnav']['collections'] = [
                'label' => Craft::t('super-favourite', 'Collections'),
                'url' => 'super-favourite/collections',
            ];

            $parent['subnav']['favourites'] = [
                'label' => Craft::t('super-favourite', 'Favourites'),
                'url' => 'super-favourite/favourites',
            ];

            $parent['subnav']['settings'] = [
                'label' => Craft::t('super-favourite', 'Settings'),
                'url' => 'super-favourite/settings',
            ];
        }

        return $parent;
    }

    // Private Methods
    // =========================================================================

    /**
     * Register CP URL rules
     */
    private function _registerRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules = array_merge($event->rules, [
                    // Collections
                    'super-favourite' => 'super-favourite/collection/index',
                    'super-favourite/collections' => 'super-favourite/collection/index',
                    'super-favourite/collections/new' => 'super-favourite/collection/edit',
                    'super-favourite/collections/<collectionId:\d+>' => 'super-favourite/collection/edit',

                    // Favourites - use element index template
                    'super-favourite/favourites' => 'super-favourite/favourite/index',
                    'super-favourite/favourites/new' => 'super-favourite/favourite/edit',
                    'super-favourite/favourites/<favouriteId:\d+>' => 'super-favourite/favourite/edit',

                    // Settings
                    'super-favourite/settings' => 'super-favourite/settings/index',
                    'super-favourite/settings/general' => 'super-favourite/settings/general',
                    'super-favourite/settings/collection-fields' => 'super-favourite/settings/collection-fields',
                    'super-favourite/settings/favourite-fields' => 'super-favourite/settings/favourite-fields',
                ]);

                // Site routes
                $event->rules = array_merge($event->rules, [
                    'actions/super-favourite/favourite/add' => 'super-favourite/favourite/add',
                    'actions/super-favourite/favourite/remove' => 'super-favourite/favourite/remove',
                    'actions/super-favourite/favourite/toggle' => 'super-favourite/favourite/toggle',
                    'actions/super-favourite/favourite/check' => 'super-favourite/favourite/check',
                    'actions/super-favourite/favourite/move' => 'super-favourite/favourite/move',
                ]);
            }
        );
    }

    /**
     * Register template variables
     */
    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('superFavourite', SuperFavouriteVariable::class);
            }
        );
    }

    /**
     * Register user permissions
     */
    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t('super-favourite', 'Super Favourite'),
                    'permissions' => [
                        'super-favourite:view-favourites' => [
                            'label' => Craft::t('super-favourite', 'View favourites'),
                        ],
                        'super-favourite:manage-favourites' => [
                            'label' => Craft::t('super-favourite', 'Manage favourites'),
                        ],
                        'super-favourite:view-collections' => [
                            'label' => Craft::t('super-favourite', 'View collections'),
                        ],
                        'super-favourite:manage-collections' => [
                            'label' => Craft::t('super-favourite', 'Manage collections'),
                        ],
                    ],
                ];
            }
        );
    }
}

