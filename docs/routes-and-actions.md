# Routes and Actions

This page lists the plugin's Control Panel routes and frontend/action endpoints.

## Control Panel Routes

```text
super-favourite
super-favourite/collections
super-favourite/collections/new
super-favourite/collections/<collectionId>

super-favourite/favourites
super-favourite/favourites/new
super-favourite/favourites/<favouriteId>

super-favourite/settings
super-favourite/settings/general
super-favourite/settings/collection-fields
super-favourite/settings/favourite-fields
```

## Collection Actions

### Save Collection

Action:

```text
super-favourite/collection/save
```

Method: `POST`

Fields:

- `collectionId` - optional; include when editing.
- `name` - required.
- `handle` - optional; generated when empty.
- `description` - optional.
- `isDefault` - optional boolean.
- `userId` - optional; empty means global collection.
- `allowedElementTypes` or `allowedElementTypes[]` - optional.
- `fields[...]` - optional custom field values.

Permission:

- `super-favourite:manage-collections`

### Delete Collection

Action:

```text
super-favourite/collection/delete
```

Method: `POST`

Fields:

- `collectionId` - required.
- `deleteItems` - optional boolean.

Requires login. Admins can delete any collection; users can delete their own collections and global collections if allowed by validation.

### Set Default Collection

Action:

```text
super-favourite/collection/set-default
```

Method: `POST`

Fields:

- `collectionId` - required.

Permission:

- `super-favourite:manage-collections`

### Reorder Collections

Action:

```text
super-favourite/collection/reorder
```

Method: `POST`

Fields:

- `ids` - array of collection IDs in the desired order.

Permission:

- `super-favourite:manage-collections`

## Favourite Actions

### Add Favourite

Action:

```text
super-favourite/favourite/add
```

Method: `POST`

Fields:

- `elementId` - required.
- `elementType` - required class name.
- `collectionId` - optional; default collection is used when empty.
- `notes` - optional.

Requires login.

### Save Favourite

Action:

```text
super-favourite/favourite/save
```

Method: `POST`

Fields:

- `favouriteId` - optional; include when editing.
- `elementType` - required.
- `elementId` - required. CP element select may submit an array.
- `userId` - optional for frontend; current user is used when empty.
- `collectionId` - optional; default collection is used when empty.
- `notes` - optional.
- `fields[...]` - optional custom field values.

CP requests require `super-favourite:manage-favourites`. Frontend requests require login.

### Remove Favourite by Element

Action:

```text
super-favourite/favourite/remove
```

Method: `POST`

Fields:

- `elementId` - required.
- `collectionId` - optional.

Requires login.

### Toggle Favourite

Action:

```text
super-favourite/favourite/toggle
```

Method: `POST`

Fields:

- `elementId` - required.
- `elementType` - required.
- `collectionId` - required.

Requires login.

Successful responses include data with:

- `success`
- `action` - `added` or `removed`
- `favouriteId`
- `elementId`
- `elementType`
- `collectionId`

### Check Favourite

Action:

```text
super-favourite/favourite/check
```

Method: `GET` or request params

Fields:

- `elementId` - required.
- `collectionId` - optional.

Requires login.

### Move Favourite

Action:

```text
super-favourite/favourite/move
```

Method: `POST`

Fields:

- `favouriteId` - required.
- `collectionId` - required destination collection.

Requires login.

### Delete Favourite by Favourite ID

Action:

```text
super-favourite/favourite/delete
```

Method: `POST`

Fields:

- `favouriteId` - required.

Requires login. The current user must own the favourite or be an admin.

## AJAX Helper Actions

### Get Allowed Types

Action:

```text
super-favourite/favourite/get-allowed-types
```

Fields:

- `collectionId` - required.

Anonymous access is allowed by the controller.

### Get Elements With Favourite Status

Action:

```text
super-favourite/favourite/get-elements
```

Fields:

- `elementType` - required.
- `collectionId` - required.
- `limit` - optional, defaults to `10`.

Anonymous access is allowed, but favourite status is only meaningful when a user is logged in.

