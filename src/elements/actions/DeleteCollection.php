<?php
namespace amici\SuperFavourite\elements\actions;

use Craft;
use craft\elements\actions\Delete;
use craft\elements\db\ElementQueryInterface;
use amici\SuperFavourite\elements\Collection;

/**
 * Deletes collection elements from the element index with accurate failure messages.
 */
class DeleteCollection extends Delete
{
    /**
     * Runs the delete action for selected collections.
     *
     * @param ElementQueryInterface $query The selected collection query.
     *
     * @return bool Whether at least one collection was deleted.
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $elementsService = Craft::$app->getElements();
        $user = Craft::$app->getUser()->getIdentity();
        $deletedCount = 0;
        $failedMessages = [];

        foreach ($query->all() as $element) {
            if (!$element instanceof Collection) {
                continue;
            }

            if (!$elementsService->canView($element, $user) || !$elementsService->canDelete($element, $user)) {
                $failedMessages[] = Craft::t('super-favourite', 'You do not have permission to delete "{name}".', [
                    'name' => $element->name,
                ]);
                continue;
            }

            if ($elementsService->deleteElement($element, $this->hard)) {
                $deletedCount++;
                continue;
            }

            $failedMessages[] = $this->collectionErrorMessage($element);
        }

        if ($deletedCount === 0) {
            $this->setMessage($failedMessages[0] ?? Craft::t('super-favourite', 'No collections were deleted.'));
            return false;
        }

        if (!empty($failedMessages)) {
            $this->setMessage(Craft::t('super-favourite', 'Deleted {count} collection(s). {failed} collection(s) failed: {error}', [
                'count' => $deletedCount,
                'failed' => count($failedMessages),
                'error' => $failedMessages[0],
            ]));
            return true;
        }

        $this->setMessage(Craft::t('super-favourite', 'Deleted {count} collection(s).', [
            'count' => $deletedCount,
        ]));

        return true;
    }

    /**
     * Returns the first useful validation error for a failed collection delete.
     *
     * @param Collection $collection The collection that failed to delete.
     *
     * @return string The error message.
     */
    private function collectionErrorMessage(Collection $collection): string
    {
        $errors = $collection->getErrors();

        foreach ($errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return implode(' ', $fieldErrors);
            }
        }

        return Craft::t('super-favourite', 'Could not delete "{name}".', [
            'name' => $collection->name,
        ]);
    }
}
