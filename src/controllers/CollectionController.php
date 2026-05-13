<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Collection Controller
 */
class CollectionController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

    /**
     * Handles the index controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('super-favourite/collections/index');
    }

    /**
     * Handles the edit controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @param ?int $collectionId The ID of the collection element.
     * @param ?Collection $collection An existing collection passed back after validation, or null for a fresh load.
     *
     * @return Response The HTTP response Craft should send.
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
            $collection->userId = null;
        }

        $tabs = [];
        $tabs['collection-details'] = [
            'label' => Craft::t('super-favourite', 'Collection Details'),
            'url' => '#collection-details',
        ];

        $fieldLayout = $collection->getFieldLayout();
        foreach ($fieldLayout->getTabs() as $tab) {
            $tabs[$tab->getHtmlId()] = [
                'label' => Craft::t('site', $tab->name),
                'url' => '#' . $tab->getHtmlId(),
            ];
        }
        $allElementTypes = Craft::$app->getElements()->getAllElementTypes();
        $elementTypeOptions = [];
        foreach ($allElementTypes as $elementType) {
            if ($elementType === Collection::class ||
                $elementType === FavouriteItem::class) {
                continue;
            }
            $elementTypeOptions[] = [
                'label' => $elementType::pluralDisplayName(),
                'value' => $elementType,
            ];
        }

        return $this->renderTemplate('super-favourite/collections/_edit', [
            'collection' => $collection,
            'isNew' => !$collection->id,
            'tabs' => $tabs,
            'elementTypeOptions' => $elementTypeOptions,
        ]);
    }

    /**
     * Handles the save controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return ?Response The HTTP response Craft should send, or null to redisplay the model with errors.
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionId = $request->getBodyParam('id') ?? $request->getBodyParam('collectionId');

        if ($collectionId) {
            $collection = Collection::find()->id($collectionId)->one();

            if (!$collection) {
                throw new \yii\web\NotFoundHttpException('Collection not found');
            }
        } else {
            $collection = new Collection();
            // Don't set userId here - will be set from POST data below
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

        // Only set handle if it's not empty (allow auto-generation in beforeSave)
        $handle = $request->getBodyParam('handle');
        if (!empty($handle)) {
            $collection->handle = $handle;
        }

        $collection->description = $request->getBodyParam('description');
        $collection->isDefault = (bool)$request->getBodyParam('isDefault', false);

        // Get userId from POST
        // Can be either from element select field (array) or hidden input (string/int)
        $userIds = $request->getBodyParam('userId');
        if (is_array($userIds) && !empty($userIds)) {
            // From element select field (CP form)
            $collection->userId = (int)$userIds[0];
        } elseif (!empty($userIds) && $userIds !== '' && $userIds !== null) {
            // From hidden input (frontend form) - convert to int
            $collection->userId = (int)$userIds;
        } else {
            // Empty or not provided = global collection
            $collection->userId = null;
        }

        // Get allowed element types from checkboxes.
        // Empty array means all element types are allowed.
        $allowedElementTypes = $request->getBodyParam('allowedElementTypes');

        if (is_array($allowedElementTypes)) {
            $allowedElementTypes = array_filter($allowedElementTypes, function($val) {
                return !empty($val) && $val !== '*';
            });

            $collection->allowedElementTypes = array_values($allowedElementTypes);
        } else {
            $collection->allowedElementTypes = [];
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
     * Handles the delete controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $collectionId = $request->getBodyParam('id') ?? $request->getBodyParam('collectionId');
        if (!$collectionId) {
            throw new \yii\web\BadRequestHttpException('Missing required body parameter: id');
        }

        // Get the collection to check ownership
        $collection = Collection::find()->id($collectionId)->one();

        if (!$collection) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('super-favourite', 'Collection not found.')
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'Collection not found.'));
            return $this->redirectToPostedUrl();
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        // Check if user can delete:
        // - Admins can delete anything
        // - Users can delete their own collections
        // - Users can delete global collections (userId is null)
        $canDelete = $currentUser->admin
                     || $collection->userId === $currentUser->id
                     || $collection->userId === null;

        if (!$canDelete) {
            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => Craft::t('super-favourite', 'You do not have permission to delete this collection.')
                ]);
            }

            Craft::$app->getSession()->setError(Craft::t('super-favourite', 'You do not have permission to delete this collection.'));
            return $this->redirectToPostedUrl();
        }

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
     * Handles the set default controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionSetDefault(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('super-favourite:manage-collections');

        $request = Craft::$app->getRequest();
        $collectionId = $request->getBodyParam('id') ?? $request->getBodyParam('collectionId');
        if (!$collectionId) {
            throw new \yii\web\BadRequestHttpException('Missing required body parameter: id');
        }

        $success = Plugin::getInstance()->collection->setDefaultCollection((int)$collectionId);

        return $this->asJson([
            'success' => $success,
            'message' => $success
                ? Craft::t('super-favourite', 'Default collection set.')
                : Craft::t('super-favourite', 'Failed to set default collection.')
        ]);
    }

    /**
     * Handles the reorder controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
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

