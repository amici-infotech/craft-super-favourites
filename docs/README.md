# Super Favourite Documentation

Super Favourite adds wishlist-style favourites to Craft CMS. Users can favourite any supported element type, organize favourites into collections, and store notes or custom field data.

This documentation is split by task so you can find the right example quickly.

## Start Here

- [Installation and Setup](installation.md) - install the plugin, enable it, and configure the basics.
- [Core Concepts](concepts.md) - understand favourites, global collections, user collections, default collections, allowed element types, and custom fields.
- [Backend Guide](backend.md) - Control Panel screens, settings, collection management, favourite management, and screenshot placeholders.
- [Frontend Forms](frontend-forms.md) - copyable Twig forms for creating, editing, deleting, and using collections and favourites.

## Developer Reference

- [Twig Usage](twig-usage.md) - template variables, element queries, listing favourites, and checking state.
- [PHP API](php-api.md) - service methods for modules, plugins, jobs, and custom controllers.
- [Routes and Actions](routes-and-actions.md) - action URLs, request fields, responses, and permissions.
- [Troubleshooting](troubleshooting.md) - common setup, validation, permission, and query issues.

## Quick Mental Model

- A `Collection` is a list/container. It can be global (`userId` is empty) or personal (`userId` belongs to a user).
- A `FavouriteItem` is one saved favourite. It links a user, collection, element ID, and element type.
- Collections can restrict which element types are allowed.
- Both collections and favourites can have Craft custom fields through the plugin settings screens.

