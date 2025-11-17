# Super Favourite Plugin for Craft CMS 5.x

A powerful wishlist/favourite plugin for Craft CMS that allows users to favourite any element type with collections support.

## Features

- **Universal Favouriting**: Favourite any Craft element type (Entries, Assets, Categories, Users, Tags, Commerce Products, etc.)
- **Collections/Lists**: Organize favourites into multiple collections
- **User-specific**: Each user has their own favourites and collections
- **Login Required**: Requires authentication to favourite items
- **Template Variables**: Easy-to-use template variables for querying favourites
- **CP Management**: Full Control Panel interface for managing collections
- **Flexible API**: Comprehensive PHP API for programmatic access

## Requirements

- Craft CMS 4.0+ or 5.0+
- PHP 8.0.2+

## Installation

1. Add the plugin to your project's composer repositories in `composer.json`:

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

2. Install via Composer:

```bash
composer require amici/craft-super-favourite
```

3. Install the plugin via Craft CLI:

```bash
php craft plugin/install super-favourite
```

Or install via the Craft Control Panel under Settings → Plugins.

## Database Architecture

### Collections Table (`super_favourite_collections`)
Stores user-created collections/lists for organizing favourites:
- `id` - Primary key, foreign key to elements
- `userId` - Owner of the collection
- `name` - Collection name
- `handle` - Unique handle for the collection
- `description` - Optional description
- `isDefault` - Whether this is the user's default collection
- `sortOrder` - For ordering collections

### Favourite Items Table (`super_favourite_items`)
Stores individual favourite items:
- `id` - Primary key, foreign key to elements
- `userId` - User who favourited
- `collectionId` - Which collection it belongs to
- `elementId` - The element being favourited
- `elementType` - Class name of the element type
- `sortOrder` - For custom ordering within collection
- `notes` - Optional user notes

## Template Usage

### Check if an element is favourited

```twig
{% if craft.superFavourite.isFavourited(entry.id) %}
    <span>Already favourited!</span>
{% endif %}
```

### Get all favourited element IDs

```twig
{% set favouriteIds = craft.superFavourite.getFavouritedElementIds() %}

{# Use in element query #}
{% set favouritedEntries = craft.entries()
    .id(favouriteIds)
    .all() %}
```

### Get favourites from a specific collection

```twig
{% set collection = craft.superFavourite.getCollectionByHandle('wishlist') %}
{% set favouriteIds = craft.superFavourite.getFavouritedElementIds(collection.id) %}
```

### Get all collections

```twig
{% set collections = craft.superFavourite.getCollections() %}

{% for collection in collections %}
    <h3>{{ collection.name }}</h3>
    <p>Items: {{ craft.superFavourite.getFavouriteCount(collection.id) }}</p>
{% endfor %}
```

### Favourite count

```twig
<span>Total Favourites: {{ craft.superFavourite.getFavouriteCount() }}</span>
```

## Frontend Forms

