# Frontend Favourite Forms

All frontend forms need CSRF protection. Most favourite actions require a logged-in user. Use `actionInput()` for normal Twig forms, or post to `/actions/...` with a CSRF token for JavaScript requests.

Favourite save, move, and delete failures return the failed `favouriteItem`/`favourite` model with errors attached for normal form submissions, and JSON errors for AJAX requests.

## Favourite Save Form

Use `favourite/add` for a simple add button. Use `favourite/save` when you need custom fields or CP-like editing behavior.

```twig
{% requireLogin %}

<form method="post" accept-charset="UTF-8">
    {{ csrfInput() }}
    {{ actionInput('super-favourite/favourite/save') }}
    {{ redirectInput(craft.app.request.url) }}

    {% if favourite is defined and favourite %}
        <input type="hidden" name="id" value="{{ favourite.id }}">
    {% endif %}

    <input type="hidden" name="elementId" value="{{ entry.id }}">
    <input type="hidden" name="elementType" value="{{ className(entry) }}">

    <input type="hidden" name="collectionId" value="{{ collection.id }}">

    <textarea name="notes">{{ favourite is defined and favourite ? favourite.notes : '' }}</textarea>
    {{ _self.fieldErrors(favourite ?? null, 'notes') }}

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

    <input type="hidden" name="id" value="{{ favourite.id }}">

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

    <input type="hidden" name="id" value="{{ favourite.id }}">

    <select name="collectionId">
        {% for option in craft.superFavourite.getCollections() %}
            <option value="{{ option.id }}">{{ option.name }}</option>
        {% endfor %}
    </select>

    <button type="submit">Move</button>
</form>
```
