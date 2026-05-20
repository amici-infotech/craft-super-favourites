<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\elements\User;
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
        $currentUser = Craft::$app->getUser()->getIdentity();
        $canManageGlobalCollections = $this->canManageGlobalCollections($currentUser);

        if ($collection === null && $collectionId !== null) {
            $collection = Collection::find()
                ->id($collectionId)
                ->status(null)
                ->one();

            if (!$collection) {
                throw new \yii\web\NotFoundHttpException('Collection not found');
            }

            if ($collection->userId === null && !$canManageGlobalCollections) {
                throw new \yii\web\ForbiddenHttpException('You do not have permission to edit global collections.');
            }
        }

        if ($collection === null) {
            $collection = new Collection();
            $collection->userId = $canManageGlobalCollections ? null : $currentUser->id;
        }

        if ($collection->id && $collection->userId === null && !$canManageGlobalCollections) {
            throw new \yii\web\ForbiddenHttpException('You do not have permission to edit global collections.');
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
            'canManageGlobalCollections' => $canManageGlobalCollections,
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
            $collection = Collection::find()
                ->id($collectionId)
                ->status(null)
                ->one();

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
        if ($request->getBodyParam('enabled') !== null) {
            $collection->enabled = (bool)$request->getBodyParam('enabled');
        }

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

        if ($collection->isDefault) {
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
        /** @var \craft\base\Model $collection */
        $collection->setScenario(\craft\base\Element::SCENARIO_LIVE);
        /** @var Collection $collection */

        // Save it
        if (!Craft::$app->getElements()->saveElement($collection)) {
            return $this->collectionSaveFailure($collection, Craft::t('super-favourite', 'Couldn\'t save collection.'));
        }

        if ($request->getAcceptsJson()) {
            return $this->asModelSuccess(
                $collection,
                Craft::t('super-favourite', 'Collection saved.'),
                'collection',
                ['success' => true]
            );
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Collection saved.'));

        return $this->redirectToPostedUrl($collection);
    }

    /**
     * Handles the delete controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return ?Response The HTTP response Craft should send.
     */
    public function actionDelete(): ?Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $collectionId = $request->getBodyParam('id') ?? $request->getBodyParam('collectionId');
        if (!$collectionId) {
            $missingCollection = new Collection();
            $missingCollection->addError('id', Craft::t('super-favourite', 'Please select a collection.'));
            return $this->asModelFailure(
                $missingCollection,
                Craft::t('super-favourite', 'Please select a collection.'),
                'collection',
                ['success' => false]
            );
        }

        // Get the collection to check ownership
        $collection = Collection::find()
            ->id($collectionId)
            ->status(null)
            ->one();

        if (!$collection) {
            $missingCollection = new Collection();
            $missingCollection->addError('id', Craft::t('super-favourite', 'Collection not found.'));
            return $this->asModelFailure(
                $missingCollection,
                Craft::t('super-favourite', 'Collection not found.'),
                'collection',
                ['success' => false]
            );
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        $canDelete = $this->canManageCollection($collection, $currentUser);

        if (!$canDelete) {
            /** @var \craft\base\Model $collection */
            $collection->addError('id', Craft::t('super-favourite', 'You do not have permission to delete this collection.'));
            /** @var Collection $collection */
            return $this->asModelFailure(
                $collection,
                Craft::t('super-favourite', 'You do not have permission to delete this collection.'),
                'collection',
                ['success' => false]
            );
        }

        $deleteItems = (bool)$request->getBodyParam('deleteItems', false);
        $collectionService = Plugin::getInstance()->collection;
        $success = $collectionService->deleteCollection(
            $collection,
            $deleteItems
        );

        if ($success) {
            $message = $deleteItems
                ? Craft::t('super-favourite', 'Collection deleted. Favourite item cleanup has been queued.')
                : Craft::t('super-favourite', 'Collection deleted.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => true,
                    'message' => $message
                ]);
            }

            Craft::$app->getSession()->setNotice($message);
            return $this->redirectToPostedUrl();
        }

        $errorMessage = $collectionService->getLastError()
            ?? Craft::t('super-favourite', 'Failed to delete collection.');

        /** @var \craft\base\Model $collection */
        if (!$collection->hasErrors()) {
            $collection->addError('id', $errorMessage);
        }
        /** @var Collection $collection */

        return $this->asModelFailure(
            $collection,
            $errorMessage,
            'collection',
            ['success' => false]
        );
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
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$this->canManageGlobalCollections($currentUser)) {
            return $this->asJson([
                'success' => false,
                'message' => Craft::t('super-favourite', 'You do not have permission to manage global collections.')
            ]);
        }

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
        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$this->canManageGlobalCollections($currentUser)) {
            $globalCollectionCount = Collection::find()
                ->id($collectionIds)
                ->userId(null)
                ->count();

            if ($globalCollectionCount > 0) {
                return $this->asJson([
                    'success' => false,
                    'message' => Craft::t('super-favourite', 'You do not have permission to reorder global collections.')
                ]);
            }
        }

        $success = Plugin::getInstance()->collection->reorderCollections($collectionIds);

        return $this->asJson([
            'success' => $success
        ]);
    }

    /**
     * Returns whether a user can manage global/default collections.
     *
     * @param ?User $user The logged-in user, or null.
     *
     * @return bool Whether the user has global collection access.
     */
    private function canManageGlobalCollections(?User $user): bool
    {
        return $user !== null
            && ($user->admin || $user->can('super-favourite:manage-global-collections'));
    }

    /**
     * Returns whether a user can manage the supplied collection.
     *
     * @param Collection $collection The collection being acted on.
     * @param ?User $user The logged-in user, or null.
     *
     * @return bool Whether the user can manage this collection.
     */
    private function canManageCollection(Collection $collection, ?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($collection->userId === null) {
            return $this->canManageGlobalCollections($user);
        }

        return $user->admin
            || $collection->userId === $user->id
            || $user->can('super-favourite:manage-collections');
    }

    /**
     * Sends a failed collection save back through Craft's route params.
     *
     * @param Collection $collection The unsaved collection with validation errors.
     * @param string $message The base error message.
     *
     * @return ?Response The action response, or null to redisplay the current route.
     */
    private function collectionSaveFailure(Collection $collection, string $message): ?Response
    {
        $errors = $collection->getErrors();
        $errorMessage = $message;

        if (!empty($errors)) {
            $errorList = [];
            foreach ($errors as $fieldErrors) {
                $errorList[] = implode(', ', $fieldErrors);
            }
            $errorMessage .= ' ' . implode(' ', $errorList);
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asModelFailure(
                $collection,
                $errorMessage,
                'collection',
                ['success' => false]
            );
        }

        Craft::$app->getUrlManager()->setRouteParams([
            'collection' => $collection,
        ]);

        return null;
    }
}

