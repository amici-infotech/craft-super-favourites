<?php
namespace amici\SuperFavourite\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Restore;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use Exception;

use amici\SuperFavourite\elements\db\FavouriteItemQuery;
use amici\SuperFavourite\records\FavouriteItemRecord;
use amici\SuperFavourite\Plugin;

/**
 * FavouriteItem Element
 *
 * Represents a single favourited element in a collection
 */
class FavouriteItem extends Element
{
    // Properties
    public $userId;
    public $collectionId;
    public $elementId;
    public $elementType;
    public $sortOrder = 0;
    public $notes;
    public ?int $fieldLayoutId = null;

    private $_user;
    private $_collection;
    private $_favouritedElement;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('super-favourite', 'Favourite Item');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'favourite item');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-favourite', 'Favourite Items');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'favourite items');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'favouriteItem';
    }

    /**
     * @inheritdoc
     */
    public static function trackChanges(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout(): ?\craft\models\FieldLayout
    {
        return Craft::$app->getFields()->getLayoutByType(self::class);
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new FavouriteItemQuery(static::class);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        $actions[] = Delete::class;
        $actions[] = Restore::class;

        return $actions;
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['elementId', 'collectionId', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['notes'];
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('super-favourite/favourites/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return $this->getCpEditUrl();
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function getIsDeletable(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canSave(User $user): bool
    {
        return $user->can('super-favourite:manage-favourites');
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return $user->can('super-favourite:view-favourites');
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return $user->can('super-favourite:manage-favourites');
    }

    /**
     * Get the user who owns this favourite
     */
    public function getUser()
    {
        if ($this->_user === null && $this->userId) {
            $this->_user = User::find()->id($this->userId)->one() ?: false;
        }
        return $this->_user ?: null;
    }

    /**
     * Get the collection this favourite belongs to
     */
    public function getCollection()
    {
        if ($this->_collection === null && $this->collectionId) {
            $this->_collection = Collection::find()->id($this->collectionId)->one() ?: false;
        }
        return $this->_collection ?: null;
    }

    /**
     * Get the actual element that was favourited
     */
    public function getFavouritedElement()
    {
        if ($this->_favouritedElement === null && $this->elementId && $this->elementType) {
            if (class_exists($this->elementType)) {
                $this->_favouritedElement = $this->elementType::find()
                    ->id($this->elementId)
                    ->one() ?: false;
            } else {
                $this->_favouritedElement = false;
            }
        }
        return $this->_favouritedElement ?: null;
    }

    /**
     * Get a human-readable label for the element type
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
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Set title to element title or ID
        $element = $this->getFavouritedElement();
        $this->title = $element ? $element->title : 'Element #' . $this->elementId;

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['userId', 'collectionId', 'elementId', 'elementType'], 'required'];
        $rules[] = [['elementType'], 'string', 'max' => 255];
        $rules[] = [['notes'], 'string'];
        $rules[] = [['sortOrder'], 'integer'];

        return $rules;
    }
}

