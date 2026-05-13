# Installation and Setup

## Requirements

- Craft CMS 5
- PHP 8.0.2 or newer
- A logged-in Craft user for frontend favourite actions

## Install From a Local Path Repository

Add the plugin path to the root Craft project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../craft-plugins-v5/craft-super-favourite"
    }
  ]
}
```

Then install and enable it:

```bash
composer require amici/craft-super-favourite
php craft plugin/install super-favourite
```

You can also install it from the Control Panel at **Settings -> Plugins**.

## First Setup Checklist

1. Go to **Super Favourite -> Settings -> General**.
2. Set the plugin name if you want a different CP nav label.
3. Set collection and favourite limits if needed. Use `0` for unlimited.
4. Go to **Super Favourite -> Settings -> Collection Fields** if collections need custom fields.
5. Go to **Super Favourite -> Settings -> Favourite Fields** if favourite items need custom fields.
6. Go to **Super Favourite -> Collections** and create at least one default/global collection.

[screenshot for backend settings general page]

## Settings

The general settings screen currently supports:

- `Plugin Name` - label used in the Control Panel navigation.
- `Max Collections Per User` - `0` means unlimited.
- `Max Favourites Per Collection` - `0` means unlimited.

Field layout screens support:

- Collection custom fields.
- Favourite item custom fields.

[screenshot for collection field layout settings page]

[screenshot for favourite field layout settings page]

## Default Collection

The install migration creates a global collection named `Default` with handle `default`.

If a favourite is saved without `collectionId`, the plugin tries to use the default collection. In frontend forms, it is still best to pass a `collectionId` when the UI is collection-specific so users know where the item will go.

