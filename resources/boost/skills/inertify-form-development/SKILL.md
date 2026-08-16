---
name: inertify-form-development
description: >
  Build headless Laravel and Inertia Vue forms with inertify/form, including
  schemas, binding, validation, choices, collections, editors, wizards, and uploads.
license: MIT
metadata:
  author: Enkot
  package: inertify/form
---

# Inertify Form

Use this skill when a Laravel 12 or 13 application uses `inertify/form` with Inertia 3 and Vue 3. The package supplies form schema and behavior; the application renders every element and style. It is an independent clean-room package, not an Inertia UI distribution or compatibility layer for proprietary internals.

## Primary Goal

Create the smallest correct PHP schema, pass it directly as a request-aware Inertia property, validate it through the typed `#[Validate]` attribute, and connect it to app-owned Vue markup without introducing package-owned UI.

## Workflow

1. Require PHP 8.3+, Laravel 12/13, Inertia Laravel 3.3+, Vue 3.5+, and `@inertiajs/vue3` 3.6+. Install `inertify/form` and `@inertify/form-vue`. Publish optional config with `php artisan vendor:publish --tag=inertia-forms-config`; `inertify-form` and `inertify-form-config` are aliases.
2. Generate `app/Forms/{Name}Form.php` with `php artisan make:form {Name}Form`, or extend `Inertify\Form\Form` directly.
3. Return functional fields from `fields()`. Use `Fieldset` for semantic groups, `Repeater` or `Blocks` for nested values, and `WizardConfig` only for step behavior. Put layout and styling in Vue.
4. Configure the action with `route()` or `url()` and a verb helper. For edit forms, call `bind($modelOrArray, except: [...])`; use `data([...])` only for explicit initial overrides.
5. Pass the form instance directly to `Inertia::render()`. Never pre-serialize it: the owning property name and request drive query-backed choices and conditional serialization.
6. Type the write parameter as `#[Inertify\Form\Validate] YourForm $form`. Use `$form->validated(files: false)` for mass assignment. Resolve tokenized uploads with `$form->upload()`, `$form->uploads()`, `request()->formUpload()`, or `request()->orderedFormUploads()`; native `storeWithForm()` files remain in `validated()` or Laravel's request file bag.
7. Render through `Form` and the `FormFields` / `FormField` components returned by `createFormRenderer()`. `Form` renders the page's `<form>` element — `id`, `action`, `method`, `novalidate`, method spoofing, and Inertia submission are wired for you, so place fields and a `type="submit"` button directly inside it and never nest another `<form>`. Use `FormProvider` for the same engine without an element. Call the factory without options for app-owned slots, or pass a registry for reusable renderers. Slot precedence is exactly `field-{qualified-path}` → `type-{kebab-component}` → `default` → deprecated `{qualified-path}-field` fallback.
8. Use the slot's qualified `name` with field composables. Register the app-owned input element for first-error scrolling; do not expect the package to create a wrapper or input. In applications with several form pages, call `createFormRenderer()` once with an app-owned component registry and reuse its generated components instead of repeating type slots.
9. For choices, use inline `options()`, query-backed `options(Model::class|Builder)` for Inertia partial reloads, or `source()` for an application JSON endpoint. Set `options.propKey` in Vue when the owning Inertia prop is not named `form`.
10. For asynchronous uploads, opt in with `Route::inertiaFormUploads()`. Defaults are `/_inertia-forms`, `inertia-forms.*`, `web`, and `auth`; keep application-specific authorization/rate limiting and review disk, lifetime, and byte/KiB limits.
11. Schedule `form:cleanup-uploads`. Cleanup is restricted to package-owned directories, aborts expired pending S3 multipart sessions, retains completed direct uploads through token lifetime, and leaves individual failures retriable.
12. If the app already uses Spatie Media Library, use `ExistingFile::fromMediaLibrary()` and `MediaLibraryUploads::syncCollection()`. The integration is optional and does not install or configure Spatie.

## References

