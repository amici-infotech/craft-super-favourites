<?php
namespace amici\SuperFavourite\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

use amici\SuperFavourite\Plugin;

/**
 * Favourite Controller
 *
 * Handles favourite/unfavourite actions
 */
class FavouriteController extends Controller
{
    /**
     * @inheritdoc
     */
    protected array|bool|int $allowAnonymous = false;

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

        // Get available collections
        $collections = \amici\SuperFavourite\elements\Collection::find()->all();

        // Get the favourited element if it exists
        $favouritedElement = null;
        if ($favouriteItem->elementId && $favouriteItem->elementType) {
            $favouritedElement = $elementsService->getElementById($favouriteItem->elementId, $favouriteItem->elementType);
        }

        // Get element type label for dynamic display
        $elementTypeLabel = null;
        if ($favouriteItem->elementType && class_exists($favouriteItem->elementType)) {
            $elementTypeLabel = $favouriteItem->elementType::displayName();
        }

        // Prepare tabs for the form
        $tabs = [];

        // Main tab with the basic fields
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
        $this->requirePermission('super-favourite:manage-favourites');

        $request = Craft::$app->getRequest();
        $favouriteId = $request->getBodyParam('favouriteId');

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

        // Get elementId from element select field
        $elementIds = $request->getBodyParam('elementId');
        if (is_array($elementIds) && !empty($elementIds)) {
            $favouriteItem->elementId = (int)$elementIds[0];
        } else {
            $favouriteItem->addError('elementId', Craft::t('super-favourite', 'Please select an element.'));
        }

        // Get userId from element select field
        $userIds = $request->getBodyParam('userId');
        if (is_array($userIds) && !empty($userIds)) {
            $favouriteItem->userId = (int)$userIds[0];
        } else {
            $favouriteItem->addError('userId', Craft::t('super-favourite', 'Please select a user.'));
        }

        // Get collectionId from element select field
        // If not provided, use the default collection
        $collectionIds = $request->getBodyParam('collectionId');
        if (is_array($collectionIds) && !empty($collectionIds)) {
            $favouriteItem->collectionId = (int)$collectionIds[0];
        } else {
            // Get the default collection
            $defaultCollection = \amici\SuperFavourite\elements\Collection::getDefaultCollection();
            if ($defaultCollection) {
                $favouriteItem->collectionId = $defaultCollection->id;
            } else {
                $favouriteItem->addError('collectionId', Craft::t('super-favourite', 'No default collection found. Please select a collection.'));
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

            Craft::$app->getSession()->setError($errorMessage);

            Craft::$app->getUrlManager()->setRouteParams([
                'favouriteItem' => $favouriteItem
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('super-favourite', 'Favourite item saved.'));

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
     *
     * @return Response
     */
    public function actionToggle(): Response
    {
        $this->requirePostRequest();
        $this->requireLogin();

        $request = Craft::$app->getRequest();

        $elementId = $request->getRequiredBodyParam('elementId');
        $elementType = $request->getRequiredBodyParam('elementType');
        $collectionId = $request->getBodyParam('collectionId');

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser) {
            return $this->asJson([
                'success' => false,
                'error' => Craft::t('super-favourite', 'User must be logged in.')
            ]);
        }

        $isFavourited = Plugin::getInstance()->favourite->isFavourited(
            (int)$elementId,
            $currentUser->id,
            $collectionId ? (int)$collectionId : null
        );

        if ($isFavourited) {
            // Remove it
            $success = Plugin::getInstance()->favourite->removeFavourite(
                (int)$elementId,
                $currentUser->id,
                $collectionId ? (int)$collectionId : null
            );

            return $this->asJson([
                'success' => $success,
                'action' => 'removed',
                'isFavourited' => false,
                'message' => Craft::t('super-favourite', 'Item removed from favourites.')
            ]);
        } else {
            // Add it
            $favourite = Plugin::getInstance()->favourite->addFavourite(
                (int)$elementId,
                $elementType,
                $currentUser->id,
                $collectionId ? (int)$collectionId : null
            );

            return $this->asJson([
                'success' => $favourite !== false,
                'action' => 'added',
                'isFavourited' => true,
                'favouriteId' => $favourite ? $favourite->id : null,
                'message' => Craft::t('super-favourite', 'Item added to favourites.')
            ]);
        }
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
}

