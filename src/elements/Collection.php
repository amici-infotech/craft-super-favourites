<?php
namespace amici\SuperFavourite\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Delete;
use craft\elements\actions\Edit;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\actions\View;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use Exception;

use amici\SuperFavourite\elements\db\CollectionQuery;
use amici\SuperFavourite\records\CollectionRecord;
use amici\SuperFavourite\Plugin;

/**
 * Collection Element
 *
 * Represents a user-created collection/list for organizing favourites
 */
class Collection extends Element
{
    public $userId;
    public $name;
    public $handle;
    public $description;
    public $isDefault = false;
    public $allowedElementTypes;
    public $sortOrder = 0;
    public ?int $fieldLayoutId = null;

    private $_user;
    private $_itemCount;

    /**
     * Check if this is a global collection
     *
     * @return bool
     */
    public function getIsGlobal(): bool
    {
        return $this->userId === null;
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('super-favourite', 'Collection');
    }

    /**
     * @inheritdoc
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'collection');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-favourite', 'Collections');
    }

    /**
     * @inheritdoc
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'collections');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle(): ?string
    {
        return 'collection';
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
        return new CollectionQuery(static::class);
    }

    /**
     * @inheritdoc
     */
    public static function createCondition(): \craft\elements\conditions\ElementConditionInterface
    {
        return Craft::createObject(\amici\SuperFavourite\conditions\CollectionCondition::class, [static::class]);
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        return [
            [
                'label' => Craft::t('super-favourite', 'Name'),
                'orderBy' => 'super_favourite_collections.name',
                'attribute' => 'name',
            ],
            [
                'label' => Craft::t('super-favourite', 'Default'),
                'orderBy' => 'super_favourite_collections.isDefault',
                'attribute' => 'isDefault',
            ],
            [
                'label' => Craft::t('super-favourite', 'Sort Order'),
                'orderBy' => 'super_favourite_collections.sortOrder',
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
        return [
            [
                'key' => '*',
                'label' => Craft::t('super-favourite', 'All Collections'),
                'defaultSort' => ['sortOrder', 'asc'],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Set Status dropdown
        $actions[] = SetStatus::class;

        // View action
        $actions[] = [
            'type' => View::class,
            'label' => Craft::t('super-favourite', 'View Collection'),
        ];

        // Edit action with custom label
        $actions[] = [
            'type' => Edit::class,
            'label' => Craft::t('super-favourite', 'Edit Collection'),
        ];

        // Custom duplicate action that handles our Collection properly
        // $actions[] = DuplicateCollection::class;

        // Delete action
        $actions[] = Delete::class;

        // Restore action (for trashed elements)
        $actions[] = Restore::class;

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        return [
            'handle'      => ['label' => Craft::t('super-favourite', 'Handle')],
            'userId'      => ['label' => Craft::t('super-favourite', 'User')],
            'itemCount'   => ['label' => Craft::t('super-favourite', 'Items')],
            'isDefault'   => ['label' => Craft::t('super-favourite', 'Default')],
            'dateCreated' => ['label' => Craft::t('super-favourite', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('super-favourite', 'Date Updated')],
        ];
    }

    /**
     * @inheritdoc
     */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return ['handle', 'userId', 'itemCount', 'isDefault', 'dateCreated'];
    }

    /**
     * @inheritdoc
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['name', 'handle', 'description'];
    }

    /**
     * @inheritdoc
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('super-favourite/collections/' . $this->id);
    }

    /**
     * @inheritdoc
     */
    protected function cpEditUrl(): ?string
    {
        return $this->getCpEditUrl();
    }

    /**
     * Get the URL for viewing this collection
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        // Return the CP edit URL for now
        // You can customize this to point to a frontend view if you have one
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
                return $user ? Cp::elementChipHtml($user) : Craft::t('super-favourite', 'Global');

            case 'isDefault':
                if ((bool)$this->isDefault) {
                    return '<span class="checkbox-icon" title="' . Craft::t('super-favourite', 'Default') . '" role="img" aria-label="' . Craft::t('super-favourite', 'Default') . '"></span>';
                }
                return '';

            case 'itemCount':
                return (string)$this->getItemCount();
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
        return $user->can('super-favourite:manage-collections');
    }

    /**
     * @inheritdoc
     */
    public function canView(User $user): bool
    {
        return $user->can('super-favourite:view-collections');
    }

    /**
     * @inheritdoc
     */
    public function canDelete(User $user): bool
    {
        return $user->can('super-favourite:manage-collections');
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        if (!parent::beforeDelete()) {
            return false;
        }

        // Prevent deleting default collections
        if ($this->isDefault) {
            $this->addError('isDefault', Craft::t('super-favourite',
                'Cannot delete the default collection.'
            ));
            return false;
        }

        // Prevent deleting collections that have enabled favourite items
        $enabledItemsCount = FavouriteItem::find()
            ->collectionId($this->id)
            ->status(\craft\elements\Element::STATUS_ENABLED)
            ->count();

        if ($enabledItemsCount > 0) {
            $this->addError('id', Craft::t('super-favourite',
                'Cannot delete collection with {count} enabled favourite item(s). Please remove or disable the favourites first.',
                ['count' => $enabledItemsCount]
            ));
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function canDuplicate(User $user): bool
    {
        return $user->can('super-favourite:manage-collections');
    }

    /**
     * Get the user who owns this collection
     */
    public function getUser()
    {
        if ($this->_user === null && $this->userId) {
            $this->_user = User::find()->id($this->userId)->one() ?: false;
        }
        return $this->_user ?: null;
    }

    /**
     * Get the count of items in this collection
     */
    public function getItemCount(): int
    {
        if ($this->_itemCount === null) {
            $this->_itemCount = FavouriteItem::find()
                ->collectionId($this->id)
                ->count();
        }
        return $this->_itemCount;
    }

    /**
     * Get all items in this collection
     */
    public function getItems()
    {
        return FavouriteItem::find()
            ->collectionId($this->id)
            ->all();
    }

    /**
     * Get the default global collection
     * Only one global collection can be default at a time
     *
     * @return Collection|null
     */
    public static function getDefaultCollection(): ?Collection
    {
        return Collection::find()
            ->isDefault(true)
            ->where(['super_favourite_collections.userId' => null])
            ->one();
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Generate handle if empty
        if (empty($this->handle) && !empty($this->name)) {
            $this->handle = $this->generateUniqueHandle($this->name);
        } elseif ($this->handle && $this->handleExists($this->handle)) {
            // If handle was provided but already exists, make it unique
            $this->handle = $this->generateUniqueHandle($this->handle);
        }

        $this->title = $this->name;

        // Default collections must be global (userId = null)
        // If isDefault is true, clear userId
        if ($this->isDefault) {
            $this->userId = null;
        }

        // If this collection is being set as default, unset any other default collections
        // Only one global collection can be default at a time
        if ($this->isDefault) {
            // Find any other default collections and unset them
            $existingDefault = Collection::find()
                ->isDefault(true)
                ->where(['super_favourite_collections.userId' => null])
                ->andWhere(['not', ['elements.id' => $this->id]]) // Exclude current collection
                ->one();

            if ($existingDefault) {
                $existingDefault->isDefault = false;
                Craft::$app->getElements()->saveElement($existingDefault, false);
            }
        }

        return parent::beforeSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew): void
    {
        if ($isNew) {
            $record = new CollectionRecord();
            $record->id = $this->id;
        } else {
            $record = CollectionRecord::findOne($this->id);
            if (!$record) {
                throw new Exception('Invalid collection ID: ' . $this->id);
            }
        }

        $record->userId = $this->userId;
        $record->name = $this->name;
        $record->handle = $this->handle;
        $record->description = $this->description;
        $record->isDefault = (bool)$this->isDefault; // Explicit boolean cast

        // Convert allowedElementTypes to JSON for storage in database
        if (is_array($this->allowedElementTypes)) {
            $record->allowedElementTypes = json_encode($this->allowedElementTypes);
        } elseif ($this->allowedElementTypes === '*' || $this->allowedElementTypes === null) {
            $record->allowedElementTypes = null; // Store null for "All"
        } else {
            $record->allowedElementTypes = $this->allowedElementTypes; // Already a string
        }

        $record->sortOrder = $this->sortOrder;

        $record->save(false);

        parent::afterSave($isNew);

        // After saving to the record, decode back for the element instance
        // This ensures the element has the correct value if it's used again in the same request
        $this->allowedElementTypes = $record->allowedElementTypes;
        $this->_decodeAllowedElementTypes();
    }

    /**
     * @inheritdoc
     */
    public function afterFind(): void
    {
        parent::afterFind();
        $this->_decodeAllowedElementTypes();
    }

    /**
     * Decode allowedElementTypes from database format to form format
     */
    private function _decodeAllowedElementTypes(): void
    {
        // Decode JSON allowedElementTypes back to array or '*' for display
        if (is_string($this->allowedElementTypes) && !empty($this->allowedElementTypes)) {
            $decoded = json_decode($this->allowedElementTypes, true);
            if ($decoded !== null) {
                $this->allowedElementTypes = $decoded;
            }
        } elseif ($this->allowedElementTypes === null || $this->allowedElementTypes === '') {
            // null/empty in database = "All" in the form
            $this->allowedElementTypes = '*';
        }
    }

    /**
     * Generate a unique handle from a name (similar to how Craft generates unique slugs)
     * Automatically adds -1, -2, etc. if the handle already exists
     */
    private function generateUniqueHandle(string $name): string
    {
        // Generate base handle from name
        $handle = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', $name));

        if (empty($handle)) {
            $handle = 'collection';
        }

        $baseHandle = $handle;
        $i = 1;
        $maxAttempts = 100; // Prevent infinite loop

        // Keep trying until we find a unique handle (like Craft does with slugs)
        while ($this->handleExists($handle) && $i <= $maxAttempts) {
            $handle = $baseHandle . $i;
            $i++;
        }

        return $handle;
    }

    /**
     * Check if a handle already exists for this user
     */
    private function handleExists(string $handle): bool
    {
        $query = Collection::find()
            ->handle($handle);

        // Exclude current collection if it has an ID (not new)
        if ($this->id) {
            $query->id('not ' . $this->id);
        }

        return $query->exists();
    }

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name', 'handle'], 'string', 'max' => 255];
        $rules[] = [['description'], 'string'];
        $rules[] = [['isDefault'], 'boolean'];
        $rules[] = [['sortOrder'], 'integer'];

        // Custom validators
        $rules[] = ['userId', 'validateMaxCollectionsPerUser', 'on' => [Element::SCENARIO_LIVE, Element::SCENARIO_ESSENTIALS]];
        // Note: handle uniqueness is enforced in beforeSave() automatically (like Craft does with slugs)

        return $rules;
    }


    /**
     * Validate that the user hasn't exceeded the maximum number of collections
     */
    public function validateMaxCollectionsPerUser(): void
    {
        // Only validate for user collections (not global)
        if ($this->userId === null) {
            return;
        }

        // Only validate for new collections (id is null for new elements)
        if ($this->id !== null) {
            return;
        }

        $settings = Plugin::getInstance()->getSettings();
        $maxCollections = $settings->maxCollectionsPerUser;

        // 0 means unlimited
        if ($maxCollections === 0) {
            return;
        }

        // Count existing collections for this user
        $existingCount = Collection::find()
            ->userId($this->userId)
            ->count();

        if ($existingCount >= $maxCollections) {
            $this->addError('userId', Craft::t('super-favourite',
                'Maximum number of collections ({max}) reached. Please delete some collections before creating new ones.',
                ['max' => $maxCollections]
            ));
        }
    }
}