- `README.md` — installation, schemas, Vue rendering, choice modes, uploads, rich text, and extensions
- `config/inertia-forms.php` — upload disks, prefix, middleware, lifetimes, and size limits
- `app/Forms/` — consuming application's form schemas
- `routes/web.php` — application actions, choice endpoints, and opt-in upload routes
- `resources/js/Pages/` — application-owned headless slots and markup
- Public npm exports — `.`, `./components`, `./composables`, and `./package.json`

## Examples

Define and return a bound form:

```php
use App\Forms\ProfileForm;
use App\Models\Profile;
use Inertia\Inertia;
use Inertia\Response;

public function edit(Profile $profile): Response
{
    return Inertia::render('Profiles/Edit', [
        'form' => ProfileForm::make()
            ->bind($profile)
            ->route('profiles.update', ['profile' => $profile])
            ->patch(),
    ]);
}
```

Validate and separate uploads:

```php
use App\Forms\ProfileForm;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Inertify\Form\Validate;

public function update(#[Validate] ProfileForm $form, Profile $profile): RedirectResponse
{
    $profile->update($form->validated(files: false));

    $form->upload('avatar')?->store('avatars', 'public');

    return back();
}
```

Use a conditional field:

```php
use Inertify\Form\Fields\TextInput;

TextInput::make('company')
    ->visibleWhen('is_employed', true)
    ->clearWhenHidden();
```

Opt in to upload endpoints and schedule cleanup:

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schedule;

Route::inertiaFormUploads();
Schedule::command('form:cleanup-uploads')->hourly();
```

Synchronize an existing optional Media Library collection:

```php
use Inertify\Form\Uploads\MediaLibraryUploads;

MediaLibraryUploads::syncCollection(
    request(),
    $profile,
    field: 'gallery',
    collection: 'gallery',
    disk: 'public',
);
```

Render a reusable Vue type slot:

```vue
<script setup lang="ts">
import {
  createFormRenderer,
  Form,
  type FormResource,
} from '@inertify/form-vue'

defineProps<{ form: FormResource }>()

const { FormFields } = createFormRenderer()
</script>

<template>
  <Form :form="form" v-slot="{ form: context, processing }">
    <FormFields :form="context">
      <template #type-text="{ field, name, value, error, setValue, blur, registerElement }">
        <label :for="name">{{ field.label }}</label>
        <input
          :id="name"
          :ref="registerElement"
          :value="value"
          @input="setValue(($event.currentTarget as HTMLInputElement).value)"
          @blur="blur"
        />
        <p v-if="error">{{ error }}</p>
      </template>
    </FormFields>

    <button type="submit" :disabled="processing">Save</button>
  </Form>
</template>
```

The slot's `name` is the qualified path. Calling `createFormRenderer()` without a registry is the slot-only API; the underlying field traversal components are internal. A shadcn-vue input may replace `<input>`, but it must remain an application-owned import such as `@/components/ui/input`.

For repeated application layouts, create the app components once:

```ts
import { createFormRenderer } from '@inertify/form-vue'
import CheckboxField from './CheckboxField.vue'
import TextField from './TextField.vue'

export const { FormField, FormFields } = createFormRenderer({
  renderers: {
    Text: TextField,
    Textarea: { component: TextField, props: { multiline: true } },
    Checkbox: CheckboxField,
    Submit: null,
  },
})
```

Generated `FormFields` renders declared layout fields once; registered repeater and block renderers own their descendants. Use generated `FormField` with a qualified path such as `projects.0.title` when a custom layout must place a nested field explicitly.

Use `FormFieldsets` to iterate selected server-declared groups in application-owned sections or cards. Its `only` and `except` props accept an exact fieldset ID string or readonly string array; `only` narrows first and `except` then removes matches, while results retain schema order. Selection does not reveal hidden fieldsets or fields—control that independently with `include-hidden`. Give selectable PHP fieldsets explicit, unique IDs with `Fieldset::id()`. For a fixed page layout, place `<FormFields fieldset="identity" />` inside each card instead.

Registry keys are exact serialized `component` discriminators, not PHP class names: `TextInput`, `OtpInput`, and `Hidden` serialize as `Text`, `Otp`, and `Hidden`. Pages may use `<FormFields />` for schema order or `<FormField name="email" />` inside their own grid. Direct components and `{ component, props }` definitions receive the complete field slot payload; preset props cannot replace its behavioral keys, and `null` is intentionally renderless. Unknown fields remain renderless unless the app supplies `fallback` or an `unsupported` slot. Renderers that consume only part of the payload should set `inheritAttrs: false` to prevent unused controller values from reaching their root DOM. Keep `field-*` / `type-*` slots for local overrides; each factory snapshots an instance-local registry and does not globally register a UI layer.

Choose the correct remote option mode:

```php
use App\Models\Person;
use Inertify\Form\Fields\Combobox;

