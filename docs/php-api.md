# PHP API

Use the plugin services from custom modules, plugins, queue jobs, console commands, or controllers.

```php
use amici\SuperFavourite\Plugin;
```

## Favourite Service

Access:

```php
$service = Plugin::getInstance()->favourite;
```

### Add a Favourite

```php
$favourite = Plugin::getInstance()->favourite->addFavourite(
    elementId: 123,
    elementType: \craft\elements\Entry::class,
    userId: Craft::$app->getUser()->getId(),
    collectionId: 5,
    notes: 'Read later'
);
```

Returns the created/existing `FavouriteItem`, or `false`.

If `userId` is `null`, the current logged-in user is used. `collectionId` is required; the service returns `false` when it is omitted, invalid, owned by another user, or disallows the element type.

### Remove a Favourite

```php
$success = Plugin::getInstance()->favourite->removeFavourite(
    elementId: 123,
    userId: Craft::$app->getUser()->getId(),
    collectionId: 5,
    hardDelete: true
);
```

Set `collectionId` to `null` to remove matching favourites from all collections for that user.

### Toggle a Favourite

```php
$result = Plugin::getInstance()->favourite->toggleFavourite(
    elementId: 123,
    elementType: \craft\elements\Entry::class,
    collectionId: 5,
    userId: Craft::$app->getUser()->getId()
);

if ($result['success']) {
    $action = $result['action']; // added or removed
}
```

### Check Duplicate

```php
$existing = Plugin::getInstance()->favourite->checkDuplicate(
    userId: 1,
    collectionId: 5,
    elementId: 123
);
```

Returns a `FavouriteItem` or `null`.

### Check Favourited State

```php
$isFavourited = Plugin::getInstance()->favourite->isFavourited(
    elementId: 123,
    userId: 1,
    collectionId: 5
);
```

### Get Favourite IDs

```php
$ids = Plugin::getInstance()->favourite->getFavouritedElementIds(
    userId: 1,
    collectionId: 5,
    elementType: \craft\elements\Entry::class
);
```

### Get Favourite Items

```php
$items = Plugin::getInstance()->favourite->getFavourites(
    userId: 1,
    collectionId: 5,
    elementType: \craft\elements\Entry::class
);
```

### Move a Favourite

```php
$success = Plugin::getInstance()->favourite->moveFavourite(
    favouriteId: 10,
    newCollectionId: 12
);
```

### Count Favourites

```php
$count = Plugin::getInstance()->favourite->getFavouriteCount(
    userId: 1,
    collectionId: 5
);
```

### Get Elements With Favourite Status

```php
$rows = Plugin::getInstance()->favourite->getElementsWithFavouriteStatus(
    elementType: \craft\elements\Entry::class,
    collectionId: 5,
    userId: 1,
    limit: 10
);
```

Each row includes:

- `id`
- `title`
- `url`
- `type`
- `isFavourited`
- `favouriteId`
- `previewUrl` for assets

## Collection Service

Access:

```php
$service = Plugin::getInstance()->collection;
```

### Create or Update Collections

For the most control, create/save the `Collection` element directly. This covers user, global, default, allowed element type, and custom field use cases in one pattern.

```php
$collection = new \amici\SuperFavourite\elements\Collection();
$collection->name = 'My Wishlist';
$collection->handle = 'my-wishlist';
$collection->description = 'Things I want to save';
$collection->userId = 1; // null = global collection
$collection->isDefault = false; // true = global default fallback collection
$collection->allowedElementTypes = [
    \craft\elements\Entry::class,
    \craft\elements\Asset::class,
]; // [] = all element types
$collection->setFieldValue('shortIntro', 'Optional custom field value');

Craft::$app->getElements()->saveElement($collection);
```

The `CollectionService::createCollection()` helper is useful for simple user-owned collections. The element pattern above is clearer when you need global/default collections, allowed element type restrictions, or custom fields.

### Delete a Collection

```php
$success = Plugin::getInstance()->collection->deleteCollection(
    collectionId: 5,
    deleteItems: false
);
```

When `deleteItems` is `false`, the delete is blocked if the collection still has enabled favourite items. When `deleteItems` is `true`, the collection is deleted immediately and the plugin queues a background job to clean up favourite items.

### Get Collections for a User

```php
$collections = Plugin::getInstance()->collection->getUserCollections(
    userId: 1
);
```

This returns global collections and collections owned by the user.

### Get by Handle

