# Super Favourite Plugin - Complete Documentation

> A powerful wishlist/favourite plugin for Craft CMS 5 that allows users to favourite any element type with collections support.

**Version:** 5.0.0
**Author:** Amici Infotech
**Craft CMS:** 5.0+

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Installation & Setup](#installation--setup)
4. [Backend Usage](#backend-usage)
5. [Frontend Usage](#frontend-usage)
6. [Element Queries](#element-queries)
7. [Services](#services)
8. [Events & Extensibility](#events--extensibility)
9. [Custom Fields](#custom-fields)
10. [Filtering & Conditions](#filtering--conditions)
11. [API Reference](#api-reference)
12. [Code Examples](#code-examples)
13. [Best Practices](#best-practices)

---

## Overview

Super Favourite is a comprehensive favouriting system for Craft CMS that enables users to:

- Favourite any element type (Entries, Assets, Categories, Users, Products, etc.)
- Organize favourites into collections (personal or global)
- Add notes to favourites
- Sort and reorder favourites
- Filter and search favourites
- Extend functionality through events

### Key Features

✅ **Multi-Element Support** - Favourite any registered element type
✅ **Collections System** - Organize favourites into collections
✅ **Personal & Global Collections** - User-specific or site-wide collections
✅ **Custom Fields** - Add custom fields to favourites and collections
✅ **Event Hooks** - Extend functionality with custom event handlers
✅ **Advanced Filtering** - Filter by user, collection, element type, and more
✅ **AJAX Support** - Built-in AJAX actions for seamless UX
✅ **Validation** - Comprehensive validation with field-specific error messages

---

## Architecture

### Elements

The plugin defines two custom element types:

#### 1. **FavouriteItem Element**
Represents a single favourited item.

**Properties:**
- `userId` - The user who created the favourite
- `collectionId` - The collection this favourite belongs to
- `elementId` - The ID of the favourited element
- `elementType` - The class name of the favourited element
- `notes` - Optional notes about the favourite
- `sortOrder` - Custom sort order within the collection

#### 2. **Collection Element**
Represents a collection of favourites.

**Properties:**
- `userId` - The owner (null for global collections)
- `name` - Collection name
- `handle` - Unique collection handle
- `description` - Optional description
- `isDefault` - Whether this is the default collection for a user
- `allowedElementTypes` - JSON array of allowed element types
- `sortOrder` - Display order

### Services

#### 1. **FavouriteService**
Handles all favourite-related operations.

**Key Methods:**
- `addFavourite()` - Add an element to favourites
- `removeFavourite()` - Remove a favourite
- `toggleFavourite()` - Add or remove based on current state
- `moveFavourite()` - Move between collections
- `checkDuplicate()` - Check for existing favourites
- `getElementsWithFavouriteStatus()` - Fetch elements with favourite status

#### 2. **CollectionService**
Manages collections.

**Key Methods:**
- `createCollection()` - Create a new collection
- `deleteCollection()` - Delete a collection
- `getOrCreateDefaultCollection()` - Get or create default collection for a user
- `getUserCollections()` - Get all collections for a user
- `getGlobalCollections()` - Get all global collections

---

## Installation & Setup

### 1. Install via Composer

```bash
composer require amici/craft-super-favourite
```

### 2. Install via Control Panel

1. Navigate to **Settings → Plugins**
2. Find "Super Favourite" in the list
3. Click **Install**

### 3. Configure Settings

Navigate to **Settings → Super Favourite** to configure:

- **Plugin Name** - Customize the plugin display name
- **Allowed Element Types** - Choose which element types can be favourited
- **Default Collection Settings** - Configure default collection behavior

### 4. Custom Fields (Optional)

Add custom fields to favourites or collections:

1. Go to **Settings → Super Favourite → Collection Fields** or **Favourite Fields**
2. Create a new field layout
3. Add your custom fields (Plain Text, Dropdown, etc.)

---

## Backend Usage

### Control Panel

#### Favourites Index

Access at: `https://your-site.test/admin/super-favourite/favourites`

**Features:**
- View all favourites across users
- Filter by user, collection, or element type
- Search favourites
- Bulk actions (delete, restore)
- Element chips showing related elements

#### Collections Index

Access at: `https://your-site.test/admin/super-favourite/collections`

**Features:**
- Manage collections
- Create personal or global collections
- Configure allowed element types
- View collection statistics

### Creating Collections Programmatically

```php
use amici\SuperFavourite\Plugin;

// Create a personal collection
$collection = Plugin::getInstance()->collection->createCollection(
    userId: 1,
    name: 'My Wishlist',
    handle: 'my-wishlist',
    description: 'Products I want to buy',
    isDefault: false
);

// Create a global collection
$collection = Plugin::getInstance()->collection->createCollection(
    userId: null, // null = global
    name: 'Staff Picks',
    handle: 'staff-picks',
    description: 'Products recommended by our team',
    isDefault: false
);
```

### Adding Favourites Programmatically

```php
use amici\SuperFavourite\Plugin;

// Add an entry to favourites
$favourite = Plugin::getInstance()->favourite->addFavourite(
    elementId: 123,
    elementType: \craft\elements\Entry::class,
    userId: 1, // optional, defaults to current user
    collectionId: 5, // optional, defaults to default collection
    notes: 'I love this article!' // optional
);

// Check if it was saved successfully
if ($favourite) {
    echo "Added to favourites!";
} else {
    echo "Failed to add favourite.";
}
```

---

## Frontend Usage

### Template Variables

Access favourites in templates via the `craft.superFavourite` variable:

```twig
{# Get all favourites #}
{% set favourites = craft.superFavourite.favourites().all() %}

{# Get all collections #}
{% set collections = craft.superFavourite.collections().all() %}

{# Get available element types #}
{% set elementTypes = craft.superFavourite.getAvailableElementTypes() %}
```

### Displaying Favourites

```twig
{# Get current user's favourites #}
{% set currentUser = currentUser ?? craft.app.user.identity %}
{% set favourites = craft.superFavourite.favourites()
    .author(currentUser)
    .all() %}

<h2>My Favourites ({{ favourites|length }})</h2>

<div class="favourites-grid">
    {% for favourite in favourites %}
        {% set element = craft.entries.id(favourite.elementId).one() %}

        <div class="favourite-card">
            <h3>{{ element.title }}</h3>

            {% if favourite.notes %}
                <p class="notes">{{ favourite.notes }}</p>
            {% endif %}

            <div class="meta">
                <span>Collection: {{ favourite.getCollection().name }}</span>
                <span>Added: {{ favourite.dateCreated|date('M d, Y') }}</span>
            </div>

            <button class="remove-favourite" data-id="{{ favourite.id }}">
                Remove
            </button>
        </div>
    {% endfor %}
</div>
```

### Displaying Collections

```twig
{# Get user's collections #}
{% set collections = craft.superFavourite.collections()
    .userId(currentUser.id)
    .all() %}

<h2>My Collections</h2>

<ul class="collections-list">
    {% for collection in collections %}
        <li>
            <a href="/favourites/collection/{{ collection.id }}">
                {{ collection.name }}
                <span class="count">
                    ({{ craft.superFavourite.favourites()
                        .collectionId(collection.id)
                        .count() }})
                </span>
            </a>

            {% if collection.description %}
                <p>{{ collection.description }}</p>
            {% endif %}
        </li>
    {% endfor %}
</ul>
```

### AJAX Actions

#### Add to Favourites

```javascript
// Add to favourites
$.post('/actions/super-favourite/favourite/add', {
    [csrfTokenName]: csrfTokenValue,
    elementId: 123,
    elementType: 'craft\\elements\\Entry',
    collectionId: 5, // optional
    notes: 'Great article!' // optional
})
.done(function(response) {
    if (response.success) {
        console.log('Added!', response.favourite);
    } else {
        console.log('Error:', response.message);
        console.log('Validation errors:', response.errors);
    }
});
```

#### Remove from Favourites

```javascript
// Remove by favourite ID
$.post('/actions/super-favourite/favourite/remove', {
    [csrfTokenName]: csrfTokenValue,
    id: 456 // favourite item ID
})
.done(function(response) {
    if (response.success) {
        console.log('Removed!');
    }
});
```

#### Toggle Favourite

```javascript
// Toggle (add if not exists, remove if exists)
$.post('/actions/super-favourite/favourite/toggle', {
    [csrfTokenName]: csrfTokenValue,
    elementId: 123,
    elementType: 'craft\\elements\\Entry',
    collectionId: 5
})
.done(function(response) {
    if (response.success) {
        if (response.action === 'added') {
            console.log('Added to favourites!');
        } else if (response.action === 'removed') {
            console.log('Removed from favourites!');
        }
    }
});
```

#### Check Favourite Status

```javascript
// Check if element is favourited
$.get('/actions/super-favourite/favourite/check', {
    elementId: 123,
    collectionId: 5 // optional
})
.done(function(response) {
    if (response.isFavourited) {
        console.log('Is favourited! ID:', response.favouriteId);
    } else {
        console.log('Not favourited');
    }
});
```

### Complete Toggle Button Example

```twig
{# In your template #}
{% set element = entry %} {# or asset, category, etc. #}
{% set currentUser = currentUser ?? craft.app.user.identity %}

{% if currentUser %}
    {% set isFavourited = craft.superFavourite.favourites()
        .author(currentUser)
        .favouritedElement(element)
        .exists() %}

    <button
        class="favourite-toggle"
        data-element-id="{{ element.id }}"
        data-element-type="{{ className(element) }}"
        data-favourited="{{ isFavourited ? 'true' : 'false' }}">

        {% if isFavourited %}
            <i class="icon-heart-filled"></i> Favourited
        {% else %}
            <i class="icon-heart-outline"></i> Add to Favourites
        {% endif %}
    </button>
{% endif %}
```

```javascript
// JavaScript for toggle button
$(document).on('click', '.favourite-toggle', function() {
    var $btn = $(this);
    var elementId = $btn.data('element-id');
    var elementType = $btn.data('element-type');
    var isFavourited = $btn.data('favourited') === 'true';

    $btn.prop('disabled', true);

    $.post('/actions/super-favourite/favourite/toggle', {
        [csrfTokenName]: csrfTokenValue,
        elementId: elementId,
        elementType: elementType
    })
    .done(function(response) {
        if (response.success) {
            // Update button state
            $btn.data('favourited', response.action === 'added');

            if (response.action === 'added') {
                $btn.html('<i class="icon-heart-filled"></i> Favourited');
            } else {
                $btn.html('<i class="icon-heart-outline"></i> Add to Favourites');
            }

            // Show notification
            showNotification(response.message, 'success');
        }
    })
    .fail(function(xhr) {
        showNotification('Error: ' + xhr.responseJSON.message, 'error');
    })
    .always(function() {
        $btn.prop('disabled', false);
    });
});
```

---

## Element Queries

### FavouriteItem Queries

#### Basic Queries

```twig
{# Get all favourites #}
{% set favourites = craft.superFavourite.favourites().all() %}

{# Get one favourite #}
{% set favourite = craft.superFavourite.favourites().one() %}

{# Count favourites #}
{% set count = craft.superFavourite.favourites().count() %}

{# Check if any exist #}
{% set exists = craft.superFavourite.favourites().exists() %}
```

#### Filtering by User (Author)

```twig
{# Current user's favourites #}
{% set favourites = craft.superFavourite.favourites()
    .author(currentUser)
    .all() %}

{# Specific user's favourites #}
{% set user = craft.users.id(5).one() %}
{% set favourites = craft.superFavourite.favourites()
    .author(user)
    .all() %}

{# Multiple users #}
{% set favourites = craft.superFavourite.favourites()
    .author([user1, user2])
    .all() %}

{# Using user ID directly #}
{% set favourites = craft.superFavourite.favourites()
    .userId(5)
    .all() %}
```

#### Filtering by Collection

```twig
{# Favourites in a specific collection #}
{% set collection = craft.superFavourite.collections().id(10).one() %}
{% set favourites = craft.superFavourite.favourites()
    .collectionId(collection.id)
    .all() %}

{# Multiple collections #}
{% set favourites = craft.superFavourite.favourites()
    .collectionId([10, 11, 12])
    .all() %}
```

#### Filtering by Favourited Element

```twig
{# Get all favourites of a specific entry #}
{% set entry = craft.entries.slug('my-post').one() %}
{% set favourites = craft.superFavourite.favourites()
    .favouritedElement(entry)
    .all() %}

{# Using element ID directly #}
{% set favourites = craft.superFavourite.favourites()
    .elementId(123)
    .all() %}

{# Multiple elements #}
{% set favourites = craft.superFavourite.favourites()
    .favouritedElement([entry1, entry2, asset])
    .all() %}
```

#### Filtering by Element Type

```twig
{# Get all favourited entries #}
{% set favourites = craft.superFavourite.favourites()
    .favouritedElementType('craft\\elements\\Entry')
    .all() %}

{# Get all favourited assets #}
{% set favourites = craft.superFavourite.favourites()
    .favouritedElementType('craft\\elements\\Asset')
    .all() %}
```

#### Combined Filters

```twig
{# Current user's favourited entries in a specific collection #}
{% set favourites = craft.superFavourite.favourites()
    .author(currentUser)
    .collectionId(5)
    .favouritedElementType('craft\\elements\\Entry')
    .all() %}
```

#### Sorting

```twig
{# Sort by custom sort order #}
{% set favourites = craft.superFavourite.favourites()
    .orderBy('sortOrder ASC')
    .all() %}

{# Sort by date added (newest first) #}
{% set favourites = craft.superFavourite.favourites()
    .orderBy('dateCreated DESC')
    .all() %}

{# Sort by title of favourited element #}
{% set favourites = craft.superFavourite.favourites()
    .orderBy('title ASC')
    .all() %}
```

#### Pagination

```twig
{% paginate craft.superFavourite.favourites()
    .author(currentUser)
    .limit(20)
    as pageInfo, favourites %}

{% for favourite in favourites %}
    {# Display favourite #}
{% endfor %}

{# Pagination links #}
{% if pageInfo.totalPages > 1 %}
    <nav class="pagination">
        {% if pageInfo.prevUrl %}
            <a href="{{ pageInfo.prevUrl }}">Previous</a>
        {% endif %}

        <span>Page {{ pageInfo.currentPage }} of {{ pageInfo.totalPages }}</span>

        {% if pageInfo.nextUrl %}
            <a href="{{ pageInfo.nextUrl }}">Next</a>
        {% endif %}
    </nav>
{% endif %}
```

### Collection Queries

#### Basic Queries

```twig
{# Get all collections #}
{% set collections = craft.superFavourite.collections().all() %}

{# Get specific collection #}
{% set collection = craft.superFavourite.collections()
    .id(10)
    .one() %}

{# Get collection by handle #}
{% set collection = craft.superFavourite.collections()
    .handle('my-wishlist')
    .one() %}
```

#### User Collections

```twig
{# Current user's collections #}
{% set collections = craft.superFavourite.collections()
    .userId(currentUser.id)
    .all() %}

{# Global collections (no owner) #}
{% set collections = craft.superFavourite.collections()
    .userId(null)
    .all() %}

{# Both user and global collections #}
{% set collections = craft.superFavourite.collections()
    .userId([currentUser.id, null])
    .all() %}
```

#### Default Collection

```twig
{# Get user's default collection #}
{% set defaultCollection = craft.superFavourite.collections()
    .userId(currentUser.id)
    .isDefault(true)
    .one() %}
```

---

## Services

### FavouriteService

Access via: `Plugin::getInstance()->favourite`

#### addFavourite()

Add an element to favourites.

```php
use amici\SuperFavourite\Plugin;

$favourite = Plugin::getInstance()->favourite->addFavourite(
    elementId: 123,
    elementType: 'craft\elements\Entry',
    userId: 1, // optional, defaults to current user
    collectionId: 5, // optional, defaults to default collection
    notes: 'Great article!' // optional
);

if ($favourite) {
    // Success - returns FavouriteItem element
    echo "Added to favourites! ID: " . $favourite->id;
} else {
    // Failed - returns false
    echo "Failed to add favourite";
}
```

#### removeFavourite()

Remove a favourite by ID.

```php
$success = Plugin::getInstance()->favourite->removeFavourite(456);

if ($success) {
    echo "Removed from favourites!";
}
```

#### toggleFavourite()

Add or remove based on current state.

```php
$result = Plugin::getInstance()->favourite->toggleFavourite(
    elementId: 123,
    elementType: 'craft\elements\Entry',
    collectionId: 5,
    userId: 1 // optional
);

if ($result['success']) {
    if ($result['action'] === 'added') {
        echo "Added to favourites!";
    } else {
        echo "Removed from favourites!";
    }
}
```

#### checkDuplicate()

Check if a favourite already exists.

```php
$existing = Plugin::getInstance()->favourite->checkDuplicate(
    userId: 1,
    collectionId: 5,
    elementId: 123
);

if ($existing) {
    echo "Already favourited! ID: " . $existing['id'];
} else {
    echo "Not favourited yet";
}
```

#### moveFavourite()

Move a favourite to a different collection.

```php
$success = Plugin::getInstance()->favourite->moveFavourite(
    favouriteId: 456,
    newCollectionId: 7
);

if ($success) {
    echo "Moved to new collection!";
}
```

#### getElementsWithFavouriteStatus()

Fetch elements with their favourite status for a user.

```php
$elements = Plugin::getInstance()->favourite->getElementsWithFavouriteStatus(
    elementType: 'craft\elements\Entry',
    collectionId: 5,
    userId: 1, // optional, defaults to current user
    limit: 10 // optional, default 10
);

foreach ($elements as $element) {
    echo $element['title'] . ' - ';
    echo $element['isFavourited'] ? 'Favourited' : 'Not favourited';
    echo "\n";
}
```

### CollectionService

Access via: `Plugin::getInstance()->collection`

#### createCollection()

Create a new collection.

```php
use amici\SuperFavourite\Plugin;

$collection = Plugin::getInstance()->collection->createCollection(
    userId: 1, // null for global collection
    name: 'My Wishlist',
    handle: 'my-wishlist', // optional, auto-generated if not provided
    description: 'Products I want to buy', // optional
    isDefault: false // optional, default false
);

if ($collection) {
    echo "Collection created! ID: " . $collection->id;
}
```

#### deleteCollection()

Delete a collection and optionally its favourites.

```php
$success = Plugin::getInstance()->collection->deleteCollection(
    collectionId: 10,
    deleteFavourites: true // optional, default false
);

if ($success) {
    echo "Collection deleted!";
}
```

#### getOrCreateDefaultCollection()

Get or create a default collection for a user.

```php
$collection = Plugin::getInstance()->collection->getOrCreateDefaultCollection(1);

if ($collection) {
    echo "Default collection ID: " . $collection->id;
}
```

#### getUserCollections()

Get all collections for a user.

```php
$collections = Plugin::getInstance()->collection->getUserCollections(
    userId: 1,
    includeGlobal: true // optional, default true
);

foreach ($collections as $collection) {
    echo $collection->name . "\n";
}
```

#### getGlobalCollections()

Get all global collections.

```php
$collections = Plugin::getInstance()->collection->getGlobalCollections();

foreach ($collections as $collection) {
    echo $collection->name . "\n";
}
```

---

## Events & Extensibility

The plugin provides numerous events for extending functionality.

### FavouriteService Events

#### EVENT_BEFORE_ADD_FAVOURITE

Triggered before adding a favourite. You can prevent the save by setting `$event->isValid = false`.

```php
use amici\SuperFavourite\Plugin;
use craft\events\ModelEvent;
use yii\base\Event;

Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_BEFORE_ADD_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Prevent favouriting draft entries
        $element = Craft::$app->elements->getElementById($favourite->elementId);
        if ($element instanceof \craft\elements\Entry && $element->getIsDraft()) {
            $event->isValid = false;
            Craft::$app->session->setError('Cannot favourite draft entries');
        }

        // Example: Log the action
        Craft::info(
            "User {$favourite->userId} is adding element {$favourite->elementId} to favourites",
            'super-favourite'
        );
    }
);
```

#### EVENT_AFTER_ADD_FAVOURITE

Triggered after successfully adding a favourite.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_AFTER_ADD_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Send notification email
        $user = $favourite->getUser();
        $element = $favourite->getFavouritedElement();

        Craft::$app->mailer->compose()
            ->setTo($user->email)
            ->setSubject('New Favourite Added')
            ->setTextBody("You added {$element->title} to your favourites!")
            ->send();

        // Example: Track analytics
        // Analytics::track('favourite_added', [
        //     'user_id' => $favourite->userId,
        //     'element_type' => $favourite->elementType,
        //     'element_id' => $favourite->elementId,
        // ]);
    }
);
```

#### EVENT_BEFORE_REMOVE_FAVOURITE

Triggered before removing a favourite.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_BEFORE_REMOVE_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Archive instead of delete
        if ($favourite->userId === 1) { // Admin user
            $favourite->archived = true;
            Craft::$app->elements->saveElement($favourite);
            $event->isValid = false; // Prevent actual deletion
        }
    }
);
```

#### EVENT_AFTER_REMOVE_FAVOURITE

Triggered after successfully removing a favourite.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_AFTER_REMOVE_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Update user statistics
        $userStats = UserStats::findOne(['userId' => $favourite->userId]);
        if ($userStats) {
            $userStats->totalFavourites--;
            $userStats->save();
        }
    }
);
```

#### EVENT_BEFORE_MOVE_FAVOURITE

Triggered before moving a favourite to a different collection.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_BEFORE_MOVE_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Prevent moving to archived collections
        $newCollection = $favourite->getCollection();
        if ($newCollection->archived ?? false) {
            $event->isValid = false;
            Craft::$app->session->setError('Cannot move to archived collection');
        }
    }
);
```

#### EVENT_AFTER_MOVE_FAVOURITE

Triggered after successfully moving a favourite.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_AFTER_MOVE_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        // Example: Log collection changes
        Craft::info(
            "Favourite {$favourite->id} moved to collection {$favourite->collectionId}",
            'super-favourite'
        );
    }
);
```

### CollectionService Events

#### EVENT_BEFORE_CREATE_COLLECTION

Triggered before creating a collection.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->collection::EVENT_BEFORE_CREATE_COLLECTION,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\Collection $collection */
        $collection = $event->sender;

        // Example: Enforce collection limit per user
        $userCollectionCount = \amici\SuperFavourite\elements\Collection::find()
            ->userId($collection->userId)
            ->count();

        if ($userCollectionCount >= 5) {
            $event->isValid = false;
            Craft::$app->session->setError('Maximum 5 collections per user');
        }
    }
);
```

#### EVENT_AFTER_CREATE_COLLECTION

Triggered after successfully creating a collection.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->collection::EVENT_AFTER_CREATE_COLLECTION,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\Collection $collection */
        $collection = $event->sender;

        // Example: Auto-populate with recommended items
        if ($collection->handle === 'wishlist') {
            $recommendedProducts = \craft\elements\Entry::find()
                ->section('products')
                ->featured(true)
                ->limit(5)
                ->all();

            foreach ($recommendedProducts as $product) {
                Plugin::getInstance()->favourite->addFavourite(
                    elementId: $product->id,
                    elementType: get_class($product),
                    userId: $collection->userId,
                    collectionId: $collection->id,
                    notes: 'Recommended for you'
                );
            }
        }
    }
);
```

#### EVENT_BEFORE_DELETE_COLLECTION

Triggered before deleting a collection.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->collection::EVENT_BEFORE_DELETE_COLLECTION,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\Collection $collection */
        $collection = $event->sender;

        // Example: Prevent deleting default collections
        if ($collection->isDefault) {
            $event->isValid = false;
            Craft::$app->session->setError('Cannot delete default collection');
        }
    }
);
```

#### EVENT_AFTER_DELETE_COLLECTION

Triggered after successfully deleting a collection.

```php
Event::on(
    Plugin::class,
    Plugin::getInstance()->collection::EVENT_AFTER_DELETE_COLLECTION,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\Collection $collection */
        $collection = $event->sender;

        // Example: Clean up related data
        // CustomMetadata::deleteAll(['collectionId' => $collection->id]);

        Craft::info(
            "Collection {$collection->id} deleted",
            'super-favourite'
        );
    }
);
```

### Complete Event Example: Email Notifications

```php
// In your plugin's init() or a module

use amici\SuperFavourite\Plugin as SuperFavourite;
use craft\events\ModelEvent;
use craft\mail\Message;
use yii\base\Event;

// Send email when someone adds an item to favourites
Event::on(
    SuperFavourite::class,
    SuperFavourite::getInstance()->favourite::EVENT_AFTER_ADD_FAVOURITE,
    function(ModelEvent $event) {
        /** @var \amici\SuperFavourite\elements\FavouriteItem $favourite */
        $favourite = $event->sender;

        $user = $favourite->getUser();
        $element = $favourite->getFavouritedElement();
        $collection = $favourite->getCollection();

        if (!$user || !$element) {
            return;
        }

        // Send email
        try {
            $message = new Message();
            $message->setTo($user->email);
            $message->setSubject('New Favourite Added');
            $message->setHtmlBody(
                Craft::$app->view->renderTemplate('_emails/favourite-added', [
                    'user' => $user,
                    'element' => $element,
                    'collection' => $collection,
                    'favourite' => $favourite,
                ])
            );

            Craft::$app->mailer->send($message);
        } catch (\Exception $e) {
            Craft::error(
                'Failed to send favourite notification email: ' . $e->getMessage(),
                'super-favourite'
            );
        }
    }
);
```

---

## Custom Fields

You can add custom fields to both FavouriteItem and Collection elements.

### Adding Custom Fields

1. Navigate to **Settings → Super Favourite**
2. Choose **Collection Fields** or **Favourite Fields**
3. Click **New Field** or select existing fields
4. Configure field layout

### Supported Field Types

All Craft field types are supported:
- Plain Text
- Dropdown
- Checkboxes
- Radio Buttons
- Lightswitch
- Date/Time
- Number
- Color
- And more...

### Accessing Custom Fields in Templates

```twig
{% set favourites = craft.superFavourite.favourites().all() %}

{% for favourite in favourites %}
    <div class="favourite">
        <h3>{{ favourite.title }}</h3>

        {# Access custom fields #}
        {% if favourite.myCustomField %}
            <p>{{ favourite.myCustomField }}</p>
        {% endif %}

        {% if favourite.priorityLevel %}
            <span class="priority">Priority: {{ favourite.priorityLevel }}</span>
        {% endif %}

        {% if favourite.reminderDate %}
            <time>Remind me: {{ favourite.reminderDate|date('F j, Y') }}</time>
        {% endif %}
    </div>
{% endfor %}
```

### Custom Fields on Collections

```twig
{% set collections = craft.superFavourite.collections().all() %}

{% for collection in collections %}
    <div class="collection">
        <h3>{{ collection.name }}</h3>

        {# Access custom fields #}
        {% if collection.icon %}
            <i class="{{ collection.icon }}"></i>
        {% endif %}

        {% if collection.color %}
            <span style="color: {{ collection.color }}">●</span>
        {% endif %}

        {% if collection.isPublic %}
            <span class="badge">Public</span>
        {% endif %}
    </div>
{% endfor %}
```

---

## Filtering & Conditions

### Control Panel Filters

The plugin provides custom condition rules for filtering in the CP:

#### Available Filters

1. **User** - Filter favourites by the user who created them
2. **Favourite Collection** - Filter by collection
3. **Favourite Element** - Filter by element type (Entry, Asset, etc.)
4. **Related To** - Use Craft's native relation filtering

### Using Filters

1. Go to `/admin/super-favourite/favourites`
2. Click **Filters** button
3. Click **Add a filter**
4. Select your filter type
5. Configure and apply

### Custom Condition Rules

You can create your own condition rules:

```php
// Example: Create a "Priority" condition rule

namespace myplugin\conditions\rules;

use Craft;
use craft\base\conditions\BaseSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;

class PriorityConditionRule extends BaseSelectConditionRule implements ElementConditionRuleInterface
{
    public function getLabel(): string
    {
        return Craft::t('app', 'Priority');
    }

    public function getExclusiveQueryParams(): array
    {
        return ['priority'];
    }

    protected function options(): array
    {
        return [
            ['label' => 'High', 'value' => 'high'],
            ['label' => 'Medium', 'value' => 'medium'],
            ['label' => 'Low', 'value' => 'low'],
        ];
    }

    public function modifyQuery(ElementQueryInterface $query): void
    {
        $query->priority($this->value);
    }

    public function matchElement(ElementInterface $element): bool
    {
        return $this->matchValue($element->priority);
    }
}
```

Then register it in your FavouriteItemCondition:

```php
protected function selectableConditionRules(): array
{
    return array_merge(parent::selectableConditionRules(), [
        PriorityConditionRule::class,
    ]);
}
```

---

## API Reference

### Controller Actions

All actions support both AJAX (JSON response) and standard form submission (redirect with flash message).

#### FavouriteController Actions

| Action | Method | Parameters | Response |
|--------|--------|------------|----------|
| `/actions/super-favourite/favourite/add` | POST | `elementId`, `elementType`, `collectionId?`, `notes?` | FavouriteItem or error |
| `/actions/super-favourite/favourite/remove` | POST | `id` | Success/error message |
| `/actions/super-favourite/favourite/toggle` | POST | `elementId`, `elementType`, `collectionId?` | Action (added/removed) + data |
| `/actions/super-favourite/favourite/check` | GET | `elementId`, `collectionId?` | `isFavourited`, `favouriteId?` |
| `/actions/super-favourite/favourite/move` | POST | `id`, `collectionId` | Success/error message |
| `/actions/super-favourite/favourite/save` | POST | Form fields | FavouriteItem or validation errors |
| `/actions/super-favourite/favourite/delete` | POST | `id` | Success/error message |

#### CollectionController Actions

| Action | Method | Parameters | Response |
|--------|--------|------------|----------|
| `/actions/super-favourite/collection/save` | POST | Form fields | Collection or validation errors |
| `/actions/super-favourite/collection/delete` | POST | `id`, `deleteFavourites?` | Success/error message |

### Template Variables

#### craft.superFavourite.favourites()

Returns a FavouriteItemQuery.

**Methods:**
- `author($value)` - Filter by user who created the favourite
- `userId($value)` - Filter by user ID
- `collectionId($value)` - Filter by collection ID
- `favouritedElement($value)` - Filter by favourited element
- `elementId($value)` - Filter by element ID
- `favouritedElementType($value)` - Filter by element type class

#### craft.superFavourite.collections()

Returns a CollectionQuery.

**Methods:**
- `userId($value)` - Filter by owner user ID (null for global)
- `handle($value)` - Filter by collection handle
- `name($value)` - Filter by collection name
- `isDefault($value)` - Filter by default status

#### craft.superFavourite.getAvailableElementTypes()

Returns array of available element types.

**Returns:**
```php
[
    ['value' => 'craft\elements\Entry', 'label' => 'Entries'],
    ['value' => 'craft\elements\Asset', 'label' => 'Assets'],
    // ...
]
```

### Response Formats

#### Success Response (AJAX)

```json
{
    "success": true,
    "message": "Item added to favourites.",
    "favourite": {
        "id": 123,
        "userId": 1,
        "collectionId": 5,
        "elementId": 456,
        "elementType": "craft\\elements\\Entry",
        "notes": "Great article!",
        "sortOrder": 0,
        "dateCreated": "2024-11-19T10:30:00+00:00"
    }
}
```

#### Error Response (AJAX)

```json
{
    "success": false,
    "message": "Couldn't save favourite.",
    "errors": {
        "elementId": ["Element ID cannot be blank."],
        "elementType": ["Element Type cannot be blank."]
    }
}
```

#### Toggle Response

```json
{
    "success": true,
    "action": "added", // or "removed"
    "favouriteId": 123, // or null if removed
    "elementId": 456,
    "elementType": "craft\\elements\\Entry",
    "collectionId": 5,
    "message": "Item added to favourites."
}
```

---

## Code Examples

### Example 1: Product Wishlist

```twig
{# templates/shop/product.twig #}
{% extends "_layout" %}

{% block content %}
    {% set product = entry %}
    {% set currentUser = currentUser ?? craft.app.user.identity %}

    <article class="product">
        <h1>{{ product.title }}</h1>

        {% if currentUser %}
            {% set isFavourited = craft.superFavourite.favourites()
                .author(currentUser)
                .favouritedElement(product)
                .exists() %}

            <button
                class="wishlist-btn {{ isFavourited ? 'active' : '' }}"
                data-product-id="{{ product.id }}"
                data-favourited="{{ isFavourited ? '1' : '0' }}">
                <i class="icon-heart"></i>
                {{ isFavourited ? 'Remove from Wishlist' : 'Add to Wishlist' }}
            </button>
        {% else %}
            <a href="/login?redirect={{ craft.app.request.url|url_encode }}" class="wishlist-btn">
                <i class="icon-heart"></i>
                Login to Add to Wishlist
            </a>
        {% endif %}

        {# Rest of product details #}
        {{ product.description }}
        <div class="price">{{ product.price|currency }}</div>
    </article>
{% endblock %}

{% block scripts %}
    <script>
        $('.wishlist-btn').on('click', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var productId = $btn.data('product-id');
            var isFavourited = $btn.data('favourited') == '1';

            $.post('/actions/super-favourite/favourite/toggle', {
                '{{ craft.app.config.general.csrfTokenName }}': '{{ craft.app.request.csrfToken }}',
                elementId: productId,
                elementType: 'craft\\elements\\Entry',
                collectionId: null // Use default collection
            })
            .done(function(response) {
                if (response.success) {
                    $btn.toggleClass('active');
                    $btn.data('favourited', response.action === 'added' ? '1' : '0');
                    $btn.find('span').text(
                        response.action === 'added'
                            ? 'Remove from Wishlist'
                            : 'Add to Wishlist'
                    );

                    // Show toast notification
                    showToast(response.message, 'success');
                }
            });
        });
    </script>
{% endblock %}
```

### Example 2: User Wishlist Page

```twig
{# templates/account/wishlist.twig #}
{% requireLogin %}

{% set currentUser = currentUser %}
{% set collections = craft.superFavourite.collections()
    .userId([currentUser.id, null])
    .orderBy('sortOrder ASC')
    .all() %}

{% set selectedCollectionId = craft.app.request.getParam('collection') %}

<div class="wishlist-page">
    <h1>My Wishlist</h1>

    {# Collection tabs #}
    <nav class="collection-tabs">
        <a href="/account/wishlist" class="{{ not selectedCollectionId ? 'active' }}">
            All Items
        </a>
        {% for collection in collections %}
            <a href="/account/wishlist?collection={{ collection.id }}"
               class="{{ selectedCollectionId == collection.id ? 'active' }}">
                {{ collection.name }}
                ({{ craft.superFavourite.favourites()
                    .author(currentUser)
                    .collectionId(collection.id)
                    .count() }})
            </a>
        {% endfor %}
    </nav>

    {# Favourites grid #}
    {% set query = craft.superFavourite.favourites().author(currentUser) %}
    {% if selectedCollectionId %}
        {% set query = query.collectionId(selectedCollectionId) %}
    {% endif %}

    {% paginate query.orderBy('dateCreated DESC').limit(12) as pageInfo, favourites %}

    {% if favourites|length %}
        <div class="products-grid">
            {% for favourite in favourites %}
                {% set product = craft.entries.id(favourite.elementId).one() %}

                {% if product %}
                    <article class="product-card">
                        {% if product.productImage|length %}
                            <img src="{{ product.productImage.one().getUrl('productCard') }}"
                                 alt="{{ product.title }}">
                        {% endif %}

                        <h3>
                            <a href="{{ product.url }}">{{ product.title }}</a>
                        </h3>

                        <div class="price">{{ product.price|currency }}</div>

                        {% if favourite.notes %}
                            <div class="notes">{{ favourite.notes }}</div>
                        {% endif %}

                        <div class="actions">
                            <button class="btn-add-to-cart" data-product-id="{{ product.id }}">
                                Add to Cart
                            </button>

                            <button class="btn-remove" data-favourite-id="{{ favourite.id }}">
                                <i class="icon-trash"></i>
                            </button>
                        </div>
                    </article>
                {% endif %}
            {% endfor %}
        </div>

        {# Pagination #}
        {% if pageInfo.totalPages > 1 %}
            {% include "_includes/pagination" with { pageInfo: pageInfo } %}
        {% endif %}
    {% else %}
        <div class="empty-state">
            <i class="icon-heart-outline"></i>
            <h2>Your wishlist is empty</h2>
            <p>Start adding products you love!</p>
            <a href="/shop" class="btn btn-primary">Browse Products</a>
        </div>
    {% endif %}
</div>

{% js %}
    // Remove from wishlist
    $('.btn-remove').on('click', function() {
        if (!confirm('Remove from wishlist?')) return;

        var favouriteId = $(this).data('favourite-id');
        var $card = $(this).closest('.product-card');

        $.post('/actions/super-favourite/favourite/remove', {
            '{{ craft.app.config.general.csrfTokenName }}': '{{ craft.app.request.csrfToken }}',
            id: favouriteId
        })
        .done(function(response) {
            if (response.success) {
                $card.fadeOut(300, function() {
                    $(this).remove();
                });
                showToast('Removed from wishlist', 'success');
            }
        });
    });
{% endjs %}
```

### Example 3: Public Collection Sharing

```twig
{# templates/collections/view.twig #}
{% set collection = craft.superFavourite.collections()
    .id(craft.app.request.getSegment(2))
    .one() %}

{% if not collection %}
    {% exit 404 %}
{% endif %}

{# Check if collection is public or owned by current user #}
{% set canView = collection.isPublic or (currentUser and collection.userId == currentUser.id) %}

{% if not canView %}
    {% requireLogin %}
{% endif %}

<div class="collection-view">
    <header class="collection-header">
        <h1>{{ collection.name }}</h1>

        {% if collection.description %}
            <p class="description">{{ collection.description }}</p>
        {% endif %}

        <div class="meta">
            {% if collection.userId %}
                {% set owner = craft.users.id(collection.userId).one() %}
                <span>By {{ owner.fullName }}</span>
            {% else %}
                <span>Global Collection</span>
            {% endif %}

            <span>
                {{ craft.superFavourite.favourites()
                    .collectionId(collection.id)
                    .count() }} items
            </span>
        </div>
    </header>

    {% set favourites = craft.superFavourite.favourites()
        .collectionId(collection.id)
        .orderBy('sortOrder ASC')
        .all() %}

    <div class="favourites-grid">
        {% for favourite in favourites %}
            {% set element = create(favourite.elementType)
                .id(favourite.elementId)
                .one() %}

            {% if element %}
                <div class="favourite-card">
                    {# Render different element types #}
                    {% switch favourite.elementType %}
                        {% case 'craft\\elements\\Entry' %}
                            {% include "_partials/entry-card" with { entry: element } %}

                        {% case 'craft\\elements\\Asset' %}
                            {% include "_partials/asset-card" with { asset: element } %}

                        {% default %}
                            <h3>{{ element.title }}</h3>
                    {% endswitch %}

                    {% if favourite.notes %}
                        <div class="notes">{{ favourite.notes }}</div>
                    {% endif %}
                </div>
            {% endif %}
        {% endfor %}
    </div>
</div>
```

### Example 4: Admin Dashboard Widget

```php
// In your plugin/module

use craft\base\Widget;

class FavouritesWidget extends Widget
{
    public static function displayName(): string
    {
        return 'Popular Favourites';
    }

    public function getBodyHtml(): ?string
    {
        // Get most favourited items
        $topFavourites = \amici\SuperFavourite\elements\FavouriteItem::find()
            ->select(['elementId', 'elementType', 'COUNT(*) as count'])
            ->groupBy(['elementId', 'elementType'])
            ->orderBy('count DESC')
            ->limit(10)
            ->asArray()
            ->all();

        $items = [];
        foreach ($topFavourites as $fav) {
            $element = Craft::$app->elements->getElementById($fav['elementId']);
            if ($element) {
                $items[] = [
                    'element' => $element,
                    'count' => $fav['count'],
                ];
            }
        }

        return Craft::$app->view->renderTemplate('_widgets/favourites', [
            'items' => $items,
        ]);
    }
}
```

---

## Best Practices

### 1. Use Collections Wisely

```twig
{# Good: Organize by purpose #}
{% set wishlist = craft.superFavourite.collections()
    .handle('wishlist')
    .one() %}

{% set readLater = craft.superFavourite.collections()
    .handle('read-later')
    .one() %}

{# Bad: Too many collections can confuse users #}
```

### 2. Cache Expensive Queries

```twig
{# Cache favourite status checks #}
{% cache using key "user-#{currentUser.id}-fav-#{product.id}" %}
    {% set isFavourited = craft.superFavourite.favourites()
        .author(currentUser)
        .favouritedElement(product)
        .exists() %}
{% endcache %}
```

### 3. Handle Guest Users

```twig
{# Always check if user is logged in #}
{% if currentUser %}
    {# Show favourite button #}
{% else %}
    <a href="/login?redirect={{ craft.app.request.url|url_encode }}">
        Login to Favourite
    </a>
{% endif %}
```

### 4. Use Events for Side Effects

```php
// Don't put business logic in controllers
// Use events for notifications, analytics, etc.

Event::on(
    Plugin::class,
    Plugin::getInstance()->favourite::EVENT_AFTER_ADD_FAVOURITE,
    function(ModelEvent $event) {
        // Send notification
        // Track analytics
        // Update related data
    }
);
```

### 5. Validate Element Types

```php
// When allowing users to select element types
$allowedTypes = Plugin::getInstance()->getSettings()->allowedElementTypes;

if (!in_array($elementType, $allowedTypes)) {
    throw new \Exception('Element type not allowed');
}
```

### 6. Optimize Database Queries

```twig
{# Bad: N+1 query problem #}
{% set favourites = craft.superFavourite.favourites().all() %}
{% for favourite in favourites %}
    {% set element = craft.entries.id(favourite.elementId).one() %}
    {# This runs a query for each favourite! #}
{% endfor %}

{# Good: Eager load elements #}
{% set favourites = craft.superFavourite.favourites().all() %}
{% set elementIds = favourites|column('elementId') %}
{% set elements = craft.entries.id(elementIds).all()|index('id') %}

{% for favourite in favourites %}
    {% set element = elements[favourite.elementId] ?? null %}
    {# Much faster! #}
{% endfor %}
```

### 7. Use Appropriate HTTP Methods

```javascript
// Use POST for state-changing actions
$.post('/actions/super-favourite/favourite/add', data);

// Use GET for read-only checks
$.get('/actions/super-favourite/favourite/check', data);
```

### 8. Handle Errors Gracefully

```javascript
$.post('/actions/super-favourite/favourite/add', data)
    .done(function(response) {
        if (response.success) {
            // Success
        } else {
            // Show validation errors
            $.each(response.errors, function(field, messages) {
                showFieldError(field, messages.join(', '));
            });
        }
    })
    .fail(function(xhr) {
        // Network or server error
        showError('Something went wrong. Please try again.');
    });
```

### 9. Secure Admin Actions

```php
// In your controller actions
$this->requirePermission('super-favourite:manage-favourites');
$this->requireLogin();
$this->requirePostRequest();
```

### 10. Document Custom Events

```php
/**
 * @event ModelEvent Event triggered after adding a favourite
 *
 * Example:
 * ```php
 * Event::on(
 *     Plugin::class,
 *     Plugin::getInstance()->favourite::EVENT_AFTER_ADD_FAVOURITE,
 *     function(ModelEvent $event) {
 *         $favourite = $event->sender;
 *         // Your code here
 *     }
 * );
 * ```
 */
const EVENT_AFTER_ADD_FAVOURITE = 'afterAddFavourite';
```

---

## Troubleshooting

### Common Issues

#### 1. "User must be logged in" error

**Solution:** Check that users are authenticated before allowing favourite actions.

```twig
{% if currentUser %}
    {# Show favourite UI #}
{% else %}
    <a href="/login">Login to favourite</a>
{% endif %}
```

#### 2. Duplicate favourites

**Solution:** Use the `toggle` action instead of `add` to prevent duplicates.

```javascript
// Use toggle instead of add
$.post('/actions/super-favourite/favourite/toggle', data);
```

#### 3. Element not found in favourites list

**Solution:** Check if the element still exists and isn't trashed.

```twig
{% set element = craft.entries
    .id(favourite.elementId)
    .status(null) {# Include trashed #}
    .one() %}

{% if element and not element.trashed %}
    {# Display element #}
{% endif %}
```

#### 4. Custom fields not showing

**Solution:** Ensure field layout is properly configured and saved.

1. Go to Settings → Super Favourite
2. Check Field Layout tab
3. Verify fields are added to layout
4. Clear caches: `php craft clear-caches/all`

#### 5. Events not firing

**Solution:** Ensure events are registered in the right place and class.

```php
// Register in your plugin's init() method
Event::on(
    \amici\SuperFavourite\services\FavouriteService::class,
    \amici\SuperFavourite\services\FavouriteService::EVENT_AFTER_ADD_FAVOURITE,
    function(ModelEvent $event) {
        // Your code
    }
);
```

---

## Support

For issues, questions, or feature requests:

- **Email:** support@amiciinfotech.com
- **GitHub:** [Report an issue](https://github.com/amici-infotech/craft-super-favourites/issues)
- **Documentation:** [View online docs](https://github.com/amici-infotech/craft-super-favourites)

---

## License

Copyright © 2024 Amici Infotech. All rights reserved.