// Query-backed: owning Inertia prop is partially reloaded.
Combobox::make('assignee_id')
    ->options(Person::class, label: 'name', value: 'id')
    ->perPage(25)
    ->searchable();

// Endpoint-backed: the app returns { data: [{ value, label }], ...pagination }.
Combobox::make('reviewer_id')
    ->source(route('people.index', absolute: false))
    ->selectedSource(route('people.index', absolute: false))
    ->valuesParam('values')
    ->searchable();
```

Use the distinct content shapes:

```php
use Inertify\Form\Fields\Composer;
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\UploadConfig;

Composer::make('message')
    ->allowAttachments()
    ->acceptedFileTypes('application/pdf'); // { text, attachments }

RichText::make('body')
    ->imageUploads(
        fn (UploadConfig $images): UploadConfig => $images->image(),
    ); // body is HTML; body_images is the token array
```

After validation, store and rewrite rich-text image markers:

```php
use Illuminate\Support\Facades\Storage;
use Inertify\Form\RichText\RichTextImage;
use Inertify\Form\RichText\RichTextUploads;
use Inertify\Form\Uploads\SubmittedUpload;

$data = $form->validated(files: false);
$data['body'] = RichTextUploads::from(request(), 'body')
    ->storeImagesUsing(function (SubmittedUpload $upload, RichTextImage $image) {
        $path = $upload->store('post-images', 'public', deleteTemporary: false);

        return $image
            ->src(Storage::disk('public')->url($path))
            ->identifier($path, ['disk' => 'public']);
    })
    ->toHtml();
```

Use `RichTextContent::from($html)->replaceImagesUsing(...)->toHtml()` when durable image markers must resolve fresh URLs. Sanitize untrusted HTML according to the application's policy.

## Anti-patterns

- Do not expect package-owned markup, CSS, Tailwind classes, shadcn components, icons, or editor UI beyond the unstyled `<form>` element rendered by `Form` and `Wizard`.
- Do not nest an application `<form>` inside `Form` or `Wizard`, and do not re-add `@submit.prevent="submit()"` to them. Nested forms are invalid HTML and lose the submit handler; use `FormProvider` when the element must live elsewhere.
- Do not add presentation-only schema modifiers. Layout, variants, orientation, icons, and classes belong in application Vue components.
- Do not pre-serialize a form or move its data into a separate prop; doing so loses request/property context and can desynchronize authorization.
- Do not override the slot order or put deprecated `{name}-field` compatibility slots ahead of `field-{path}`, `type-{component}`, or `default`.
- Do not pass unauthorized fields in a separate data prop; authorization removes them from schema, data, and rules together.
- Do not use `upload()` for native `storeWithForm()` files, and do not save encrypted upload tokens as paths. Resolve tokenized values through form/request accessors.
- Do not treat Spatie Media Library as required; its synchronization adapter is conditional on a compatible model.
- Do not expose upload or choice-creation routes without authentication, authorization, rate limits, and tenant boundaries appropriate to the application.
- Do not delete arbitrary storage paths during cleanup; use `form:cleanup-uploads`, which scopes deletion to validated package-owned directories.
- Do not manually duplicate nested form state in Vue; use `useForm`, `useFormCollection`, `useFormWizard`, and field controllers.
- Do not submit Composer attachments as a RichText token list: Composer uses `{ text, attachments }`, while RichText uses an HTML value plus `<field>_images`.
- Do not persist rich-text image tokens without matching HTML markers, or render untrusted HTML without sanitization.
- Do not deep-import npm files outside the declared root, components, and composables exports.
