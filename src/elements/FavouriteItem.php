<?php
namespace amici\SuperFavourite\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Edit;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\actions\View;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use Exception;

use amici\SuperFavourite\elements\db\FavouriteItemQuery;
use amici\SuperFavourite\records\FavouriteItemRecord;
use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\conditions\FavouriteItemCondition;

/**
 * FavouriteItem Element
 *
 * Represents a single favourited element in a collection
 */
class FavouriteItem extends Element
{
    // Properties
    public ?int $userId = null;
    public ?int $collectionId = null;
    public ?int $elementId = null;
    public ?string $elementType = null;
    public int $sortOrder = 0;
    public ?string $notes = null;
    public ?int $fieldLayoutId = null;

    private User|false|null $_user = null;
    private Collection|false|null $_collection = null;
    private Element|false|null $_favouritedElement = null;

    /**
     * Normalizes values before validation so saves can return model errors together.
     *
     * @return bool Whether validation should continue.
     */
    public function beforeValidate(): bool
    {
        if ($this->elementId && !$this->elementType) {
            $element = Craft::$app->getElements()->getElementById($this->elementId);

            if ($element) {
                $this->elementType = get_class($element);
                $this->_favouritedElement = $element;
            }
        }

        return parent::beforeValidate();
    }

    /**
     * Returns the singular display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function displayName(): string
    {
        return Craft::t('super-favourite', 'Favourite Item');
    }

    /**
     * Returns the lower-case singular display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'favourite item');
    }

    /**
     * Returns the plural display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-favourite', 'Favourite Items');
    }

    /**
     * Returns the lower-case plural display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'favourite items');
    }

    /**
     * Returns the reference handle Craft uses for this element type.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public static function refHandle(): ?string
    {
        return 'favouriteItem';
    }

    /**
     * Tells Craft whether changes should be tracked for this element type.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * Tells Craft whether this element type stores content/custom field values.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * Tells Craft whether this element type has a title.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * Returns the configured field layout for this element type.
     *
     * @return ?\craft\models\FieldLayout The `?\craft\models\FieldLayout` value produced by this method.
     */
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * Tells Craft whether this element type has public URLs.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function hasUris(): bool
    {
        return false;
    }

    /**
     * Tells Craft whether this element type is localized per site.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * Tells Craft whether this element type supports statuses.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * Creates the custom query class for this element type.
     *
     * @return FavouriteItemQuery A custom element query instance.
     */
    public static function find(): FavouriteItemQuery
    {
        return new FavouriteItemQuery(static::class);
    }

    /**
     * Creates the condition model used by Craft element indexes.
     *
     * @return ElementConditionInterface The condition object used by Craft element indexes.
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(FavouriteItemCondition::class, [static::class]);
    }

    /**
     * Defines sort options available in the element index.
     *
     * @return array The requested array of data.
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('super-favourite', 'Sort Order'),
                'orderBy' => 'super_favourite_items.sortOrder',
                'attribute' => 'sortOrder',
            ],
            [
                'label' => Craft::t('super-favourite', 'Date Created'),
                'orderBy' => 'elements.dateCreated',
                'attribute' => 'dateCreated',
                'defaultDir' => 'desc',
            ],
            [
                'label' => Craft::t('super-favourite', 'Date Updated'),
                'orderBy' => 'elements.dateUpdated',
                'attribute' => 'dateUpdated',
                'defaultDir' => 'desc',
            ],
        ];
    }

    /**
     * Defines sidebar sources available in the element index.
     *
     * @param string $context The element index context requesting sources.
     *
     * @return array The requested array of data.
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('super-favourite', 'All Favourites'),
                'defaultSort' => ['dateCreated', 'desc'],
            ],
        ];

        // Dynamically get all registered element types from Craft
        $allElementTypes = Craft::$app->getElements()->getAllElementTypes();

        foreach ($allElementTypes as $elementType) {
            // Exclude our own element types from the sources
            if ($elementType === self::class ||
                $elementType === Collection::class) {
                continue;
            }

            // Get the display name for this element type
            $displayName = $elementType::pluralDisplayName();

            $sources[] = [
                'key' => 'type:' . $elementType,
                'label' => $displayName,
                'criteria' => ['favouritedElementType' => $elementType],
            ];
        }

        return $sources;
    }


    /**
     * Defines bulk actions available in the element index.
     *
     * @param string $source The selected element index source, or null for general index setup.
     *
     * @return array The requested array of data.
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Set Status dropdown
        $actions[] = SetStatus::class;

        // View action
        $actions[] = [
            'type' => View::class,
            'label' => Craft::t('super-favourite', 'View Favourite'),
        ];

        // Edit action with custom label
        $actions[] = [
            'type' => Edit::class,
            'label' => Craft::t('super-favourite', 'Edit Favourite'),
        ];

        // Delete action
        $actions[] = Delete::class;

        // Restore action (for trashed elements)
        $actions[] = Restore::class;

        return $actions;
    }

    /**
     * Defines optional table columns for the element index.
     *
     * @return array The requested array of data.
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'elementId'    => ['label' => Craft::t('super-favourite', 'Element')],
            'elementType'  => ['label' => Craft::t('super-favourite', 'Element Type')],
            'userId'       => ['label' => Craft::t('super-favourite', 'User')],
            'collectionId' => ['label' => Craft::t('super-favourite', 'Collection')],
            'notes'        => ['label' => Craft::t('super-favourite', 'Notes')],
            'sortOrder'    => ['label' => Craft::t('super-favourite', 'Sort Order')],
            'dateCreated'  => ['label' => Craft::t('super-favourite', 'Date Created')],
            'dateUpdated'  => ['label' => Craft::t('super-favourite', 'Date Updated')],
        ];
    }

    /**
     * Defines default table columns for the element index.
     *
     * @param string $source The selected element index source, or null for general index setup.
     *
     * @return array The requested array of data.
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['elementId', 'collectionId', 'dateCreated'];
    }

    /**
     * Defines attributes included in Craft element search.
     *
     * @return array The requested array of data.
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['notes'];
    }

    /**
     * Returns this element's Control Panel edit URL.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('super-favourite/favourites/' . $this->id);
    }

    /**
     * Returns the Control Panel edit URL Craft uses internally.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    protected function cpEditUrl(): ?string
    {
        return $this->getCpEditUrl();
    }

    /**
     * Returns the view URL for this element when Craft asks for one.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public function getUrl(): ?string
    {
        // Return the CP edit URL for now
        // You can customize this to point to a frontend view if you have one
        return $this->getCpEditUrl();
    }

    /**
     * Renders custom table-cell HTML for plugin-specific element attributes.
     *
     * @param string $attribute The table attribute key being rendered.
     *
     * @return string The requested string value.
     */
    protected function attributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'userId':
                $user = $this->getUser();
                return $user ? Cp::elementChipHtml($user) : '';