### Add to Favourites Form

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/add') }}
    {{ redirectInput('path/to/redirect') }}

    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="elementType" value="{{ className(entry) }}">
    {# Optional: specify collection #}
    <input type="hidden" name="collectionId" value="{{ collection.id }}">
    {# Optional: add notes #}
    <textarea name="notes">My notes about this item</textarea>

    <button type="submit">Add to Favourites</button>
</form>
```

### Remove from Favourites Form

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/remove') }}
    {{ redirectInput('path/to/redirect') }}

    <input type="hidden" name="elementId" value="{{ entry.id }}">
    {# Optional: specify collection to remove from specific collection only #}
    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <button type="submit">Remove from Favourites</button>
</form>
```

### Toggle Favourite (AJAX)

```javascript
// Using fetch API
async function toggleFavourite(elementId, elementType) {
    const formData = new FormData();
    formData.append('elementId', elementId);
    formData.append('elementType', elementType);
    formData.append(window.Craft.csrfTokenName, window.Craft.csrfTokenValue);

    const response = await fetch('/actions/super-favourite/favourite/toggle', {
        method: 'POST',
        body: formData
    });

    const result = await response.json();

    if (result.success) {
        console.log(result.action); // 'added' or 'removed'
        console.log(result.isFavourited); // true or false
    }
}
```

### Complete Toggle Button Example

```twig
{% set isFavourited = craft.superFavourite.isFavourited(entry.id) %}

<button
    class="favourite-toggle {{ isFavourited ? 'is-favourited' : '' }}"
    data-element-id="{{ entry.id }}"
    data-element-type="{{ className(entry) }}"
>
    <span class="icon">{{ isFavourited ? '★' : '☆' }}</span>
    <span class="text">{{ isFavourited ? 'Remove from Favourites' : 'Add to Favourites' }}</span>
</button>

<script>
document.querySelectorAll('.favourite-toggle').forEach(button => {
    button.addEventListener('click', async (e) => {
        e.preventDefault();

        const elementId = button.dataset.elementId;
        const elementType = button.dataset.elementType;

        const formData = new FormData();
        formData.append('elementId', elementId);
        formData.append('elementType', elementType);
        formData.append('{{ craft.app.config.general.csrfTokenName }}', '{{ craft.app.request.csrfToken }}');

        try {
            const response = await fetch('/actions/super-favourite/favourite/toggle', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                button.classList.toggle('is-favourited', result.isFavourited);
                button.querySelector('.icon').textContent = result.isFavourited ? '★' : '☆';
                button.querySelector('.text').textContent = result.isFavourited
                    ? 'Remove from Favourites'
                    : 'Add to Favourites';
            }
        } catch (error) {
            console.error('Error toggling favourite:', error);
        }
    });
});
</script>
```

## PHP API

### Add a favourite

```php
use amici\SuperFavourite\Plugin;

$favourite = Plugin::getInstance()->favourite->addFavourite(
    elementId: 123,
    elementType: \craft\elements\Entry::class,
    userId: Craft::$app->getUser()->getId(),
    collectionId: null, // null = default collection
    notes: 'Optional notes'
);
```

### Remove a favourite

```php
$success = Plugin::getInstance()->favourite->removeFavourite(
    elementId: 123,
    userId: Craft::$app->getUser()->getId(),
    collectionId: null // null = remove from all collections
);
```

### Check if favourited

```php
$isFavourited = Plugin::getInstance()->favourite->isFavourited(
    elementId: 123,
    userId: Craft::$app->getUser()->getId()
);
```

### Get favourited element IDs

```php
$elementIds = Plugin::getInstance()->favourite->getFavouritedElementIds(
    userId: Craft::$app->getUser()->getId(),
    collectionId: null, // null = all collections
    elementType: \craft\elements\Entry::class // null = all types
);
```

### Create a collection

```php
$collection = Plugin::getInstance()->collection->createCollection(
    userId: Craft::$app->getUser()->getId(),
    name: 'My Wishlist',
    handle: 'wishlist',
    description: 'Items I want to buy',
    isDefault: false
);
```

### Get user collections

```php
$collections = Plugin::getInstance()->collection->getUserCollections(
    userId: Craft::$app->getUser()->getId()
);
```

## Permissions

The plugin provides the following user permissions:
- **View favourites** - View favourite items
- **Manage favourites** - Add/remove favourites
- **View collections** - View collections
- **Manage collections** - Create/edit/delete collections

## Settings

Configure the plugin in the Control Panel under Super Favourite → Settings:

- **Plugin Name** - Customize the plugin name in the CP
- **Show Favourite Counts in Indexes** - Display counts in element indexes
- **Auto Create Default Collection** - Automatically create default collection for new users
- **Allow Multiple Collections** - Whether users can create multiple collections
- **Max Collections Per User** - Limit number of collections (0 = unlimited)
- **Max Favourites Per Collection** - Limit favourites per collection (0 = unlimited)

## Support

For issues, questions, or feature requests, please contact [contact@amiciinfotech.com](mailto:contact@amiciinfotech.com)

## License

Proprietary - Copyright © 2024 Amici Infotech

---

Brought to you by [Amici Infotech](https://amiciinfotech.com)

