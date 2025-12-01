<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Settings Controller
 */
class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Settings index page - redirects to general settings
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->redirect('super-favourite/settings/general');
    }

    /**
     * General settings page
     *
     * @return Response
     */
    public function actionGeneral(): Response
    {
        $this->requirePermission('accessPlugin-super-favourite');

        $settings = Plugin::getInstance()->getSettings();

        return $this->renderTemplate('super-favourite/settings/general', [
            'settings' => $settings,
            'selectedSubnavItem' => 'general',
        ]);
    }

    /**
     * Collection field layout settings page
     *
     * @return Response
     */
    public function actionCollectionFields(): Response
    {
        $this->requirePermission('accessPlugin-super-favourite');

        $fieldLayout = Craft::$app->getFields()->getLayoutByType(Collection::class);

        return $this->renderTemplate('super-favourite/settings/collection-fields', [
            'fieldLayout' => $fieldLayout,
            'selectedSubnavItem' => 'collection-fields',
        ]);
    }

    /**
     * Favourite item field layout settings page
     *
     * @return Response
     */
    public function actionFavouriteFields(): Response
    {
        $this->requirePermission('accessPlugin-super-favourite');

        $fieldLayout = Craft::$app->getFields()->getLayoutByType(FavouriteItem::class);

        return $this->renderTemplate('super-favourite/settings/favourite-fields', [
            'fieldLayout' => $fieldLayout,
            'selectedSubnavItem' => 'favourite-fields',
        ]);
    }

    /**
     * Save general settings
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('accessPlugin-super-favourite');

        // Block saving if admin changes aren't allowed
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Changes to these settings aren\'t permitted in this environment.'));
            return null;
        }

        $request = Craft::$app->getRequest();
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();

        $settings->pluginName = $request->getBodyParam('pluginName', $settings->pluginName);
        $settings->maxCollectionsPerUser = (int)$request->getBodyParam('maxCollectionsPerUser', 0);
        $settings->maxFavouritesPerCollection = (int)$request->getBodyParam('maxFavouritesPerCollection', 0);

        if (!Craft::$app->getPlugins()->savePluginSettings($plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Couldn\'t save settings.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Save collection field layout
     *
     * @return Response|null
     */
    public function actionSaveCollectionFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('accessPlugin-super-favourite');

        // Block saving if admin changes aren't allowed (field layouts are project config)
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Changes to these settings aren\'t permitted in this environment.'));
            return null;
        }

        $fieldsService = Craft::$app->getFields();

        // Assemble the field layout from the posted data
        // The namespace matches the {% namespace 'fieldLayout' %} in the template
        $fieldLayout = $fieldsService->assembleLayoutFromPost('fieldLayout');
        $fieldLayout->type = Collection::class;

        // Save the field layout
        if (!$fieldsService->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Couldn\'t save field layout.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Field layout saved.'));

        return $this->redirectToPostedUrl();
    }

    /**
     * Save favourite item field layout
     *
     * @return Response|null
     */
    public function actionSaveFavouriteFieldLayout(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('accessPlugin-super-favourite');

        // Block saving if admin changes aren't allowed (field layouts are project config)
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Changes to these settings aren\'t permitted in this environment.'));
            return null;
        }

        $fieldsService = Craft::$app->getFields();

        // Assemble the field layout from the posted data
        $fieldLayout = $fieldsService->assembleLayoutFromPost('fieldLayout');
        $fieldLayout->type = FavouriteItem::class;

        // Save the field layout
        if (!$fieldsService->saveLayout($fieldLayout)) {
            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Couldn\'t save field layout.'));
            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Field layout saved.'));

        return $this->redirectToPostedUrl();
    }
}

