# Frontend Collection Forms

All frontend forms need CSRF protection. Collection saving requires `super-favourite:manage-collections`. Creating, editing, deleting, reordering, or setting default global collections also requires admin access or `super-favourite:manage-global-collections`.

## Collection Save Form

Use one form for create, edit, global collections, user collections, default collections, allowed element types, and collection custom fields. The comments in the example explain what each field changes.

The `_self.fieldErrors()` macro renders validation errors from the model Craft returns after a failed submit. Use the returned `collection` variable when it exists so submitted values and errors are preserved. Collection delete failures also return the failed `collection` model, so list pages can read `collection.getErrors()`.

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

{% set collection = collection ?? create('amici\\SuperFavourite\\elements\\Collection') %}
{% set elementTypes = craft.superFavourite.getAvailableElementTypes() %}
{% set isDefault = collection.isDefault %}
{% set canManageGlobalCollections = currentUser.admin or currentUser.can('super-favourite:manage-global-collections') %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/collection/save') }}
    {{ redirectInput('/account/collections') }}

    {% if collection.id %}
        <input type="hidden" name="id" value="{{ collection.id }}">
    {% endif %}

    <input type="text" name="name" value="{{ collection.name }}" required>
    {{ _self.fieldErrors(collection, 'name') }}

    <input type="text" name="handle" value="{{ collection.handle }}">
    {{ _self.fieldErrors(collection, 'handle') }}

    <textarea name="description">{{ collection.description }}</textarea>
    {{ _self.fieldErrors(collection, 'description') }}

    <label>
        Owner user ID
        <input
            type="number"
            name="userId"
            value="{{ collection.id and canManageGlobalCollections ? collection.userId : currentUser.id }}"
        >
    </label>
    {{ _self.fieldErrors(collection, 'userId') }}

    {% if canManageGlobalCollections %}
        <input type="hidden" name="isDefault" value="{{ isDefault ? '1' : '0' }}">
        <label>
            <input type="checkbox" name="isDefault" value="1" {{ isDefault ? 'checked disabled' }}>
            Use as default collection
        </label>
        {% if isDefault %}
            <p>Set another global collection as default before changing this one.</p>
        {% endif %}
        {{ _self.fieldErrors(collection, 'isDefault') }}
    {% else %}
        <input type="hidden" name="isDefault" value="0">
    {% endif %}

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

    <textarea name="fields[shortIntro]">{{ collection.shortIntro }}</textarea>

    <button type="submit">{{ collection.id ? 'Update collection' : 'Create collection' }}</button>
</form>
```

## Dynamic Allowed Element Types

Use `craft.superFavourite.getAvailableElementTypes()` when you want the form to stay in sync with registered Craft element types:

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

    <input type="hidden" name="id" value="{{ collection.id }}">

    {# Optional. Deletes the collection now, then queues favourite item cleanup. #}
    <input type="hidden" name="deleteItems" value="1">

    <button type="submit">Delete collection</button>
</form>
```

Without `deleteItems`, collections with enabled favourite items are blocked by validation. With `deleteItems=1`, the collection is deleted immediately and the plugin queues favourite item cleanup so large collections do not make the form submit wait.

Failed deletes return the failed `collection` model with errors attached, such as a default collection block, missing permission, or enabled favourite item warning.
