<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;

/**
 * Collection Controller
 *
 * Handles collection management in the CP
 */
class CollectionController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Collections index page
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('super-favourite/collections/index');
    }

    /**
     * Edit collection page
     *
     * @param int|null $collectionId
     * @param Collection|null $collection
     * @return Response
     */
    public function actionEdit(?int $collectionId = null, ?Collection $collection = null): Response
    {
        $this->requirePermission('super-favourite:manage-collections');

        if ($collection === null && $collectionId !== null) {
            $collection = Collection::find()->id($collectionId)->one();

            if (!$collection) {
                throw new \yii\web\NotFoundHttpException('Collection not found');
            }
        }

        if ($collection === null) {
            $collection = new Collection();
            // Default to global collection (no user assigned)
            $collection->userId = null;
        }

        // Prepare tabs for the form
        $tabs = [];

        // Main tab with the basic fields
        $tabs['collection-details'] = [
            'label' => Craft::t('super-favourite', 'Collection Details'),
            'url' => '#collection-details',
        ];

        // Add tabs from the field layout
        $fieldLayout = $collection->getFieldLayout();
        foreach ($fieldLayout->getTabs() as $tab) {
            $tabs[$tab->getHtmlId()] = [
                'label' => Craft::t('site', $tab->name),
                'url' => '#' . $tab->getHtmlId(),
            ];
        }

        return $this->renderTemplate('super-favourite/collections/_edit', [
            'collection' => $collection,
            'isNew' => !$collection->id,
            'tabs' => $tabs,
        ]);
    }

    /**
     * Save collection
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionId = $request->getBodyParam('collectionId');

        if ($collectionId) {
            $collection = Collection::find()->id($collectionId)->one();

            if (!$collection) {
                throw new \yii\web\NotFoundHttpException('Collection not found');
            }
        } else {
            $collection = new Collection();
            $collection->userId = Craft::$app->getUser()->getId();
        }

        // Set site ID for content
        $collection->siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Set field layout ID
        $fieldLayout = $collection->getFieldLayout();
        if ($fieldLayout) {
            $collection->fieldLayoutId = $fieldLayout->id;
        }

        // Set attributes
        $collection->name = $request->getBodyParam('name');
        $collection->handle = $request->getBodyParam('handle');
        $collection->description = $request->getBodyParam('description');
        $collection->isDefault = (bool)$request->getBodyParam('isDefault', false);

        // Get userId from element select field
        // If no user selected, userId stays null = global collection
        $userIds = $request->getBodyParam('userId');
        if (is_array($userIds) && !empty($userIds)) {
            $collection->userId = (int)$userIds[0];
        } else {
            $collection->userId = null;
        }

        // Set custom field values from the 'fields' namespace
        $collection->setFieldValuesFromRequest('fields');

        // Set scenario to LIVE for proper content saving
        $collection->setScenario(\craft\base\Element::SCENARIO_LIVE);

        // Save it
        if (!Craft::$app->getElements()->saveElement($collection)) {
            // Get validation errors
            $errors = $collection->getErrors();
            $errorMessage = Craft::t('super-favourite', 'Couldn\'t save collection.');

            if (!empty($errors)) {
                $errorList = [];
                foreach ($errors as $field => $fieldErrors) {
                    $errorList[] = implode(', ', $fieldErrors);
                }
                $errorMessage .= ' ' . implode(' ', $errorList);
            }

            Craft::$app->getSession()->setError($errorMessage);

            Craft::$app->getUrlManager()->setRouteParams([
                'collection' => $collection,
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Collection saved.'));

        return $this->redirectToPostedUrl($collection);
    }

    /**
     * Delete collection
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionId = $request->getRequiredBodyParam('collectionId');

        $success = Plugin::getInstance()->collection->deleteCollection(
            (int)$collectionId,
            (bool)$request->getBodyParam('deleteItems', false)
        );

        if ($success) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => Craft::t('super-favourite', 'Collection deleted.')
                ]);
            }

            Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Collection deleted.'));
            return $this->redirectToPostedUrl();
        }

        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Failed to delete collection.')
            ]);
        }

        Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Failed to delete collection.'));
        return $this->redirectToPostedUrl();
    }

    /**
     * Set default collection
     *
     * @return Response
     */
    public function actionSetDefault(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionId = $request->getRequiredBodyParam('collectionId');

        $success = Plugin::getInstance()->collection->setDefaultCollection((int)$collectionId);

        return $this->asJson([
            'success' => $success,
            'message' => $success
                ? Craft::t('super-favourite', 'Default collection set.')
                : Craft::t('super-favourite', 'Failed to set default collection.')
        ]);
    }

    /**
     * Reorder collections
     *
     * @return Response
     */
    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionIds = $request->getRequiredBodyParam('ids');

        $success = Plugin::getInstance()->collection->reorderCollections($collectionIds);

        return $this->asJson([
            'success' => $success
        ]);
    }
}

