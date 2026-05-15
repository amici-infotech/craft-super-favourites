<?php
namespace amici\SuperFavourite\elements;

use Craft;
use craft\base\Element;
use craft\elements\actions\Edit;
use craft\elements\actions\Restore;
use craft\elements\actions\SetStatus;
use craft\elements\actions\View;
use craft\elements\conditions\ElementConditionInterface;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use Exception;

use amici\SuperFavourite\elements\db\CollectionQuery;
use amici\SuperFavourite\records\CollectionRecord;
use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\conditions\CollectionCondition;
use amici\SuperFavourite\elements\actions\DeleteCollection;

/**
 * Collection Element
 *
 * Represents a user-created collection/list for organizing favourites
 *
 * @method void addError(string $attribute, string $error = '')
 * @method array getErrors(?string $attribute = null)
 * @method void setScenario(string $value)
 */
class Collection extends Element
{
    public ?int $userId = null;
    public ?string $name = null;
    public ?string $handle = null;
    public ?string $description = null;
    public bool $isDefault = false;
    public int $sortOrder = 0;
    public ?int $fieldLayoutId = null;
    public bool $allowDeleteWithFavouriteItems = false;
    public bool $allowUnsetDefault = false;

    private array $_allowedElementTypes = [];
    private User|false|null $_user = null;
    private ?int $_itemCount = null;

    /**
     * Returns the is global value.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function getIsGlobal(): bool
    {
        return $this->userId === null;
    }

    /**
     * Returns allowed element types as an array for templates and PHP code.
     *
     * @return array Element type class names. Empty array means all element types are allowed.
     */
    public function getAllowedElementTypes(): array
    {
        return $this->_allowedElementTypes;
    }

    /**
     * Normalizes assigned allowed element type data immediately.
     *
     * @param mixed $value Posted array, stored JSON string, single class name, "*" sentinel, or null.
     *
     * @return void Nothing is returned.
     */
    public function setAllowedElementTypes(mixed $value): void
    {
        $this->_allowedElementTypes = $this->normalizeAllowedElementTypes($value);
    }

    /**
     * Returns the singular display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function displayName(): string
    {
        return Craft::t('super-favourite', 'Collection');
    }

    /**
     * Returns the lower-case singular display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function lowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'collection');
    }

    /**
     * Returns the plural display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('super-favourite', 'Collections');
    }

    /**
     * Returns the lower-case plural display name Craft shows for this element type.
     *
     * @return string The requested string value.
     */
    public static function pluralLowerDisplayName(): string
    {
        return Craft::t('super-favourite', 'collections');
    }

    /**
     * Returns the reference handle Craft uses for this element type.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public static function refHandle(): ?string
    {
        return 'collection';
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
     * @return CollectionQuery A custom element query instance.
     */
    public static function find(): CollectionQuery
    {
        return new CollectionQuery(static::class);
    }

    /**
     * Creates the condition model used by Craft element indexes.
     *
     * @return ElementConditionInterface The condition object used by Craft element indexes.
     */
    public static function createCondition(): ElementConditionInterface
    {
        return Craft::createObject(CollectionCondition::class, [static::class]);
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
     * Defines sidebar sources available in the element index.
     *
     * @param string $context The element index context requesting sources.
     *
     * @return array The requested array of data.
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
            'label' => Craft::t('super-favourite', 'View Collection'),
        ];

        // Edit action with custom label
        $actions[] = [
            'type' => Edit::class,
            'label' => Craft::t('super-favourite', 'Edit Collection'),
        ];

        // Custom duplicate action that handles our Collection properly
        // $actions[] = DuplicateCollection::class;

