# Super Favourite for Craft CMS 5

Super Favourite adds wishlist-style favourites to Craft CMS. Users can save Craft elements into collections, keep personal or global lists, add notes, and manage favourite data from the Control Panel or templates.

## Features

- Favourite Craft elements such as entries, assets, categories, users, tags, and Commerce elements.
- Organize saved items into global collections or user-owned collections.
- Restrict a collection to specific element types.
- Add custom fields to collections and favourite items.
- Manage collections and favourites from the Craft Control Panel.
- Use Twig variables, element queries, frontend actions, and PHP services.

## Requirements

- Craft CMS 5
- PHP 8.0.2 or newer

## Installation

For local development, add the plugin as a path repository in the Craft project's `composer.json`, then install it:

```bash
composer require amici/craft-super-favourite
php craft plugin/install super-favourite
```

You can also install it from **Settings -> Plugins** in the Craft Control Panel.

For the full setup flow, see [Installation and Setup](docs/installation.md).

## Documentation

Detailed docs are split by task:

- [Documentation Home](docs/README.md)
- [Installation and Setup](docs/installation.md)
- [Core Concepts](docs/concepts.md)
- [Backend Guide](docs/backend.md)
- [Frontend Forms](docs/frontend-forms.md)
- [Twig Usage](docs/twig-usage.md)
- [PHP API](docs/php-api.md)
- [Routes and Actions](docs/routes-and-actions.md)
- [Troubleshooting](docs/troubleshooting.md)

## Control Panel

The plugin adds a **Super Favourite** section with:

- **Collections** for creating global, user, and default collections.
- **Favourites** for managing saved items.
- **Settings** for plugin limits and field layouts.

## Permissions

The plugin registers permissions for viewing and managing favourites and collections:

- `super-favourite:view-favourites`
- `super-favourite:manage-favourites`
- `super-favourite:view-collections`
- `super-favourite:manage-collections`

## License

Proprietary - Copyright (c) 2024 Amici Infotech

