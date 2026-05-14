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

- `id` - optional; include when editing the collection element.
- `name` - required.
- `handle` - optional; generated when empty.
- `description` - optional.
- `isDefault` - optional boolean.
- `userId` - optional; empty means global collection.
- `allowedElementTypes[]` - optional; omit all values to allow every element type.
- `fields[...]` - optional custom field values.

Permission:

- `super-favourite:manage-collections`
- `super-favourite:manage-global-collections` is also required when creating or editing a global/default collection.

### Delete Collection

Action:

```text
super-favourite/collection/delete
```

Method: `POST`

Fields:

- `id` - required collection element ID.
- `deleteItems` - optional boolean. When truthy, the collection is deleted immediately and favourite item cleanup is queued.

Requires login. Admins can delete any collection; users can delete their own collections. Global collections require admin access or `super-favourite:manage-global-collections`. Without `deleteItems`, collections that still contain enabled favourite items cannot be deleted.

Non-JSON failures return the failed `collection` model via Craft's model failure response. JSON failures return `success: false` with an error message.

### Set Default Collection

Action:

```text
super-favourite/collection/set-default
```

Method: `POST`

Fields:

- `id` - required collection element ID.

Permission:

- `super-favourite:manage-collections`
- `super-favourite:manage-global-collections`

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
- `super-favourite:manage-global-collections` is also required when the submitted order includes global collections.

## Favourite Actions

### Add Favourite

Action:

```text
super-favourite/favourite/add
```

Method: `POST`

Fields:

- `elementId` - required.
- `elementType` - optional class name; derived from `elementId` when omitted.
- `collectionId` - required valid collection ID.
- `notes` - optional.

Requires login.

### Save Favourite

Action:

```text
super-favourite/favourite/save
```

Method: `POST`

Fields:

- `id` - optional; include when editing the favourite item element.
- `elementType` - optional; derived from `elementId` when omitted.
- `elementId` - required. CP element select may submit an array.
- `userId` - optional for frontend; current user is used when empty.
- `collectionId` - required valid collection ID.
- `notes` - optional.
- `fields[...]` - optional custom field values.

CP requests require `super-favourite:manage-favourites`. Frontend requests require login.

Non-JSON failures return the failed `favouriteItem` model via Craft's model failure response. JSON failures return `success: false` with an error message/errors payload.

### Remove Favourite by Element

Action:

```text
super-favourite/favourite/remove
```

Method: `POST`

Fields:

- `elementId` - required.
- `collectionId` - optional. If omitted, matching favourites for the element can be removed across collections.

Requires login.

Non-JSON failures return the failed `favourite` model when a favourite item can be identified.

### Toggle Favourite

Action:

```text
super-favourite/favourite/toggle
```

Method: `POST`

Fields:

- `elementId` - required.
- `elementType` - optional; derived from `elementId` when omitted.
- `collectionId` - required.

Requires login.

Non-JSON failures return the failed `favourite` model. JSON failures return `success: false` with an error message/errors payload.

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

- `id` - required favourite item element ID.
- `collectionId` - required destination collection.

Requires login.

### Delete Favourite by Favourite ID

Action:

```text
super-favourite/favourite/delete
```

Method: `POST`

Fields:

- `id` - required favourite item element ID.

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

- `elementType` - optional; derived from `elementId` when omitted.
- `collectionId` - required.
- `limit` - optional, defaults to `10`.

Anonymous access is allowed, but favourite status is only meaningful when a user is logged in.

