<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;

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
     * Favourites index page (element index)
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('super-favourite/favourites/index');
    }

    /**
     * Get element select field HTML for a given element type (AJAX)
     *
     * @return Response
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
     * Get allowed element types for a collection (AJAX endpoint)
     *
     * @return Response
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
        $collection = \amici\SuperFavourite\elements\Collection::find()
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

        if ($allowedTypes === '*' || $allowedTypes === null || $allowedTypes === '' ||
            (is_array($allowedTypes) && (empty($allowedTypes) || in_array('*', $allowedTypes)))) {
            $allowedTypes = array_keys($availableTypes);
        } elseif (is_string($allowedTypes)) {
            $decoded = json_decode($allowedTypes, true);
            $allowedTypes = is_array($decoded) ? $decoded : [$allowedTypes];
        } elseif (!is_array($allowedTypes)) {
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
     * Get elements for a given element type (AJAX endpoint for frontend forms)
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

            $elements = Plugin::getInstance()->favouriteService->getElementsWithFavouriteStatus(
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
     * Edit favourite item page
     *
     * @param int|null $favouriteId
     * @param FavouriteItem|null $favouriteItem
     * @return Response
     */
    public function actionEdit(?int $favouriteId = null, $favouriteItem = null): Response
    {
        $this->requirePermission('super-favourite:manage-favourites');

        $elementsService = Craft::$app->getElements();

        if ($favouriteItem === null && $favouriteId !== null) {
            $favouriteItem = $elementsService->getElementById($favouriteId, \amici\SuperFavourite\elements\FavouriteItem::class);

            if (!$favouriteItem) {
                throw new \yii\web\NotFoundHttpException('Favourite item not found');
            }
        }

        if ($favouriteItem === null) {
            $favouriteItem = new \amici\SuperFavourite\elements\FavouriteItem();
        }

        // Get all registered element types for the dropdown
        $elementTypes = [];
        foreach ($elementsService->getAllElementTypes() as $elementType) {
            // Exclude our own element types from the list
            if ($elementType === \amici\SuperFavourite\elements\Collection::class ||
                $elementType === \amici\SuperFavourite\elements\FavouriteItem::class) {
                continue;
            }
            $elementTypes[$elementType] = $elementType::displayName();
        }

        $collections = \amici\SuperFavourite\elements\Collection::find()->all();

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
     * Save favourite item
     *
     * @return Response|null
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $favouriteId = $request->getBodyParam('favouriteId');

        // Check if this is a CP request (requires permission) or frontend request (requires login)
        $isCpRequest = $request->getIsCpRequest();

        if ($isCpRequest) {
            $this->requirePermission('super-favourite:manage-favourites');
        } else {
            $this->requireLogin();
        }

        if ($favouriteId) {
            $favouriteItem = \amici\SuperFavourite\elements\FavouriteItem::find()->id($favouriteId)->one();

            if (!$favouriteItem) {
                throw new \yii\web\NotFoundHttpException('Favourite item not found');
            }
        } else {
            $favouriteItem = new \amici\SuperFavourite\elements\FavouriteItem();
        }

        // Set site ID for content
        $favouriteItem->siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // Set field layout ID
        $fieldLayout = $favouriteItem->getFieldLayout();
        if ($fieldLayout) {
            $favouriteItem->fieldLayoutId = $fieldLayout->id;
        }

        // Set attributes
        $favouriteItem->elementType = $request->getRequiredBodyParam('elementType');

        // Get elementId - handle both CP (array) and frontend (simple value) formats
        $elementIds = $request->getBodyParam('elementId');
        if (is_array($elementIds) && !empty($elementIds)) {
            $favouriteItem->elementId = (int)$elementIds[0];
        } elseif (!empty($elementIds)) {
            $favouriteItem->elementId = (int)$elementIds;
        } else {
            $favouriteItem->addError('elementId', Craft::t('super-favourite', 'Please select an element.'));
        }

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
                $favouriteItem->addError('userId', Craft::t('super-favourite', 'Please login to add favourites.'));
            }
        }

        // Get collectionId - handle both CP (array) and frontend (simple value) formats
        // If not provided, use the default collection
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

        // If no collection specified, use default collection
        if (empty($favouriteItem->collectionId)) {
            $defaultCollection = \amici\SuperFavourite\elements\Collection::getDefaultCollection();
            if ($defaultCollection) {
                $favouriteItem->collectionId = $defaultCollection->id;
            } else {
                // No default collection found - this is a critical error
                $errorMessage = Craft::t('super-favourite', 'No default collection found. Please create a default collection first or select a collection.');

                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'error' => $errorMessage
                    ]);
                }

                Craft::$app->getSession()->setError($errorMessage);
                Craft::$app->getUrlManager()->setRouteParams([
                    'favouriteItem' => $favouriteItem
                ]);
                return null;
            }
        }

        // Validation 1: Check collection ownership/permissions
        $collection = \amici\SuperFavourite\elements\Collection::find()
            ->id($favouriteItem->collectionId)
            ->one();

        if (!$collection) {
            $errorMessage = Craft::t('super-favourite', 'Collection not found.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage
                ]);
            }

            Craft::$app->getSession()->setError($errorMessage);
            Craft::$app->getUrlManager()->setRouteParams([
                'favouriteItem' => $favouriteItem
            ]);
            return null;
        }

        // If it's not a global collection, only the owner can add items
        if ($collection->userId !== null && $collection->userId !== $favouriteItem->userId) {
            $errorMessage = Craft::t('super-favourite', 'You do not have permission to add items to this collection. Only the collection owner can add items to personal collections.');

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage
                ]);
            }

            Craft::$app->getSession()->setError($errorMessage);
            Craft::$app->getUrlManager()->setRouteParams([
                'favouriteItem' => $favouriteItem
            ]);
            return null;
        }

        // Validation 2: Check allowed element types
        $allowedElementTypes = $collection->allowedElementTypes;

        // Decode if it's a JSON string
        if (is_string($allowedElementTypes) && !empty($allowedElementTypes)) {
            $allowedElementTypes = json_decode($allowedElementTypes, true);
        }

        // If allowedElementTypes is set and not '*' (all), validate the element type
        if (!empty($allowedElementTypes) && !in_array('*', $allowedElementTypes)) {
            if (!in_array($favouriteItem->elementType, $allowedElementTypes)) {
                // Get element type display name for better error message
                $elementTypeLabel = $favouriteItem->elementType;
                if (class_exists($favouriteItem->elementType)) {
                    $elementTypeLabel = $favouriteItem->elementType::displayName();
                }

                $errorMessage = Craft::t('super-favourite', '{elementType} is not allowed in this collection. Please select a different collection or element type.', [
                    'elementType' => $elementTypeLabel
                ]);

                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'error' => $errorMessage
                    ]);
                }

                Craft::$app->getSession()->setError($errorMessage);
                Craft::$app->getUrlManager()->setRouteParams([
                    'favouriteItem' => $favouriteItem
                ]);
                return null;
            }
        }

        // Validation 3: Check for duplicate (same user, collection, and element)
        // Only check for new items, not when editing existing ones
        if (!$favouriteId) {
            // Use a direct database query to check for duplicates
            // This is more reliable than element queries which can be affected by caching
            $existingRecord = (new \yii\db\Query())
                ->select(['id'])
                ->from('{{%super_favourite_items}}')
                ->where([
                    'userId' => $favouriteItem->userId,
                    'collectionId' => $favouriteItem->collectionId,
                    'elementId' => $favouriteItem->elementId,
                ])
                ->one();

            if ($existingRecord) {
                // Item already exists in this collection
                $message = Craft::t('super-favourite', 'This item is already in the selected collection.');

                // Check if this is an AJAX request
                if ($request->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => true,
                        'message' => $message,
                        'favouriteId' => $existingRecord['id']
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
        $favouriteItem->setScenario(\craft\base\Element::SCENARIO_LIVE);

        // Save it
        if (!Craft::$app->getElements()->saveElement($favouriteItem)) {
            // Get validation errors
            $errors = $favouriteItem->getErrors();
            $errorMessage = Craft::t('super-favourite', 'Couldn\'t save favourite item.');

            if (!empty($errors)) {
                $errorList = [];
                foreach ($errors as $field => $fieldErrors) {
                    $errorList[] = implode(', ', $fieldErrors);
                }
                $errorMessage .= ' ' . implode(' ', $errorList);
            }

            if ($request->getAcceptsJson()) {
                return $this->asJson([
                    'success' => false,
                    'error' => $errorMessage,
                    'errors' => $errors
                ]);
            }

            Craft::$app->getSession()->setError($errorMessage);
            Craft::$app->getUrlManager()->setRouteParams([
                'favouriteItem' => $favouriteItem
            ]);
            return null;
        }

        $message = Craft::t('super-favourite', 'Favourite item saved.');

        // Check if this is an AJAX request
        if ($request->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
                'favouriteId' => $favouriteItem->id
            ]);
        }

        // For regular form submissions
        Craft::$app->getSession()->setNotice($message);
        return $this->redirectToPostedUrl($favouriteItem);
    }

    /**
     * Add an element to favourites
     *
     * @return Response
     */
    public function actionAdd(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $elementType = $request->getRequiredBodyParam('elementType');
        $collectionId = $request->getBodyParam('collectionId');
        $notes = $request->getBodyParam('notes');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'User must be logged in to favourite items.')
            ]);
        }

        $favourite = Plugin::getInstance()->favourite->addFavourite(
            (int)$elementId,
            $elementType,
            $currentUser->id,
            $collectionId ? (int)$collectionId : null,
            $notes
        );

        if ($favourite) {
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('super-favourite', 'Item added to favourites.'),
                'favouriteId' => $favourite->id
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('super-favourite', 'Failed to add item to favourites.')
        ]);
    }

    /**
     * Remove an element from favourites
     *
     * @return Response
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
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'User must be logged in.')
            ]);
        }

        $success = Plugin::getInstance()->favourite->removeFavourite(
            (int)$elementId,
            $currentUser->id,
            $collectionId ? (int)$collectionId : null
        );

        if ($success) {
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('super-favourite', 'Item removed from favourites.')
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('super-favourite', 'Failed to remove item from favourites.')
        ]);
    }

    /**
     * Toggle favourite status
     */
    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $elementType = $request->getRequiredBodyParam('elementType');
        $collectionId = $request->getRequiredBodyParam('collectionId');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'User must be logged in.')
            ]);
        }

        $result = Plugin::getInstance()->favouriteService->toggleFavourite(
            (int)$elementId,
            $elementType,
            (int)$collectionId,
            $currentUser->id
        );

        return $this->asJson($result);
    }

    /**
     * Check if an element is favourited
     *
     * @return Response
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
     * Move a favourite to another collection
     *
     * @return Response
     */
    public function actionMove(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $favouriteId = $request->getRequiredBodyParam('favouriteId');
        $newCollectionId = $request->getRequiredBodyParam('collectionId');

        $success = Plugin::getInstance()->favourite->moveFavourite(
            (int)$favouriteId,
            (int)$newCollectionId
        );

        if ($success) {
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('super-favourite', 'Favourite moved to new collection.')
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('super-favourite', 'Failed to move favourite.')
        ]);
    }

    /**
     * Delete a favourite item
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $favouriteId = $request->getRequiredBodyParam('favouriteId');

        $favouriteItem = \amici\SuperFavourite\elements\FavouriteItem::find()
            ->id($favouriteId)
            ->one();

        if (!$favouriteItem) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'Favourite item not found.')
            ]);
        }

        // Check permissions: user must be the owner or an admin
        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($favouriteItem->userId !== $currentUser->id && !$currentUser->admin) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'You do not have permission to delete this favourite.')
            ]);
        }

        // Delete the favourite item
        $success = Craft::$app->getElements()->deleteElement($favouriteItem);

        if ($success) {
            return $this->asJson([
                'success' => true,
                'message' => Craft::t('super-favourite', 'Favourite removed.')
            ]);
        }

        return $this->asJson([
            'success' => false,
            'error' => Craft::t('super-favourite', 'Failed to delete favourite.')
        ]);
    }
}

