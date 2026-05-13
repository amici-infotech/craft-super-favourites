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

If `userId` is `null`, the current logged-in user is used. If `collectionId` is `null`, the service tries to get or create a default collection for the user.

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

### Create a User Collection

```php
$collection = Plugin::getInstance()->collection->createCollection(
    userId: 1,
    name: 'My Wishlist',
    handle: 'my-wishlist',
    description: 'Things I want to save',
    isDefault: false
);
```

### Create a Global Collection

The service signature expects an integer user ID in the current code, while the element/controller support `null` for global collections. To create global collections programmatically, instantiate and save the element directly:

```php
$collection = new \amici\SuperFavourite\elements\Collection();
$collection->name = 'Staff Picks';
$collection->handle = 'staff-picks';
$collection->description = 'Shared recommendations';
$collection->userId = null;
$collection->allowedElementTypes = null;

Craft::$app->getElements()->saveElement($collection);
```

### Update a Collection

```php
$collection = Plugin::getInstance()->collection->updateCollection(
    collectionId: 5,
    attributes: [
        'name' => 'Updated Wishlist',
        'description' => 'Updated description',
    ]
);
```

### Delete a Collection

```php
$success = Plugin::getInstance()->collection->deleteCollection(
    collectionId: 5,
    deleteItems: false
);
```

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