            case 'collectionId':
                $collection = $this->getCollection();
                return $collection ? Cp::elementChipHtml($collection) : '';

            case 'elementId':
                $element = $this->getFavouritedElement();
                return $element ? Cp::elementChipHtml($element) : '';

            case 'elementType':
                return $this->getElementTypeLabel();
        }

        return parent::attributeHtml($attribute);
    }

    /**
     * Tells Craft whether this element can be deleted.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function getIsDeletable(): bool
    {
        return true;
    }

    /**
     * Checks whether a user can save this element.
     *
     * @param User $user The Craft user whose permissions should be checked.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function canSave(User $user): bool
    {
        return $user->can('super-favourite:manage-favourites');
    }

    /**
     * Checks whether a user can view this element.
     *
     * @param User $user The Craft user whose permissions should be checked.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function canView(User $user): bool
    {
        return $user->can('super-favourite:view-favourites');
    }

    /**
     * Checks whether a user can delete this element.
     *
     * @param User $user The Craft user whose permissions should be checked.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function canDelete(User $user): bool
    {
        return $user->can('super-favourite:manage-favourites');
    }

    /**
     * Returns the related user element, caching the lookup for this request.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getUser()
    {
        if ($this->_user === null && $this->userId) {
            $this->_user = User::find()->id($this->userId)->one() ?: false;
        }
        return $this->_user ?: null;
    }

    /**
     * Returns the related collection element, caching the lookup for this request.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getCollection()
    {
        if ($this->_collection === null && $this->collectionId) {
            $this->_collection = Collection::find()
                ->id($this->collectionId)
                ->status(null)
                ->one() ?: false;
        }
        return $this->_collection ?: null;
    }

    /**
     * Returns the Craft element represented by this favourite item.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getFavouritedElement()
    {
        if ($this->_favouritedElement === null && $this->elementId && $this->elementType) {
            if (class_exists($this->elementType)) {
                $this->_favouritedElement = $this->elementType::find()
                    ->id($this->elementId)
                    ->status(null)
                    ->one() ?: false;
            } else {
                $this->_favouritedElement = false;
            }
        }
        return $this->_favouritedElement ?: null;
    }

    /**
     * Returns a readable label for the favourited element class.
     *
     * @return string The requested string value.
     */
    public function getElementTypeLabel(): string
    {
        if (!$this->elementType) {
            return '';
        }

        $parts = explode('\\', $this->elementType);
        return end($parts);
    }

    /**
     * Normalizes data before Craft saves the element.
     *
     * @param bool $isNew Whether Craft is saving a new element instead of updating an existing one.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function beforeSave(bool $isNew): bool
    {
        // Set title to element title or ID
        $element = $this->getFavouritedElement();
        $this->title = $element ? $element->title : 'Element #' . $this->elementId;

        return parent::beforeSave($isNew);
    }

    /**
     * Writes element data to the plugin's custom record table after Craft saves it.
     *
     * @param bool $isNew Whether Craft is saving a new element instead of updating an existing one.
     *
     * @return void Nothing is returned.
     */
    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new FavouriteItemRecord();
            $record->id = $this->id;
        } else {
            $record = FavouriteItemRecord::findOne($this->id);
            if (!$record) {
                throw new Exception('Invalid favourite item ID: ' . $this->id);
            }
        }

        $record->userId = $this->userId;
        $record->collectionId = $this->collectionId;
        $record->elementId = $this->elementId;
        $record->elementType = $this->elementType;
        $record->sortOrder = $this->sortOrder;
        $record->notes = $this->notes;

        $record->save(false);

        parent::afterSave($isNew);
    }

    /**
     * Defines validation rules for this model or element.
     *
     * @return array The requested array of data.
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['userId', 'collectionId', 'elementId', 'elementType'], 'required'];
        $rules[] = [['elementType'], 'string', 'max' => 255];
        $rules[] = [['notes'], 'string'];
        $rules[] = [['sortOrder'], 'integer'];

        // Custom validators keep controller saves Craft-like: assign fields, save,
        // then return every model error at once.
        $rules[] = ['elementId', 'validateFavouritedElement'];
        $rules[] = ['collectionId', 'validateCollection'];
        $rules[] = ['elementType', 'validateAllowedElementType'];
        $rules[] = ['elementId', 'validateDuplicateFavourite'];
        $rules[] = ['collectionId', 'validateMaxFavouritesPerCollection'];

        return $rules;
    }

    /**
     * Validates that the posted element ID resolves to a Craft element.
     *
     * @return void Nothing is returned.
     */
    public function validateFavouritedElement(): void
    {
        if (!$this->elementId) {
            return;
        }

        if (!$this->getFavouritedElement()) {
            $this->addError('elementId', Craft::t('super-favourite', 'Could not determine the element type for this favourite.'));
        }
    }

    /**
     * Validates that the selected collection exists and can receive this favourite.
     *
     * @return void Nothing is returned.
     */
    public function validateCollection(): void
    {
        if (!$this->collectionId) {
            return;
        }

        $collection = $this->getCollection();

        if (!$collection) {
            $this->addError('collectionId', Craft::t('super-favourite', 'Collection not found.'));
            return;
        }

        if ($collection->userId !== null && $collection->userId !== $this->userId) {
            $this->addError('collectionId', Craft::t('super-favourite',
                'You do not have permission to add items to this collection. Only the collection owner can add items to personal collections.'
            ));
        }
    }

    /**
     * Validates that the favourite's element type is allowed by the selected collection.
     *
     * @return void Nothing is returned.
     */
    public function validateAllowedElementType(): void
    {
        if (!$this->collectionId || !$this->elementType) {
            return;
        }

        $collection = $this->getCollection();

        if (!$collection) {
            return;
        }

        $allowedElementTypes = $collection->allowedElementTypes;

        if (empty($allowedElementTypes) || in_array($this->elementType, $allowedElementTypes, true)) {
            return;
        }

        $elementTypeLabel = $this->elementType;
        if (class_exists($this->elementType)) {
            $elementTypeLabel = $this->elementType::displayName();
        }

        $this->addError('elementType', Craft::t('super-favourite',
            '{elementType} is not allowed in this collection. Please select a different collection or element type.',
            ['elementType' => $elementTypeLabel]
        ));
    }

    /**
     * Validates that the same user/collection/element favourite does not already exist.
     *
     * @return void Nothing is returned.
     */
    public function validateDuplicateFavourite(): void
    {
        if (!$this->userId || !$this->collectionId || !$this->elementId) {
            return;
        }

        $query = FavouriteItem::find()
            ->userId($this->userId)
            ->collectionId($this->collectionId)
            ->elementId($this->elementId);

        if ($this->id) {
            $query->id('not ' . $this->id);
        }

        $existingFavourite = $query->one();

        if ($existingFavourite) {
            $this->addError('elementId', Craft::t('super-favourite', 'This item is already in the selected collection.'));
        }
    }

    /**
     * Validates the configured maximum favourites per collection.
     *
     * @return void Nothing is returned.
     */
    public function validateMaxFavouritesPerCollection(): void
    {
        if (!$this->collectionId) {
            return;
        }

        // Only validate for new favourites (id is null for new elements)
        if ($this->id !== null) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();
        $maxFavourites = $settings->maxFavouritesPerCollection;

        // 0 means unlimited
        if ($maxFavourites === 0) {
            return;
        }

        // Count existing favourites in this collection
        $existingCount = FavouriteItem::find()
            ->collectionId($this->collectionId)
            ->count();

        if ($existingCount >= $maxFavourites) {
            $this->addError('collectionId', Craft::t('super-favourite',
                'Maximum number of favourites ({max}) reached for this collection. Please remove some favourites before adding new ones.',
                ['max' => $maxFavourites]
            ));
        }
    }
}