```php
$collection = Plugin::getInstance()->collection->getCollectionByHandle(
    handle: 'wishlist',
    userId: 1
);
```

### Get Default Collection

```php
$collection = Plugin::getInstance()->collection->getDefaultCollection(
    userId: 1
);
```

### Set Default Collection

```php
$success = Plugin::getInstance()->collection->setDefaultCollection(
    collectionId: 5
);
```

### Reorder Collections

```php
$success = Plugin::getInstance()->collection->reorderCollections([5, 8, 12]);
```

## Element Queries

```php
use amici\SuperFavourite\elements\Collection;
use amici\SuperFavourite\elements\FavouriteItem;

$collections = Collection::find()
    ->userId(1)
    ->all();

$favourites = FavouriteItem::find()
    ->collectionId(5)
    ->favouritedElementType(\craft\elements\Entry::class)
    ->all();
```

## Service Events

The collection and favourite services expose Yii events so custom modules/plugins can validate, cancel, audit, or react to plugin operations.

Register listeners from your module or plugin `init()` method:

```php
use yii\base\Event;
use craft\events\ModelEvent;
use amici\SuperFavourite\Plugin;
use amici\SuperFavourite\services\CollectionService;
use amici\SuperFavourite\services\FavouriteService;

Event::on(
    CollectionService::class,
    CollectionService::EVENT_BEFORE_DELETE_COLLECTION,
    function(ModelEvent $event) {
        $collection = $event->sender;

        if ($collection->handle === 'protected') {
            $collection->addError('id', 'This collection is protected.');
            $event->isValid = false;
        }
    }
);
```

Before events receive `craft\events\ModelEvent` and can cancel the operation by setting `$event->isValid = false`. The affected model/element is available as `$event->sender`.

### Collection Events

```php
use yii\base\Event;
use craft\events\ModelEvent;
use amici\SuperFavourite\services\CollectionService;

Event::on(
    CollectionService::class,
    CollectionService::EVENT_BEFORE_CREATE_COLLECTION,
    function(ModelEvent $event) {
        $collection = $event->sender;
        // Validate or modify the collection before it is saved.
    }
);

Event::on(
    CollectionService::class,
    CollectionService::EVENT_AFTER_CREATE_COLLECTION,
    function(ModelEvent $event) {
        $collection = $event->sender;
        // Run post-create side effects, such as logging or syncing.
    }
);

Event::on(
    CollectionService::class,
    CollectionService::EVENT_BEFORE_DELETE_COLLECTION,
    function(ModelEvent $event) {
        $collection = $event->sender;
        // Set $event->isValid = false to block deletion.
    }
);

Event::on(
    CollectionService::class,
    CollectionService::EVENT_AFTER_DELETE_COLLECTION,
    function(ModelEvent $event) {
        $collection = $event->sender;
        // React after the collection element has been deleted.
    }
);
```

Available collection events:

- `CollectionService::EVENT_BEFORE_CREATE_COLLECTION`
- `CollectionService::EVENT_AFTER_CREATE_COLLECTION`
- `CollectionService::EVENT_BEFORE_DELETE_COLLECTION`
- `CollectionService::EVENT_AFTER_DELETE_COLLECTION`

### Favourite Events

```php
use yii\base\Event;
use craft\events\ModelEvent;
use amici\SuperFavourite\services\FavouriteService;

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_BEFORE_ADD_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // Set $event->isValid = false to block adding the favourite.
    }
);

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_AFTER_ADD_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // React after a favourite has been saved.
    }
);

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_BEFORE_REMOVE_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // Set $event->isValid = false to skip removing this favourite.
    }
);

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_AFTER_REMOVE_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // React after a favourite has been removed.
    }
);

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_BEFORE_MOVE_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // Validate the move before the new collection is saved.
    }
);

Event::on(
    FavouriteService::class,
    FavouriteService::EVENT_AFTER_MOVE_FAVOURITE,
    function(ModelEvent $event) {
        $favourite = $event->sender;
        // React after a favourite has moved collections.
    }
);
```

Available favourite events:

- `FavouriteService::EVENT_BEFORE_ADD_FAVOURITE`
- `FavouriteService::EVENT_AFTER_ADD_FAVOURITE`
- `FavouriteService::EVENT_BEFORE_REMOVE_FAVOURITE`
- `FavouriteService::EVENT_AFTER_REMOVE_FAVOURITE`
- `FavouriteService::EVENT_BEFORE_MOVE_FAVOURITE`
- `FavouriteService::EVENT_AFTER_MOVE_FAVOURITE`

