# Frontend Forms

All frontend forms need CSRF protection and usually require a logged-in user.

Use `actionInput()` when rendering normal Twig forms. For JavaScript requests, post to `/actions/...` and include the CSRF token.

## Create a User Collection

Creates a personal collection for the current user.

```twig
{% requireLogin %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/account/collections') }}

    <input type="hidden" name="userId" value="{{ currentUser.id }}">

    <label>
        Name
        <input type="text" name="name" required>
    </label>

    <label>
        Handle
        <input type="text" name="handle">
    </label>

    <label>
        Description
        <textarea name="description"></textarea>
    </label>

    <button type="submit">Create collection</button>
</form>
```

If `handle` is empty, the element's `beforeSave()` generates one from the name.

## Create a Global Collection

Global collections have no `userId`.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/collections') }}

    <input type="text" name="name" required>
    <input type="text" name="handle">
    <textarea name="description"></textarea>

    {# Empty or missing userId means global collection. #}
    <input type="hidden" name="userId" value="">

    <button type="submit">Create global collection</button>
</form>
```

Frontend collection save currently uses the same action as the CP and requires `super-favourite:manage-collections`.

## Create a Default Collection

Default collections are global. Do not send a user ID.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/collections') }}

    <input type="text" name="name" value="Default" required>
    <input type="text" name="handle" value="default">
    <input type="hidden" name="isDefault" value="1">
    <input type="hidden" name="userId" value="">

    <button type="submit">Save default collection</button>
</form>
```

## Restrict Allowed Element Types

`allowedElementTypes` can be omitted or empty for all element types.

```twig
{# Allow entries and assets only. #}
<input type="checkbox" name="allowedElementTypes[]" value="craft\elements\Entry" checked>
<input type="checkbox" name="allowedElementTypes[]" value="craft\elements\Asset" checked>
```

To explicitly allow all:

```twig
<input type="hidden" name="allowedElementTypes" value="*">
```

## Save Collection Custom Fields

Collection custom fields are submitted under `fields`.

```twig
<input type="text" name="fields[shortIntro]" value="">
<input type="color" name="fields[collectionColor]" value="#3366ff">
```

Full example:

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/account/collections') }}

    <input type="hidden" name="userId" value="{{ currentUser.id }}">
    <input type="text" name="name" required>
    <textarea name="fields[shortIntro]"></textarea>

    <button type="submit">Save collection</button>
</form>
```

## Edit a Collection

Pass `collectionId` to update an existing collection.

```twig
{% set collection = craft.superFavourite.collections()
    .id(craft.app.request.getQueryParam('id'))
    .one() %}

{% if collection %}
    <form method="post" accept-charset="UTF-8">
        {{ csrfInput() }}
        {{ actionInput('super-favourite/collection/save') }}
        {{ redirectInput('/account/collections') }}

        <input type="hidden" name="collectionId" value="{{ collection.id }}">
        <input type="hidden" name="userId" value="{{ collection.userId }}">

        <input type="text" name="name" value="{{ collection.name }}" required>
        <input type="text" name="handle" value="{{ collection.handle }}">
        <textarea name="description">{{ collection.description }}</textarea>

        <button type="submit">Update collection</button>
    </form>
{% endif %}
```

## Delete a Collection

The delete action requires `collectionId`.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/delete') }}
    {{ redirectInput('/account/collections') }}

    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <button type="submit">Delete collection</button>
</form>
```

Optionally ask the service/controller to delete favourite items too:

```twig
<input type="hidden" name="deleteItems" value="1">
```

Default collections and collections with enabled favourite items may be blocked by validation.

## Add a Favourite

```twig
{% requireLogin %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/add') }}
    {{ redirectInput(craft.app.request.url) }}

    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="elementType" value="{{ className(entry) }}">
    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <textarea name="notes" placeholder="Optional notes"></textarea>

    <button type="submit">Add to favourites</button>
</form>
```

## Save Favourite Custom Fields

The `favourite/save` action supports custom fields.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/save') }}
    {{ redirectInput(craft.app.request.url) }}

    <input type="hidden" name="elementType" value="{{ className(entry) }}">
    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <textarea name="notes"></textarea>
    <select name="fields[priority]">
        <option value="low">Low</option>
        <option value="high">High</option>
    </select>

    <button type="submit">Save favourite</button>
</form>
```

## Remove a Favourite by Element

Removes the current user's favourite for an element. If `collectionId` is omitted, matching favourites are removed from all collections.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/remove') }}
    {{ redirectInput(craft.app.request.url) }}

    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <button type="submit">Remove from favourites</button>
</form>
```

## Delete a Favourite by Favourite ID

Use this when you are listing favourite items and already have the favourite item ID.

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/delete') }}
    {{ redirectInput(craft.app.request.url) }}

    <input type="hidden" name="favouriteId" value="{{ favourite.id }}">

    <button type="submit">Delete favourite</button>
</form>
```

## Toggle a Favourite With JavaScript

The toggle action requires `elementId`, `elementType`, and `collectionId`.

```twig
<button
    type="button"
    data-favourite-toggle
    data-element-id="{{ entry.id }}"
    data-element-type="{{ className(entry) }}"
    data-collection-id="{{ collection.id }}"
>
    Toggle favourite
</button>

<script>
document.querySelector('[data-favourite-toggle]').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const formData = new FormData();

    formData.append('elementId', button.dataset.elementId);
    formData.append('elementType', button.dataset.elementType);
    formData.append('collectionId', button.dataset.collectionId);
    formData.append('{{ craft.app.config.general.csrfTokenName }}', '{{ craft.app.request.csrfToken }}');

    const response = await fetch('/actions/super-favourite/favourite/toggle', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    });

    const result = await response.json();
    console.log(result);
});
</script>
```

## Move a Favourite

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/move') }}
    {{ redirectInput(craft.app.request.url) }}

    <input type="hidden" name="favouriteId" value="{{ favourite.id }}">
    <select name="collectionId">
        {% for option in craft.superFavourite.getCollections() %}
            <option value="{{ option.id }}">{{ option.name }}</option>
        {% endfor %}
    </select>

    <button type="submit">Move</button>
</form>
```

