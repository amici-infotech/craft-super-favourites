# Troubleshooting

## "No default collection found"

This happens when a favourite is saved without `collectionId` and no default collection exists.

Fix:

1. Create a global collection.
2. Enable **Default Collection**.
3. Save.

Or pass a specific `collectionId` in the form/action request.

## "You do not have permission to add items to this collection"

Personal collections can only receive favourites from their owner. If the collection has a `userId`, the favourite's `userId` must match it.

Fix:

- Use the current user's own collection.
- Use a global collection.
- Make sure your frontend form sends the expected `collectionId`.

## Element Type Is Not Allowed

Collections can restrict element types. If a favourite's `elementType` is not in `allowedElementTypes`, save fails.

Fix:

- Edit the collection in the CP.
- Add the element type under **Allowed Element Types**.
- Or set the collection to allow all element types.

## Collection Delete Fails

Collection deletion can fail when:

- The collection is the default collection.
- The collection still has enabled favourite items.
- The current user does not have permission.

Fix:

- Disable default before deleting, or choose another default.
- Remove favourite items first.
- Confirm the user has permission to manage/delete the collection.

## Favourite Toggle Requires Collection ID

The toggle endpoint requires:

- `elementId`
- `elementType`
- `collectionId`

If you want a simple "save to default collection" button, first resolve the default collection in Twig:

```twig
{% set collection = craft.superFavourite.getDefaultCollection() %}

{% if collection %}
    <input type="hidden" name="collectionId" value="{{ collection.id }}">
{% endif %}
```

## Current User Is Required

Most frontend actions require login. Use:

```twig
{% requireLogin %}
```

Or hide controls for guests:

```twig
{% if currentUser %}
    {# favourite controls #}
{% else %}
    <a href="/login">Log in to save favourites</a>
{% endif %}
```

## Custom Fields Are Not Saving

Make sure:

- The field is added to the correct field layout.
- Collection fields are posted to `super-favourite/collection/save`.
- Favourite item fields are posted to `super-favourite/favourite/save`.
- Field values are inside the `fields` namespace.

Example:

```twig
<input type="text" name="fields[reason]" value="">
```

## Global vs User Collections Look Wrong

Remember:

- Global collection: `userId` is `null`.
- User collection: `userId` is a Craft user ID.
- Default collection: global collection with `isDefault` enabled.

Twig checks:

```twig
{% if collection.userId is null %}
    Global
{% else %}
    Personal
{% endif %}
```

## Query Method Is Not Recognized by the IDE

The plugin uses custom Craft element query classes. If an IDE cannot infer methods such as `.collectionId()` or `.userId()`, make sure it is reading the plugin source and that `Collection::find()` / `FavouriteItem::find()` return the plugin query classes.

Runtime Craft queries still work when the plugin is installed correctly.

