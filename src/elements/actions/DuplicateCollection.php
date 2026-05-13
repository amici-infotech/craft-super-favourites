<?php
namespace amici\SuperFavourite\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use amici\SuperFavourite\elements\Collection;

/**
 * DuplicateCollection Element Action
 *
 * Custom duplicate action that properly handles Collection element duplication
 * with automatic unique handle generation
 */
class DuplicateCollection extends ElementAction
{
    /**
     * Returns the label for the element action trigger.
     *
     * @return string The requested string value.
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('super-favourite', 'Duplicate');
    }

    /**
     * Registers JavaScript for the element action trigger.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public function getTriggerHtml(): ?string
    {
        // Only enable for duplicatable elements
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
(() => {
  new Craft.ElementActionTrigger({
    type: $type,
    validateSelection: (selectedItems, elementIndex) => {
      for (let i = 0; i < selectedItems.length; i++) {
        if (!Garnish.hasAttr(selectedItems.eq(i).find('.element'), 'data-duplicatable')) {
          return false;
        }
      }
      return true;
    },
  });
})();
JS, [static::class]);

        return null;
    }

    /**
     * Runs the element action for the selected query results.
     *
     * @param ElementQueryInterface $query The Craft element query being modified or processed.
     *
     * @return bool True if at least one selected collection was duplicated; false otherwise.
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($elements as $element) {
            if (!$element instanceof Collection) {
                $failCount++;
                continue;
            }

            try {
                // Create a new collection with the same attributes
                $duplicate = new Collection();

                // Copy basic attributes
                $duplicate->userId = $element->userId;
                $duplicate->name = $element->name;
                $duplicate->description = $element->description;
                $duplicate->isDefault = false; // Never duplicate as default
                $duplicate->allowedElementTypes = $element->allowedElementTypes;
                $duplicate->sortOrder = $element->sortOrder;

                // Generate a unique handle using Craft's naming convention
                $duplicate->handle = $this->getUniqueHandle($element);

                // Copy custom fields
                $duplicate->setFieldValues($element->getFieldValues());

                // Save the duplicate
                if (Craft::$app->getElements()->saveElement($duplicate)) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = [
                        'element' => $element->name,
                        'errors' => $duplicate->getErrors(),
                    ];

                    // Log the errors
                    Craft::error(
                        'Failed to duplicate collection "' . $element->name . '": ' . json_encode($duplicate->getErrors()),
                        'super-favourite'
                    );
                }
            } catch (\Throwable $e) {
                $failCount++;
                $errors[] = [
                    'element' => $element->name ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];

                Craft::error(
                    'Exception while duplicating collection: ' . $e->getMessage(),
                    'super-favourite'
                );
            }
        }

        // Set appropriate message
        if ($successCount === 0) {
            $message = Craft::t('super-favourite', 'Could not duplicate collections due to validation errors.');

            // Log detailed errors for debugging
            if (!empty($errors)) {
                Craft::error('Duplicate errors: ' . json_encode($errors), 'super-favourite');
            }

            $this->setMessage($message);
            return false;
        }

        if ($failCount !== 0) {
            $this->setMessage(Craft::t('super-favourite', 'Duplicated {count} collection(s). {failCount} failed.', [
                'count' => $successCount,
                'failCount' => $failCount,
            ]));
        } else {
            $this->setMessage(Craft::t('super-favourite', 'Duplicated {count} collection(s).', [
                'count' => $successCount,
            ]));
        }

        return true;
    }

    /**
     * Returns the unique handle value.
     *
     * @param Collection $element The Craft element being checked or duplicated.
     *
     * @return string The requested string value.
     */
    private function getUniqueHandle(Collection $element): string
    {
        $baseHandle = $element->handle;
        $handle = $baseHandle;
        $i = 1;

        // Keep incrementing until we find a unique handle
        while ($this->handleExists($handle, $element->userId)) {
            $handle = $baseHandle . $i;
            $i++;
        }

        return $handle;
    }

    /**
     * Checks whether a collection handle is already in use.
     *
     * @param string $handle The collection handle to save, filter by, or test for uniqueness.
     * @param ?int $userId The user ID; null usually means use the current user or global scope depending on the method.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    private function handleExists(string $handle, ?int $userId): bool
    {
        return Collection::find()
            ->handle($handle)
            ->userId($userId)
            ->exists();
    }
}

