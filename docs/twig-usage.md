# Twig Usage

The plugin registers a Twig variable:

```twig
craft.superFavourite
```

## Check if an Element Is Favourited

```twig
{% set isSaved = craft.superFavourite.isFavourited(entry.id) %}

{% if isSaved %}
    Saved
{% else %}
    Not saved yet
{% endif %}
```

Pass a collection ID to check a specific collection:

```twig
{% set isSaved = craft.superFavourite.isFavourited(entry.id, collection.id) %}
```

## Get Favourite IDs

```twig
{% set ids = craft.superFavourite.getFavouritedElementIds() %}
```

Filter by collection:

```twig
{% set ids = craft.superFavourite.getFavouritedElementIds(collection.id) %}
```

Filter by element type:

```twig
{% set ids = craft.superFavourite.getFavouritedElementIds(null, 'craft\\elements\\Entry') %}
```

Use IDs in a Craft element query:

```twig
{% set savedEntries = craft.entries()
    .id(ids)
    .all() %}
```

## List Current User Collections

```twig
{% set collections = craft.superFavourite.getCollections() %}

{% for collection in collections %}
    <h2>{{ collection.name }}</h2>
    <p>{{ collection.description }}</p>
{% endfor %}
```

`getCollections()` returns global collections and collections owned by the current user.

## List Global and Personal Collections Separately

```twig
{% set globalCollections = craft.superFavourite.collections()
    .userId(null)
    .all() %}

{% set personalCollections = currentUser
    ? craft.superFavourite.collections().userId(currentUser.id).all()
    : [] %}
```

## Query Collections Directly

```twig
{% set collection = craft.superFavourite.collections()
    .handle('wishlist')
    .one() %}
```

Available collection query methods:

- `.userId(value)`
- `.name(value)`
- `.handle(value)`
- `.isDefault(value)`
- `.sortOrder(value)`

Craft's normal element query methods also work, such as `.id()`, `.status()`, `.limit()`, `.orderBy()`, `.one()`, `.all()`, and `.count()`.

## Query Favourite Items Directly

```twig
{% set favourites = craft.superFavourite.favourites()
    .collectionId(collection.id)
    .all() %}
```

Available favourite query methods:

- `.userId(value)`
- `.author(value)`
- `.collectionId(value)`
- `.elementId(value)`
- `.favouritedElement(value)`
- `.favouritedElementType(value)`
- `.sortOrder(value)`

## Render Elements From Favourite Items

Favourite items store `elementId` and `elementType`, so use `createElementQuery()` to load the saved element.

```twig
{% set favourites = craft.superFavourite.favourites()
    .collectionId(collection.id)
    .all() %}

{% for favourite in favourites %}
    {% set elementQuery = craft.superFavourite.createElementQuery(favourite.elementType) %}
    {% set element = elementQuery ? elementQuery.id(favourite.elementId).one() : null %}

    {% if element %}
        <article>
            <h2>{{ element.title ?? 'Untitled' }}</h2>
            {% if element.url %}
                <a href="{{ element.url }}">View</a>
            {% endif %}
        </article>
    {% endif %}
{% endfor %}
```

## Get a Collection by Handle

```twig
{% set wishlist = craft.superFavourite.getCollectionByHandle('wishlist') %}
```

This method uses the current user. For global-only lookups, use the collection query:

```twig
{% set globalWishlist = craft.superFavourite.collections()
    .userId(null)
    .handle('wishlist')
    .one() %}
```

## Counts

Current user's favourite count:

```twig
{{ craft.superFavourite.getFavouriteCount() }}
```

Current user's count in one collection:

```twig
{{ craft.superFavourite.getFavouriteCount(collection.id) }}
```

Collection item count from the element:

```twig
{{ collection.getItemCount() }}
```

Direct query count:

```twig
{{ craft.superFavourite.favourites()
    .collectionId(collection.id)
    .count() }}
```

## Available Element Types

```twig
{% set elementTypes = craft.superFavourite.getAvailableElementTypes() %}

{% for type in elementTypes %}
    <option value="{{ type.value }}">{{ type.label }}</option>
{% endfor %}
```

The plugin excludes internal plugin element types and common internal/system types.

