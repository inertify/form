# Release Notes

## [Unreleased](https://github.com/inertify/form/compare/v0.1.0...1.x)

### Changed

- **Breaking:** `Form` and `Wizard` now render the native `<form>` element instead of returning slot content only. The element carries `id` from the resolved form ID, `action` and `method` from the serialized resource, `novalidate`, a hidden `_method` field for `put`/`patch`/`delete`, and a submit handler that cancels the native navigation and submits through Inertia with `event.submitter`. Consumer attributes are merged last and override every default. Remove the hand-written `<form @submit.prevent="submit()">` wrapper from pages that use `Form` or `Wizard`; nested forms are invalid HTML and the inner element is discarded together with its submit handler.
- `FormProvider` remains renderless and is the supported way to create and provide the engine without an element.

### Added

- `Form` and `Wizard` expose the rendered element as `element` on a component ref.

## [v0.1.0](https://github.com/inertify/form/compare/...v0.1.0) - 202x-xx-xx

Initial pre-release.
