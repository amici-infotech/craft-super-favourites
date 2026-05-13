# Frontend Forms

All frontend forms need CSRF protection. Most actions also require a logged-in user. Use `actionInput()` for normal Twig forms, or post to `/actions/...` with a CSRF token for JavaScript requests.

Frontend collection saving uses the same action as the Control Panel and currently requires `super-favourite:manage-collections`.

## Collection Save Form

Use one form for create, edit, global collections, user collections, default collections, allowed element types, and collection custom fields. The comments in the example explain what each field changes.

The `_self.fieldErrors()` macro renders validation errors from the model Craft returns after a failed submit. Use the returned `collection` variable when it exists so submitted values and errors are preserved.

```twig
{% macro fieldErrors(model, field) %}
    {% set errors = model ? model.getErrors(field) : [] %}
    {% if errors|length %}
        <ul class="errors">
            {% for error in errors %}
                <li>{{ error }}</li>
            {% endfor %}
        </ul>
    {% endif %}
{% endmacro %}

{% set collection = collection ?? null %}
{% set elementTypes = craft.superFavourite.getAvailableElementTypes() %}
{% set isDefault = collection ? collection.isDefault : false %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/account/collections') }}

    {# Include collectionId only when editing. Missing collectionId creates a new collection. #}
    {% if collection %}
        <input type="hidden" name="collectionId" value="{{ collection.id }}">
    {% endif %}

    {# Required. If handle is empty, the plugin generates a handle from this name. #}
    <input type="text" name="name" value="{{ collection ? collection.name : '' }}" required>
    {{ _self.fieldErrors(collection, 'name') }}

    {# Optional. Leave empty on create if you want the plugin to generate it. #}
    <input type="text" name="handle" value="{{ collection ? collection.handle : '' }}">
    {{ _self.fieldErrors(collection, 'handle') }}

    {# Optional plain collection description. #}
    <textarea name="description">{{ collection ? collection.description : '' }}</textarea>
    {{ _self.fieldErrors(collection, 'description') }}

    {# User collection: set this to a user ID, usually currentUser.id. #}
    {# Global collection: leave this field empty. #}
    {# Default collection: the plugin clears userId because default collections are global. #}
    <label>
        Owner user ID
        <input
            type="number"
            name="userId"
            value="{{ collection and collection.userId ? collection.userId : (currentUser ? currentUser.id : '') }}"
        >
    </label>
    {{ _self.fieldErrors(collection, 'userId') }}

    {# Default collection: check this box. Normal collection: leave unchecked. #}
    {# A default collection is used when favourites are saved without collectionId. #}
    <input type="hidden" name="isDefault" value="0">
    <label>
        <input type="checkbox" name="isDefault" value="1" {{ isDefault ? 'checked' }}>
        Use as default collection
    </label>
    {{ _self.fieldErrors(collection, 'isDefault') }}

    {# Allowed element types can be rendered dynamically from the plugin. #}
    {# To allow all element types, submit no allowedElementTypes[] checkboxes. #}
    {# If any allowedElementTypes[] values are submitted, only those types are allowed. #}

    {% for elementType in elementTypes %}
        <label>
            <input
                type="checkbox"
                name="allowedElementTypes[]"
                value="{{ elementType.value }}"
            >
            {{ elementType.label }}
        </label>
    {% endfor %}
    {{ _self.fieldErrors(collection, 'allowedElementTypes') }}

    {# Collection custom fields use the fields namespace. #}
    <textarea name="fields[shortIntro]">{{ collection ? collection.shortIntro : '' }}</textarea>

    <button type="submit">{{ collection ? 'Update collection' : 'Create collection' }}</button>
</form>
```

### Dynamic Allowed Element Types

Use `craft.superFavourite.getAvailableElementTypes()` when you want the form to stay in sync with registered Craft element types. This is the same pattern used by the demo template:

```twig
{% set elementTypes = craft.superFavourite.getAvailableElementTypes() %}

{% for elementType in elementTypes %}
    <label>
        <input
            type="checkbox"
            name="allowedElementTypes[]"
            value="{{ elementType.value }}"
        >
        {{ elementType.label }}
    </label>
{% endfor %}
```

To allow all element types, submit no `allowedElementTypes[]` values. If your UI has an "All Element Types" checkbox, use JavaScript to uncheck/disable the individual checkboxes and make sure no `allowedElementTypes[]` inputs are submitted.

## Delete a Collection

```twig
<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/delete') }}
    {{ redirectInput('/account/collections') }}

    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    {# Optional. Requests deletion of favourite items in the collection too. #}
    <input type="hidden" name="deleteItems" value="1">

    <button type="submit">Delete collection</button>
</form>
```

Default collections and collections with enabled favourite items can still be blocked by validation.

## Favourite Save Form

Use `favourite/add` for a simple add button. Use `favourite/save` when you need custom fields or CP-like editing behavior.

```twig
{% requireLogin %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/save') }}
    {{ redirectInput(craft.app.request.url) }}

    {# Include favouriteId only when editing an existing favourite item. #}
    {% if favourite is defined and favourite %}
        <input type="hidden" name="favouriteId" value="{{ favourite.id }}">
    {% endif %}

    {# The saved Craft element. #}
    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="elementType" value="{{ className(entry) }}">

    {# Optional. If omitted, the plugin uses the default collection. #}
    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    {# Optional note field built into the plugin. #}
    <textarea name="notes">{{ favourite is defined and favourite ? favourite.notes : '' }}</textarea>
    {{ _self.fieldErrors(favourite ?? null, 'notes') }}

    {# Favourite item custom fields also use the fields namespace. #}
    <select name="fields[priority]">
        <option value="low">Low</option>
        <option value="high">High</option>
    </select>

    <button type="submit">Save favourite</button>
</form>
```

For the simplest possible add form, the same required element fields can be posted to `super-favourite/favourite/add`.

## Remove or Delete a Favourite

Remove by element when you know the element and optional collection:

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

Delete by favourite item ID when you are listing favourite items:

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

