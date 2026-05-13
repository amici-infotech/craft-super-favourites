# Backend Guide

The plugin adds a **Super Favourite** section to the Craft Control Panel.

## Navigation

Main sections:

- **Collections** - create and manage collection elements.
- **Favourites** - create and manage favourite item elements.
- **Settings** - configure general settings and field layouts.

[screenshot for backend plugin navigation]

## Collections Index

URL:

```text
/admin/super-favourite/collections
```

Use this screen to:

- View all collections.
- Search and sort collections.
- See collection owner/global status.
- See item counts.
- Edit a collection.
- Delete a collection when it is allowed.

[screenshot for backend collections index]

## Create a Global Collection

1. Go to **Super Favourite -> Collections**.
2. Click **New Collection**.
3. Enter **Name** and **Handle**.
4. Leave **Assign to User** empty.
5. Choose allowed element types, or leave all enabled.
6. Save.

Global collections are available site-wide. They are useful for shared lists and defaults.

[screenshot for backend create global collection form]

## Create a User Collection

1. Go to **Super Favourite -> Collections**.
2. Click **New Collection**.
3. Enter **Name** and **Handle**.
4. Select a user in **Assign to User**.
5. Choose allowed element types.
6. Save.

User collections are personal to one Craft user.

[screenshot for backend create user collection form]

## Create a Default Collection

1. Go to **Super Favourite -> Collections**.
2. Open or create a global collection.
3. Enable **Default Collection**.
4. Save.

When a collection is default:

- It is global.
- The user field is hidden and cleared.
- It is protected from deletion.

[screenshot for backend default collection setting]

## Collection Custom Fields

Go to:

```text
Super Favourite -> Settings -> Collection Fields
```

Use this when collections need editorial metadata, display settings, image fields, or any other Craft custom field.

[screenshot for backend collection field layout page]

After adding fields, the fields appear as tabs on the collection edit screen.

[screenshot for backend collection custom field tab]

## Favourites Index

URL:

```text
/admin/super-favourite/favourites
```

Use this screen to:

- View all favourite items.
- Filter by user.
- Filter by collection.
- Filter by favourited element type.
- Edit notes or custom fields.
- Delete favourite items.

[screenshot for backend favourites index]

## Create a Favourite in the CP

1. Go to **Super Favourite -> Favourites**.
2. Click **New Favourite Item**.
3. Choose an **Element Type**.
4. Select the actual element.
5. Select the user.
6. Select the collection, or leave empty to use the default collection.
7. Add notes or custom field values.
8. Save.

[screenshot for backend create favourite form]

## Favourite Custom Fields

Go to:

```text
Super Favourite -> Settings -> Favourite Fields
```

Use this when saved items need metadata such as reason, priority, reminder date, or editorial notes.

[screenshot for backend favourite field layout page]

## Delete Rules

Collections:

- Default collections cannot be deleted.
- Collections with enabled favourite items are protected at the element level.
- The collection delete action can optionally delete items when called through the service/controller, but element-level validation can still prevent unsafe deletion.

Favourite items:

- Favourite items can be deleted from the CP by users with manage permission.
- Frontend delete/remove actions require login and ownership/admin checks where applicable.

## Permissions

The plugin registers:

- `super-favourite:view-favourites`
- `super-favourite:manage-favourites`
- `super-favourite:view-collections`
- `super-favourite:manage-collections`

Craft's plugin access permission is also used for settings screens.

