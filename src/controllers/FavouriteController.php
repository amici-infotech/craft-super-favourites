<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Favourite Controller
 */
class FavouriteController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = ['get-elements', 'get-allowed-types'];

    /**
     * Handles the index controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('super-favourite/favourites/index');
    }

    /**
     * Handles the get element select html controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionGetElementSelectHtml(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $request = Craft::$app->getRequest();
        $elementType = $request->getRequiredBodyParam('elementType');

        // Validate element type
        if (!class_exists($elementType)) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Invalid element type.')
            ]);
        }

        // Get the element type's display name
        $elementTypeLabel = $elementType::displayName();

        // Render the element select field
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $view->setTemplateMode(\craft\web\View::TEMPLATE_MODE_CP);

        // Start buffering to capture registered JS
        $view->startJsBuffer();

        $html = $view->renderTemplate('super-favourite/favourites/_element-select-field', [
            'elementType' => $elementType,
            'elementTypeLabel' => $elementTypeLabel,
        ]);

        // Get the registered JS
        $js = $view->clearJsBuffer(false);

        $view->setTemplateMode($oldTemplateMode);

        return $this->asJson([
            'success' => true,
            'html' => $html,
            'js' => $js
        ]);
    }

    /**
     * Handles the get allowed types controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionGetAllowedTypes(): Response
    {
        $request = Craft::$app->getRequest();
        $collectionId = $request->getParam('collectionId');

        if (!$collectionId) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Collection ID is required.')
            ]);
        }

        // Get the collection
        $collection = Collection::find()
            ->id($collectionId)
            ->one();

        if (!$collection) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Collection not found.')
            ]);
        }

        $allowedTypes = $collection->allowedElementTypes;

        $variable = new \amici\SuperFavourite\variables\SuperFavouriteVariable();
        $availableTypesArray = $variable->getAvailableElementTypes();

        $availableTypes = [];
        foreach ($availableTypesArray as $type) {
            $availableTypes[$type['value']] = $type['label'];
        }

        if (empty($allowedTypes)) {
            $allowedTypes = array_keys($availableTypes);
        }

        $typesData = [];
        foreach ($allowedTypes as $typeClass) {
            if (isset($availableTypes[$typeClass])) {
                $typesData[] = [
                    'value' => $typeClass,
                    'label' => $availableTypes[$typeClass]
                ];
            }
        }

        return $this->asJson([
            'success' => true,
            'types' => $typesData
        ]);
    }

    /**
     * Handles the get elements controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionGetElements(): Response
    {
        $request = Craft::$app->getRequest();
        $elementType = $request->getParam('elementType');
        $collectionId = $request->getParam('collectionId');
        $limit = $request->getParam('limit', 10);

        if (!$elementType || !$collectionId) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Element type and collection ID are required.')
            ]);
        }

        try {
            $currentUser = Craft::$app->getUser()->getIdentity();
            $userId = $currentUser ? $currentUser->id : null;

            $elements = Plugin::getInstance()->favourite->getElementsWithFavouriteStatus(
                $elementType,
                $collectionId,
                $userId,
                $limit
            );

            return $this->asJson([
                'success' => true,
                'elements' => $elements
            ]);
        } catch (\Exception $e) {
            return $this->asJson([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handles the edit controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @param ?int $favouriteId The ID of the favourite item element.
     * @param mixed $favouriteItem An existing favourite item passed back after validation, or null for a fresh load.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionEdit(?int $favouriteId = null, $favouriteItem = null): Response
    {
        $this->requirePermission('super-favourite:manage-favourites');

        $elementsService = Craft::$app->getElements();

        if ($favouriteItem === null && $favouriteId !== null) {
            $favouriteItem = $elementsService->getElementById($favouriteId, FavouriteItem::class);

            if (!$favouriteItem) {
                throw new \yii\web\NotFoundHttpException('Favourite item not found');
            }
        }

        if ($favouriteItem === null) {
            $favouriteItem = new FavouriteItem();
        }

        // Get all registered element types for the dropdown
        $elementTypes = [];
        foreach ($elementsService->getAllElementTypes() as $elementType) {
            // Exclude our own element types from the list
            if ($elementType === Collection::class ||
                $elementType === FavouriteItem::class) {
                continue;
            }
            $elementTypes[$elementType] = $elementType::displayName();
        }

        $collections = Collection::find()->all();

        $favouritedElement = null;
        if ($favouriteItem->elementId && $favouriteItem->elementType) {
            $favouritedElement = $elementsService->getElementById($favouriteItem->elementId, $favouriteItem->elementType);
        }

        $elementTypeLabel = null;
        if ($favouriteItem->elementType && class_exists($favouriteItem->elementType)) {
            $elementTypeLabel = $favouriteItem->elementType::displayName();
        }

        $tabs = [];
        $tabs['favourite-details'] = [
            'label' => Craft::t('super-favourite', 'Favourite Item Details'),
            'url' => '#favourite-details',
        ];

        // Add tabs from the field layout
        $fieldLayout = $favouriteItem->getFieldLayout();
        foreach ($fieldLayout->getTabs() as $tab) {
            $tabs[$tab->getHtmlId()] = [
                'label' => Craft::t('site', $tab->name),
                'url' => '#' . $tab->getHtmlId(),
            ];
        }

        return $this->renderTemplate('super-favourite/favourites/_edit', [
            'favouriteItem' => $favouriteItem,
            'isNew' => !$favouriteItem->id,
            'elementTypes' => $elementTypes,
            'collections' => $collections,
            'favouritedElement' => $favouritedElement,
            'elementTypeLabel' => $elementTypeLabel,
            'tabs' => $tabs,
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

        $request = Craft::$app->getRequest();
        $favouriteId = $request->getBodyParam('id') ?? $request->getBodyParam('favouriteId');

        // Check if this is a CP request (requires permission) or frontend request (requires login)
        $isCpRequest = $request->getIsCpRequest();

        if ($isCpRequest) {
            $this->requirePermission('super-favourite:manage-favourites');
        } else {
            $this->requireLogin();
        }

        if ($favouriteId) {
            $favouriteItem = FavouriteItem::find()->id($favouriteId)->one();

            if (!$favouriteItem) {
                throw new \yii\web\NotFoundHttpException('Favourite item not found');
            }
        } else {
            $favouriteItem = new FavouriteItem();
        }

        // Set site ID for content
        $favouriteItem->siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Set field layout ID
        $fieldLayout = $favouriteItem->getFieldLayout();
        if ($fieldLayout) {
            $favouriteItem->fieldLayoutId = $fieldLayout->id;
        }

        // Get elementId - handle both CP (array) and frontend (simple value) formats
        $elementIds = $request->getBodyParam('elementId');
        if (is_array($elementIds) && !empty($elementIds)) {
            $favouriteItem->elementId = (int)$elementIds[0];
        } elseif (!empty($elementIds)) {
            $favouriteItem->elementId = (int)$elementIds;
        } else {
            /** @var \craft\base\Model $favouriteItem */
            $favouriteItem->addError('elementId', Craft::t('super-favourite', 'Please select an element.'));
            /** @var FavouriteItem $favouriteItem */
        }

        $this->assignElementTypeFromRequest($favouriteItem);

        // Get userId - handle both CP (array/element select) and frontend (auto-assign current user) formats
        $userIds = $request->getBodyParam('userId');
        if (is_array($userIds) && !empty($userIds)) {
            // CP format: element select field
            $favouriteItem->userId = (int)$userIds[0];
        } elseif (!empty($userIds) && $userIds !== '') {
            // CP format: simple input
            $favouriteItem->userId = (int)$userIds;
        } else {
            // Frontend format: auto-assign to current user
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($currentUser) {
                $favouriteItem->userId = $currentUser->id;
            } else {
                /** @var \craft\base\Model $favouriteItem */
                $favouriteItem->addError('userId', Craft::t('super-favourite', 'Please login to add favourites.'));
                /** @var FavouriteItem $favouriteItem */
            }
        }

        // Get collectionId - handle both CP (array) and frontend (simple value) formats.
        $collectionIds = $request->getBodyParam('collectionId');
        if (is_array($collectionIds) && !empty($collectionIds)) {
            // Remove empty values from array
            $collectionIds = array_filter($collectionIds, function($val) {
                return !empty($val) && $val !== '';
            });

            if (!empty($collectionIds)) {
                $favouriteItem->collectionId = (int)$collectionIds[0];
            } else {
                $collectionIds = null; // Treat empty array as no selection
            }
        } elseif (!empty($collectionIds) && $collectionIds !== '') {
            $favouriteItem->collectionId = (int)$collectionIds;
        }

        if (empty($favouriteItem->collectionId)) {
            return $this->favouriteModelFailure(
                $favouriteItem,
                'collectionId',
                Craft::t('super-favourite', 'Please select a collection.')
            );
        }

        // Validation 1: Check collection ownership/permissions
        $collection = Collection::find()
            ->id($favouriteItem->collectionId)
            ->one();

        if (!$collection) {
            return $this->favouriteModelFailure(
                $favouriteItem,
                'collectionId',
                Craft::t('super-favourite', 'Collection not found.')
            );
        }

        // If it's not a global collection, only the owner can add items
        if ($collection->userId !== null && $collection->userId !== $favouriteItem->userId) {
            return $this->favouriteModelFailure(
                $favouriteItem,
                'collectionId',
                Craft::t('super-favourite', 'You do not have permission to add items to this collection. Only the collection owner can add items to personal collections.')
            );
        }

        // Validation 2: Check allowed element types
        $allowedElementTypes = $collection->allowedElementTypes;

        // Empty array means all element types are allowed.
        if (!empty($allowedElementTypes)) {
            if (!in_array($favouriteItem->elementType, $allowedElementTypes)) {
                // Get element type display name for better error message
                $elementTypeLabel = $favouriteItem->elementType;
                if (class_exists($favouriteItem->elementType)) {
                    $elementTypeLabel = $favouriteItem->elementType::displayName();
                }

                $errorMessage = Craft::t('super-favourite', '{elementType} is not allowed in this collection. Please select a different collection or element type.', [
                    'elementType' => $elementTypeLabel
                ]);

                return $this->favouriteModelFailure($favouriteItem, 'elementType', $errorMessage);
            }
        }

        // Validation 3: Check for duplicate (same user, collection, and element)
        // Only check for new items, not when editing existing ones
        if (!$favouriteId) {
            // Use element query to check for duplicates
            $existingFavourite = FavouriteItem::find()
                ->userId($favouriteItem->userId)
                ->collectionId($favouriteItem->collectionId)
                ->elementId($favouriteItem->elementId)
                ->one();

            if ($existingFavourite) {
                // Item already exists in this collection
                $message = Craft::t('super-favourite', 'This item is already in the selected collection.');

                // Check if this is an AJAX request
                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => true,
                        'message' => $message,
                        'favouriteId' => $existingFavourite->id
                    ]);
                }

                // For regular form submissions
                Craft::$app->getSession()->setNotice($message);
                return $this->redirectToPostedUrl($favouriteItem);
            }
        }

        $favouriteItem->notes = $request->getBodyParam('notes');

        // Set custom field values from the 'fields' namespace
        $favouriteItem->setFieldValuesFromRequest('fields');

        // Set scenario to LIVE for proper content saving
        /** @var \craft\base\Model $favouriteItem */
        $favouriteItem->setScenario(\craft\base\Element::SCENARIO_LIVE);
        /** @var FavouriteItem $favouriteItem */

        // Save and return validation errors if it fails
        if (!Craft::$app->getElements()->saveElement($favouriteItem)) {
            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', "Couldn't save favourite.")
            );
        }

        return $this->asModelSuccess(
            $favouriteItem,
            Craft::t('super-favourite', 'Favourite saved.'),
            $isCpRequest ? 'favouriteItem' : 'favourite'
        );
    }

    /**
     * Handles the add controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $elementType = $this->resolveElementType((int)$elementId, $request->getBodyParam('elementType'));
        $collectionId = $request->getBodyParam('collectionId');
        $notes = $request->getBodyParam('notes');

        $favourite = new FavouriteItem();
        $favourite->elementId = (int)$elementId;
        $favourite->elementType = $elementType;
        $favourite->collectionId = $collectionId ? (int)$collectionId : null;
        $favourite->notes = $notes;

        if ($elementType === null) {
            return $this->favouriteModelFailure(
                $favourite,
                'elementId',
                Craft::t('super-favourite', 'Could not determine the element type for this favourite.'),
                'favourite'
            );
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asFailure(Craft::t('super-favourite', 'User must be logged in to favourite items.'));
        }

        $favourite->userId = $currentUser->id;

        if (!$collectionId) {
            return $this->favouriteModelFailure(
                $favourite,
                'collectionId',
                Craft::t('super-favourite', 'Please select a collection.'),
                'favourite'
            );
        }

        $collection = Collection::find()->id($collectionId)->one();
        if (!$collection) {
            return $this->favouriteModelFailure(
                $favourite,
                'collectionId',
                Craft::t('super-favourite', 'Collection not found.'),
                'favourite'
            );
        }

        if ($collection->userId !== null && $collection->userId !== $currentUser->id) {
            return $this->favouriteModelFailure(
                $favourite,
                'collectionId',
                Craft::t('super-favourite', 'You do not have permission to add items to this collection.'),
                'favourite'
            );
        }

        $allowedElementTypes = $collection->allowedElementTypes;
        if (!empty($allowedElementTypes) && !in_array($elementType, $allowedElementTypes, true)) {
            return $this->favouriteModelFailure(
                $favourite,
                'elementType',
                Craft::t('super-favourite', 'This element type is not allowed in the selected collection.'),
                'favourite'
            );
        }

        // Check for existing favourite to prevent duplicates
        $existing = Plugin::getInstance()->favourite->checkDuplicate(
            $currentUser->id,
            (int)$collectionId,
            (int)$elementId
        );

        if ($existing) {
            // Return existing favourite instead of error
            $favourite = FavouriteItem::find()->id($existing['id'])->one();
            return $this->asModelSuccess(
                $favourite,
                Craft::t('super-favourite', 'Item is already in your favourites.'),
                'favourite'
            );
        }

        // Create new favourite item
        $favourite->collectionId = (int)$collectionId;

        // Validate and save - this will populate validation errors if it fails
        if (!Craft::$app->getElements()->saveElement($favourite)) {
            return $this->asFavouriteModelFailure(
                $favourite,
                Craft::t('super-favourite', "Couldn't save favourite.")
            );
        }

        return $this->asModelSuccess(
            $favourite,
            Craft::t('super-favourite', 'Item added to favourites.'),
            'favourite'
        );
    }

    /**
     * Handles the remove controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionRemove(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $collectionId = $request->getBodyParam('collectionId');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asFailure(Craft::t('super-favourite', 'User must be logged in.'));
        }

        $favouriteItem = FavouriteItem::find()
            ->userId($currentUser->id)
            ->elementId((int)$elementId)
            ->collectionId($collectionId ? (int)$collectionId : null)
            ->one();

        if (!$favouriteItem) {
            $favouriteItem = new FavouriteItem();
            return $this->favouriteModelFailure(
                $favouriteItem,
                'elementId',
                Craft::t('super-favourite', 'Favourite item not found.'),
                'favourite'
            );
        }

        // Attempt to remove favourite
        $success = Plugin::getInstance()->favourite->removeFavourite(
            (int)$elementId,
            $currentUser->id,
            $collectionId ? (int)$collectionId : null
        );

        if (!$success) {
            return $this->favouriteModelFailure(
                $favouriteItem,
                'id',
                Craft::t('super-favourite', 'Failed to remove item from favourites.'),
                'favourite'
            );
        }

        return $this->asSuccess(Craft::t('super-favourite', 'Item removed from favourites.'));
    }

    /**
     * Handles the toggle controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $elementType = $this->resolveElementType((int)$elementId, $request->getBodyParam('elementType'));
        $collectionId = $request->getRequiredBodyParam('collectionId');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asFailure(Craft::t('super-favourite', 'User must be logged in.'));
        }

        if ($elementType === null) {
            $favourite = new FavouriteItem();
            $favourite->elementId = (int)$elementId;
            return $this->favouriteModelFailure(
                $favourite,
                'elementId',
                Craft::t('super-favourite', 'Could not determine the element type for this favourite.'),
                'favourite'
            );
        }

        // Toggle favourite using service
        $result = Plugin::getInstance()->favourite->toggleFavourite(
            (int)$elementId,
            $elementType,
            (int)$collectionId,
            $currentUser->id
        );

        // Check if operation was successful
        if (!$result['success']) {
            $favourite = new FavouriteItem();
            $favourite->userId = $currentUser->id;
            $favourite->collectionId = (int)$collectionId;
            $favourite->elementId = (int)$elementId;
            $favourite->elementType = $elementType;

            return $this->favouriteModelFailure(
                $favourite,
                'id',
                $result['error'] ?? Craft::t('super-favourite', 'Failed to toggle favourite.')
            );
        }

        // Set appropriate message based on action taken
        $message = $result['action'] === 'added'
            ? Craft::t('super-favourite', 'Item added to favourites.')
            : Craft::t('super-favourite', 'Item removed from favourites.');

        // Return success with full result data for AJAX
        return $this->asSuccess($message, data: $result);
    }

    /**
     * Handles the check controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionCheck(): Response
    {
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredParam('elementId');
        $collectionId = $request->getParam('collectionId');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asJson([
                'success' => false,
                'isFavourited' => false
            ]);
        }

        $isFavourited = Plugin::getInstance()->favourite->isFavourited(
            (int)$elementId,
            $currentUser->id,
            $collectionId ? (int)$collectionId : null
        );

        return $this->asJson([
            'success' => true,
            'isFavourited' => $isFavourited
        ]);
    }

    /**
     * Handles the move controller action.
     *
     * Request values are read from Craft's request object rather than method parameters.
     *
     * @return Response The HTTP response Craft should send.
     */
    public function actionMove(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $favouriteId = $request->getBodyParam('id') ?? $request->getBodyParam('favouriteId');
        if (!$favouriteId) {
            throw new \yii\web\BadRequestHttpException('Missing required body parameter: id');
        }
        $newCollectionId = $request->getRequiredBodyParam('collectionId');
        $favouriteItem = FavouriteItem::find()->id($favouriteId)->one();

        if (!$favouriteItem) {
            $favouriteItem = new FavouriteItem();
            /** @var \craft\base\Model $favouriteItem */
            $favouriteItem->addError('id', Craft::t('super-favourite', 'Favourite item not found.'));
            /** @var FavouriteItem $favouriteItem */
            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', 'Favourite item not found.')
            );
        }

        // Attempt to move favourite to new collection
        $success = Plugin::getInstance()->favourite->moveFavourite(
            (int)$favouriteId,
            (int)$newCollectionId
        );

        if (!$success) {
            /** @var \craft\base\Model $favouriteItem */
            $favouriteItem->addError('collectionId', Craft::t('super-favourite', 'Failed to move favourite.'));
            /** @var FavouriteItem $favouriteItem */
            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', 'Failed to move favourite.')
            );
        }

        return $this->asSuccess(Craft::t('super-favourite', 'Favourite moved to new collection.'));
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
        $favouriteId = $request->getBodyParam('id') ?? $request->getBodyParam('favouriteId');
        if (!$favouriteId) {
            throw new \yii\web\BadRequestHttpException('Missing required body parameter: id');
        }

        // Find the favourite item to delete
        $favouriteItem = FavouriteItem::find()
            ->id($favouriteId)
            ->one();

        if (!$favouriteItem) {
            $favouriteItem = new FavouriteItem();
            /** @var \craft\base\Model $favouriteItem */
            $favouriteItem->addError('id', Craft::t('super-favourite', 'Favourite item not found.'));
            /** @var FavouriteItem $favouriteItem */
            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', 'Favourite item not found.')
            );
        }

        // Check permissions: user must be the owner or an admin
        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($favouriteItem->userId !== $currentUser->id && !$currentUser->admin) {
            /** @var \craft\base\Model $favouriteItem */
            $favouriteItem->addError('id', Craft::t('super-favourite', 'You do not have permission to delete this favourite.'));
            /** @var FavouriteItem $favouriteItem */
            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', 'You do not have permission to delete this favourite.')
            );
        }

        // Delete the favourite item (hard delete for consistency)
        if (!Craft::$app->getElements()->deleteElement($favouriteItem, true)) {
            /** @var \craft\base\Model $favouriteItem */
            if (!$favouriteItem->hasErrors()) {
                $favouriteItem->addError('id', Craft::t('super-favourite', 'Failed to delete favourite.'));
            }
            /** @var FavouriteItem $favouriteItem */

            return $this->asFavouriteModelFailure(
                $favouriteItem,
                Craft::t('super-favourite', 'Failed to delete favourite.')
            );
        }

        return $this->asModelSuccess(
            $favouriteItem,
            Craft::t('super-favourite', 'Favourite removed.'),
            'favourite'
        );
    }

    /**
     * Adds a validation error to a favourite item and returns a model failure response.
     *
     * @param FavouriteItem $favouriteItem The model receiving the error.
     * @param string $attribute The attribute to attach the error to.
     * @param string $message The validation message.
     *
     * @return ?Response The Craft model failure response.
     */
    private function favouriteModelFailure(
        FavouriteItem $favouriteItem,
        string $attribute,
        string $message,
        string $variableName = 'favourite'
    ): ?Response
    {
        /** @var \craft\base\Model $favouriteItem */
        $favouriteItem->addError($attribute, $message);
        /** @var FavouriteItem $favouriteItem */

        return $this->asFavouriteModelFailure($favouriteItem, $message, $variableName);
    }

    /**
     * Returns a model failure with the frontend-friendly `favourite` variable.
     *
     * @param FavouriteItem $favouriteItem The failed favourite model.
     * @param string $message The failure message.
     * @param string $variableName The primary response model name.
     *
     * @return ?Response The Craft model failure response.
     */
    private function asFavouriteModelFailure(
        FavouriteItem $favouriteItem,
        string $message,
        string $variableName = 'favourite'
    ): ?Response
    {
        return $this->asModelFailure(
            $favouriteItem,
            $message,
            $variableName,
            [],
            [
                'favourite' => $favouriteItem,
                'favouriteItem' => $favouriteItem,
            ]
        );
    }

    /**
     * Assigns the favourite element type from the posted value or by loading the element.
     *
     * @param FavouriteItem $favouriteItem The favourite item being saved.
     *
     * @return void Nothing is returned.
     */
    private function assignElementTypeFromRequest(FavouriteItem $favouriteItem): void
    {
        $elementType = $this->resolveElementType(
            $favouriteItem->elementId,
            Craft::$app->getRequest()->getBodyParam('elementType')
        );

        if ($elementType !== null) {
            $favouriteItem->elementType = $elementType;
            return;
        }

        if ($favouriteItem->elementId) {
            $this->addFavouriteError(
                $favouriteItem,
                'elementId',
                Craft::t('super-favourite', 'Could not determine the element type for this favourite.')
            );
        }
    }

    /**
     * Resolves an element type from the posted value or by loading the element by ID.
     *
     * @param ?int $elementId The element ID.
     * @param mixed $postedElementType Optional posted element type.
     *
     * @return ?string The resolved element type class name, or null.
     */
    private function resolveElementType(?int $elementId, mixed $postedElementType = null): ?string
    {
        if ($elementId) {
            $element = Craft::$app->getElements()->getElementById($elementId);

            if ($element) {
                return get_class($element);
            }
        }

        if (is_string($postedElementType) && class_exists($postedElementType)) {
            return $postedElementType;
        }

        return null;
    }

    /**
     * Adds an error to a favourite item while keeping static analysis happy.
     *
     * @param FavouriteItem $favouriteItem The model receiving the error.
     * @param string $attribute The field name.
     * @param string $message The error message.
     *
     * @return void Nothing is returned.
     */
    private function addFavouriteError(FavouriteItem $favouriteItem, string $attribute, string $message): void
    {
        /** @var \craft\base\Model $favouriteItem */
        $favouriteItem->addError($attribute, $message);
    }
}