        // Delete action with accurate validation failure messages.
        $actions[] = DeleteCollection::class;

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
            'handle'      => ['label' => Craft::t('super-favourite', 'Handle')],
            'userId'      => ['label' => Craft::t('super-favourite', 'User')],
            'itemCount'   => ['label' => Craft::t('super-favourite', 'Items')],
            'isDefault'   => ['label' => Craft::t('super-favourite', 'Default')],
            'dateCreated' => ['label' => Craft::t('super-favourite', 'Date Created')],
            'dateUpdated' => ['label' => Craft::t('super-favourite', 'Date Updated')],
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
        return ['handle', 'userId', 'itemCount', 'isDefault', 'dateCreated'];
    }

    /**
     * Defines attributes included in Craft element search.
     *
     * @return array The requested array of data.
     */
    protected static function defineSearchableAttributes(): array
    {
        return ['name', 'handle', 'description'];
    }

    /**
     * Returns this element's Control Panel edit URL.
     *
     * @return ?string The requested string value, or null when none exists.
     */
    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('super-favourite/collections/' . $this->id);
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
        if ($this->userId === null || $this->isDefault) {
            return $this->canManageGlobalCollections($user);
        }

        return $user->can('super-favourite:manage-collections');
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
        return $user->can('super-favourite:view-collections');
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
        if ($this->userId === null) {
            return $this->canManageGlobalCollections($user);
        }

        return $user->can('super-favourite:manage-collections');
    }

    /**
     * Validates deletion before Craft removes the element.
     *
     * @return bool True on success or when the condition matches; false otherwise.
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

        // Normal deletes are blocked while favourites exist. The collection service can
        // bypass this after it has scheduled favourite cleanup in the queue.
        $enabledItemsCount = FavouriteItem::find()
            ->collectionId($this->id)
            ->status(Element::STATUS_ENABLED)
            ->count();

        if ($enabledItemsCount > 0 && !$this->allowDeleteWithFavouriteItems) {
            $this->addError('id', Craft::t('super-favourite',
                'Cannot delete collection with {count} enabled favourite item(s). Please remove or disable the favourites first.',
                ['count' => $enabledItemsCount]
            ));
            return false;
        }

        return true;
    }

    /**
     * Checks whether a user can duplicate this element.
     *
     * @param User $user The Craft user whose permissions should be checked.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    public function canDuplicate(User $user): bool
    {
        if ($this->userId === null || $this->isDefault) {
            return $this->canManageGlobalCollections($user);
        }

        return $user->can('super-favourite:manage-collections');
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
     * Counts favourite items in this collection.
     *
     * @return int The requested integer value.
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
     * Returns all favourite item elements in this collection.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getItems()
    {
        return FavouriteItem::find()
            ->collectionId($this->id)
            ->all();
    }

    /**
     * Returns the default collection, or null if none exists.
     *
     * @return ?Collection The `?Collection` value produced by this method.
     */
    public static function getDefaultCollection(): ?Collection
    {
        return Collection::find()
            ->isDefault(true)
            ->where(['super_favourite_collections.userId' => null])
            ->one();
    }

    /**
     * Normalizes values before validation so model rules see the final save shape.
     *
     * @return bool Whether validation should continue.
     */
    public function beforeValidate(): bool
    {
        if ($this->isDefault) {
            $this->userId = null;
        }

        if (empty($this->handle) && !empty($this->name)) {
            $this->handle = $this->generateUniqueHandle($this->name);
        } elseif ($this->handle && $this->handleExists($this->handle)) {
            $this->handle = $this->generateUniqueHandle($this->handle);
        }

        return parent::beforeValidate();
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
        $this->title = $this->name;

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
                $existingDefault->allowUnsetDefault = true;
                Craft::$app->getElements()->saveElement($existingDefault, false);
            }
        }

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

        // Keep the element API array-based, but store selected types as JSON.
        // An empty array means "all element types" and is stored as null.
        $allowedElementTypes = $this->getAllowedElementTypes();
        $record->allowedElementTypes = empty($allowedElementTypes) ? null : json_encode($allowedElementTypes);

        $record->sortOrder = $this->sortOrder;

        $record->save(false);

        parent::afterSave($isNew);

        // Keep this element instance array-based for the rest of the request.
        $this->setAllowedElementTypes($allowedElementTypes);
    }

    /**
     * Normalizes database-backed values after Craft hydrates the element.
     *
     * @return void Nothing is returned.
     */
    public function afterFind(): void
    {
        parent::afterFind();
        $this->setAllowedElementTypes($this->allowedElementTypes ?? []);
    }

    /**
     * Converts stored or posted allowed element type data into the public array shape.
     *
     * @param mixed $value The stored JSON string, posted array, single class name, "*" sentinel, or null.
     *
     * @return array Element type class names. Empty array means all element types are allowed.
     */
    private function normalizeAllowedElementTypes(mixed $value): array
    {
        if ($value === null || $value === '' || $value === '*') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $types = array_values(array_filter($value, fn($type) => is_string($type) && $type !== '' && $type !== '*'));

        return in_array('*', $value, true) ? [] : $types;
    }

    /**
     * Generates a unique collection handle from a name or handle seed.
     *
     * @param string $name The collection name, or source text used to generate a handle.
     *
     * @return string The requested string value.
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
     * Checks whether a collection handle is already in use.
     *
     * @param string $handle The collection handle to save, filter by, or test for uniqueness.
     *
     * @return bool True on success or when the condition matches; false otherwise.
     */
    private function handleExists(string $handle): bool
    {
        $query = Collection::find()
            ->handle($handle)
            ->userId($this->userId);

        // Exclude current collection if it has an ID (not new)
        if ($this->id) {
            $query->id('not ' . $this->id);
        }

        return $query->exists();
    }

    /**
     * Checks whether the user can manage global/default collections.
     *
     * @param User $user The Craft user whose permissions should be checked.
     *
     * @return bool Whether the user has global collection management access.
     */
    private function canManageGlobalCollections(User $user): bool
    {
        return $user->admin || $user->can('super-favourite:manage-global-collections');
    }

    /**
     * Defines validation rules for this model or element.
     *
     * @return array The requested array of data.
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
        $rules[] = ['userId', 'validateGlobalPermission', 'on' => [Element::SCENARIO_LIVE, Element::SCENARIO_ESSENTIALS]];
        $rules[] = ['isDefault', 'validateDefaultState', 'on' => [Element::SCENARIO_LIVE, Element::SCENARIO_ESSENTIALS]];
        $rules[] = ['userId', 'validateMaxCollectionsPerUser', 'on' => [Element::SCENARIO_LIVE, Element::SCENARIO_ESSENTIALS]];
        // Note: handle uniqueness is enforced in beforeSave() automatically (like Craft does with slugs)

        return $rules;
    }


    /**
     * Validates that the current user can create or edit global/default collections.
     *
     * @return void Nothing is returned.
     */
    public function validateGlobalPermission(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        $currentUser = Craft::$app->getUser()->getIdentity();

        if (!$currentUser instanceof User) {
            $this->addError('userId', Craft::t('super-favourite', 'Please login to manage collections.'));
            return;
        }

        $wasGlobal = false;
        if ($this->id) {
            $existingRecord = CollectionRecord::findOne($this->id);
            $wasGlobal = $existingRecord && $existingRecord->userId === null;
        }

        if (($wasGlobal || $this->userId === null || $this->isDefault) && !$this->canManageGlobalCollections($currentUser)) {
            $this->addError('userId', Craft::t('super-favourite', 'You do not have permission to create or edit global collections.'));
        }
    }

    /**
     * Validates default collection state before save.
     *
     * @return void Nothing is returned.
     */
    public function validateDefaultState(): void
    {
        if (!$this->id || $this->isDefault || $this->allowUnsetDefault) {
            return;
        }

        $existingRecord = CollectionRecord::findOne($this->id);

        if ($existingRecord && (bool)$existingRecord->isDefault) {
            $this->addError('isDefault', Craft::t('super-favourite',
                'The default collection cannot be unset directly. Set another global collection as default instead.'
            ));
        }
    }

    /**
     * Validates the configured maximum collections per user.
     *
     * @return void Nothing is returned.
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

