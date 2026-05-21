# Changelog

All notable changes to this project will be documented in this file.

## 5.0.1 - 2026-05-20

### Added
- Added Control Panel breadcrumbs across collection, favourite item, and settings screens.

### Fixed
- Fixed disabled collections and favourite items not loading from their backend detail/edit pages.
- Fixed backend detail page **Enabled** lightswitch changes not being saved for collections and favourite items.

## 5.0.0 - 2026-05-15

### Added
- Initial Craft CMS 5 release.
- Added custom `Collection` and `FavouriteItem` elements for organizing favourites into global and user-owned collections.
- Added support for favouriting Craft elements, including entries, assets, categories, users, tags, and Commerce element types when available.
- Added Control Panel sections for managing collections, favourite items, settings, and custom field layouts.
- Added Twig variables and element queries for checking favourite state, listing favourites, counting favourites, and querying collections.
- Added frontend actions for saving, adding, removing, toggling, moving, and deleting favourite items.
- Added frontend actions for creating, editing, deleting, reordering, and setting default collections.
- Added PHP services for creating collections, adding/removing/toggling/moving favourites, and querying favourite state.
- Added service events for collection and favourite workflows, including before/after create, delete, add, remove, and move events.
- Added custom field support for both collections and favourite items.
- Added collection-level allowed element type restrictions.
- Added model-based AJAX and normal form responses for collection and favourite actions.
- Added `success` boolean values to AJAX model responses.
- Added queue-based favourite item cleanup when deleting a collection with `deleteItems`.
- Added `super-favourite:manage-global-collections` permission for managing global/default collections.
- Added demo templates for AJAX and normal form testing.
- Added structured documentation under `docs/`.

### Changed
- Favourite save/add/toggle actions now validate the whole `FavouriteItem` element and return all model errors together.
- Collection save actions now validate the whole `Collection` element, including global/default permission rules.
- Favourite forms now require an explicit valid `collectionId`; favourites are no longer auto-assigned to a default collection when the field is omitted.
- Favourite `elementType` is derived from `elementId` when possible, so frontend favourite forms do not need to submit it.
- `Collection::allowedElementTypes` is exposed as an array in PHP and Twig.
- Collection deletion now deletes the collection immediately and queues favourite item cleanup when requested.
- Soft-deleted collection handles can be reused.

### Fixed
- Fixed validation and error reporting so frontend forms receive failed `collection` or `favourite` models with attached errors.
- Fixed AJAX collection and favourite actions returning redirects instead of JSON model responses.
- Fixed backend collection deletes reporting success when element validation blocked deletion.
- Fixed duplicate favourite handling with element-level validation.
- Fixed permission gaps around creating, editing, and deleting global/default collections.
- Fixed unsetting the active default collection directly.
- Fixed collection handle uniqueness conflicts caused by soft-deleted collections.
- Fixed demo template behavior when JavaScript is disabled.
- Fixed undefined Twig variables and outdated frontend form examples.
