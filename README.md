<div align="center">
    <h1>Inertify Form</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/enkot/inertify-form"><img src="https://img.shields.io/packagist/v/enkot/inertify-form.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/enkot/inertify-form"><img src="https://img.shields.io/packagist/php-v/enkot/inertify-form.svg?style=flat-square" alt="Supported PHP versions"></a>
    <a href="https://github.com/enkot/inertify-form/actions"><img src="https://img.shields.io/github/actions/workflow/status/enkot/inertify-form/tests.yml?branch=main&label=tests&style=flat-square" alt="Test status"></a>
    <a href="https://packagist.org/packages/enkot/inertify-form"><img src="https://img.shields.io/packagist/dt/enkot/inertify-form.svg?style=flat-square" alt="Total downloads"></a>
</p>

Inertify Form is a completely headless, schema-driven form library for Laravel, Inertia, and Vue. Laravel owns schema, authorization, validation, binding, and uploads. The Vue package owns form state and behavior. Your application owns every element, class, component, icon, and accessibility decision.

The runtime includes no CSS, Tailwind preset, shadcn component, editor, icon, or default field presenter.

This package is an independent, clean-room implementation based only on the publicly documented Inertia Forms v1.3.1 API as available on August 10, 2026. It is not affiliated with, endorsed by, or a redistribution of Inertia UI or its proprietary package.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13
- `inertiajs/inertia-laravel` 3.3 or newer
- Vue 3.5 or newer
- `@inertiajs/vue3` 3.6 or newer

React is not supported.

## Installation

Install the Laravel and Vue packages:

```bash
composer require enkot/inertify-form
npm install @inertify/form-vue
```

The Laravel service provider is discovered automatically. Publish the optional upload configuration when its defaults do not fit your application:

```bash
php artisan vendor:publish --tag=inertia-forms-config
```

The aliases `inertify-form` and `inertify-form-config` are also registered for package-native workflows.

Generate a form class with:

```bash
php artisan make:form ProfileForm
```

## Define a form schema

Forms are regular container-resolved PHP classes. Functional field metadata is serialized; presentation-only concepts such as grids, variants, icons, CSS classes, and decorative progress settings are intentionally absent.

```php
<?php

namespace App\Forms;

use Inertify\Form\Fields\Checkbox;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Fields\Textarea;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Form;
use Inertify\Form\WizardConfig;

final class ProfileForm extends Form
{
    protected bool $unsavedWarning = true;

    protected bool $scrollToFirstError = true;

    public function fields(): array
    {
        return [
            Fieldset::make('Identity')
                ->id('identity')
                ->description('Tell us how to contact you.')
                ->fields([
                    TextInput::make('name', 'Name')
                        ->required()
                        ->maxLength(120)
                        ->precognitive(),
                    TextInput::make('email', 'Email')
                        ->email()
                        ->required()
                        ->precognitive(),
                    Checkbox::make('is_employed', 'Currently employed')
                        ->default(false),
                    TextInput::make('company', 'Company')
                        ->visibleWhen('is_employed', true)
                        ->clearWhenHidden(),
                ]),
            Fieldset::make('Experience')
                ->id('experience')
                ->fields([
                    Combobox::make('skill', 'Primary skill')
                        ->source(route('skills.index', absolute: false))
                        ->searchable()
                        ->preload(),
                    Repeater::make('projects', 'Projects')
                        ->schema([
                            TextInput::make('title')->required(),
                            Textarea::make('summary')->maxLength(500),
                        ])
                        ->default([['title' => '', 'summary' => '']])
                        ->rules(['array'])
                        ->minItems(1),
                ]),
            Fieldset::make('About')
                ->id('about')
                ->fields([
                    File::make('avatar')->image()->maxSize(5 * 1024),
                    Textarea::make('bio')->maxLength(1000),
                    Submit::make('Save profile'),
                ]),
        ];
    }

    public function wizard(): WizardConfig
    {
        return WizardConfig::make()
            ->step('Identity', 'Account details')
            ->step('Experience', 'Skills and projects')
            ->step('About', 'Biography and avatar')
            ->validateOnStep();
    }
}
```

Functional fields currently include:

- Text and structured input: `TextInput`, `Textarea`, `Slug`, `Link`, `Hidden`, `OtpInput`
- Choices: `Combobox`, `Radio`, `Checkbox`, `CheckboxGroup`, `Toggle`
- Specialized values: `DatePicker`, `TimePicker`, `ColorPicker`, `Slider`
- Content and files: `File`, `Composer`, `RichText`
- Nested schemas: `Repeater`, `Blocks`, `BlockSet`, `KeyValue`, `Fieldset`
- Submission: `Submit`

All fields support labels, help, placeholders, rules, defaults, authorization, conditions, data attributes, arbitrary metadata, and fluent Laravel helpers such as `when()` and `tap()`.

## Return and validate forms

Pass the form instance directly to Inertia. It is a context-aware Inertia property, so it receives the current request and owning property key during serialization.

```php
use App\Forms\ProfileForm;
use App\Models\Profile;
use Inertia\Inertia;
use Inertia\Response;

public function edit(Profile $profile): Response
{
    return Inertia::render('Profiles/Edit', [
        'form' => ProfileForm::make()
            ->bind($profile, except: ['internal_note'])
            ->route('profiles.update', ['profile' => $profile])
            ->patch(),
    ]);
}
```

Use the contextual `#[Validate]` attribute to resolve, bind to the current request, authorize, and validate the typed form before the controller runs:

```php
use App\Forms\ProfileForm;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Inertify\Form\Validate;

public function update(#[Validate] ProfileForm $form, Profile $profile): RedirectResponse
{
    $profile->update($form->validated(files: false));

    if ($avatar = $form->upload('avatar')) {
        $avatar->store('avatars', 'public');
    }

    return back()->with('success', 'Profile updated.');
}
```

You may also call `setRequest()`, `validate()`, and `validated()` explicitly. `validated()` keeps native file values or secure upload tokens. `validated(files: false)` removes every `File` field. Resolve temporary, chunked, direct, and existing-file tokens with `upload()` or `uploads()`; read a native `storeWithForm()` upload from `validated()` or Laravel's request file bag.

Forms bind from Eloquent models or arrays. Use `data([...])` to override initial values and `data()` to read the resolved initial payload. Defaults are applied through dotted paths, including nested repeater rows.

Authorization may be declared on the form, a fieldset, or a field with `authorize()`, `authorizedWhen()`, or `authorizedUnless()`. Unauthorized fields are absent from schema, data, and validation rules. Unauthorized forms serialize as an empty form resource unless `inertia-forms.authorization.throw_on_unauthorized` is enabled.

## Render your own Vue markup

`HeadlessForm` creates the form engine, provides it to descendants, and returns only slot content. `HeadlessFormFields` resolves slots in this exact order:

1. `field-{path}` slot
2. `type-{component}` slot, normalized to kebab case
3. `default`
4. `{name}-field` deprecated compatibility fallback

Here, `path` is the fully qualified data path, including collection indexes. The deprecated `{name}-field` slot is consulted only after `default`; it never overrides a path or type slot. Insertion slots use `before-{path}-field` and `after-{path}-field`. Fieldset components expose matching fieldset slots.

```vue
<script setup lang="ts">
import {
  HeadlessForm,
  HeadlessFormFields,
  type FormResource,
} from '@inertify/form-vue'
import { Input } from '@/components/ui/input'

defineProps<{ form: FormResource }>()
</script>

<template>
  <HeadlessForm :form="form">
    <template #default="{ form: context, submit, processing }">
      <form @submit.prevent="submit()">
        <HeadlessFormFields :form="context">
          <template
            #type-text-input="{
              field,
              name,
              value,
              error,
              disabled,
              readonly,
              setValue,
              blur,
              registerElement,
            }"
          >
            <label :for="`field-${name}`">{{ field.label }}</label>
            <Input
              :id="`field-${name}`"
              :ref="registerElement"
              :name="name"
              :model-value="value"
              :placeholder="field.placeholder"
              :disabled="disabled"
              :readonly="readonly"
              @update:model-value="setValue"
              @blur="blur"
            />
            <p v-if="error" role="alert">{{ error }}</p>
          </template>
        </HeadlessFormFields>

        <button type="submit" :disabled="processing">Save</button>
      </form>
    </template>
  </HeadlessForm>
</template>
```

`Input` above is an application-owned component generated or installed through shadcn-vue; it is not imported from this package. Plain HTML or any Vue component library works equally well.

The field slot also receives `controller`, `errors`, `visible`, `touched`, `dirty`, `required`, `validate`, and mutation helpers. `name` is the qualified path, while `field.path` and `field.schemaField` expose the resolved path and original schema. Registering the consumer-owned input element enables `scrollToFirstError` without requiring package markup.

Use `HeadlessFormProvider` when only context provision is needed, `HeadlessFormFieldsets` for fieldset-level rendering, `HeadlessWizard` for wizard navigation, `HeadlessFormCollection` for stable collection identities and move operations, and `HeadlessFormUploads` for upload state. `Form` is an alias-compatible renderless entry point. These components return consumer slot nodes or `null`; none adds a package-owned DOM element.

All behavior is also available composables-first:

```ts
import {
  useForm,
  useFormContext,
  useFormField,
  useFormFields,
  useFormValidation,
  useFormVisibility,
  useFormSubmission,
  useFormWizard,
  useFormCollection,
  useFormCollections,
  useFormCombobox,
  useFormUploads,
} from '@inertify/form-vue'
```

The engine supports nested values and errors, dirty and touched state, transforms, defaults and resets, cancellation, debounced Precognition validation with stale-request cancellation, conditional transitions with `clearWhenHidden`, wizard guards, collection reordering, upload progress, and unsaved-navigation protection.

The npm package has explicit root, component, and composable entry points with ESM, CommonJS, and declaration outputs:

```ts
import { HeadlessForm, useForm, type FormResource } from '@inertify/form-vue'
import { HeadlessFormFields } from '@inertify/form-vue/components'
import { useFormCombobox, useFormUploads } from '@inertify/form-vue/composables'
```

Only `.`, `./components`, `./composables`, and `./package.json` are public exports. Do not import files from package internals.

## Remote comboboxes

A combobox may use inline options, an Eloquent query serialized through the owning Inertia prop, or an application JSON endpoint. In all cases, render it with `useFormCombobox(name, form)` from the field slot; `name` is the qualified path.

For a simple model or query, `options()` enables request-aware paging, search against the mapped label column, selected-option hydration, and membership validation. The controller reloads only the owning Inertia property and merges later pages:

```php
use App\Models\Person;
use Inertify\Form\Fields\Combobox;

Combobox::make('assignee_id', 'Assignee')
    ->options(
        Person::query()->where('active', true),
        label: 'name',
        value: 'id',
    )
    ->perPage(25)
    ->searchable();
```

The Vue engine assumes the owning property is named `form`. If it is named differently, identify it when creating the engine:

```vue
<HeadlessForm :form="profileForm" :options="{ propKey: 'profileForm' }">
  <!-- app-owned markup -->
</HeadlessForm>
```

Use `source()` when search, grouping, selected hydration, or creation belongs in a dedicated application endpoint:

```php
Combobox::make('assignee_id')
    ->source(route('people.index', absolute: false))
    ->selectedSource(route('people.index', absolute: false))
    ->searchParam('search')
    ->valuesParam('values')
    ->pageParam('page')
    ->perPage(25)
    ->debounce(250)
    ->searchable()
    ->createRecordUsing(route('people.store', absolute: false), 'post', 'name')
```

The option endpoint should return an array or a paginator-shaped payload whose `data` contains `{ value, label }` items. It may also return `description`, `group`, `disabled`, and `disabledReason`. Search requests include the configured search/page parameters; selected hydration sends the configured values parameter; creation may return one option or `{ "item": option }`. Authorize these endpoints like any other application endpoint. The workbench contains a complete app-owned endpoint example with search, selected hydration, and pagination.

## Uploads

Upload endpoints are opt-in. Register them in the host application's route file:

```php
use Illuminate\Support\Facades\Route;

Route::inertiaFormUploads();
```

The default prefix is `/_inertia-forms`, route names use `inertia-forms.*`, and middleware is `web` plus `auth`. The macro accepts custom `prefix`, `middleware`, and `name` arguments. Keep authentication in place and add application-specific authorization, tenant, and rate-limit middleware where needed; these endpoints can allocate storage and must not be exposed as an unbounded public service.

`File` supports four strategies:

```php
File::make('document')->storeWithForm();
File::make('avatar')->image();                    // temporary upload
File::make('archive')->chunked(5 * 1024 * 1024); // resumable chunks
File::make('video')->directToStorage('s3');       // direct/multipart
```

`storeWithForm()` submits native browser files with the form. Temporary uploads transfer before submission, chunked uploads resume from a reported byte offset, and direct uploads use the configured disk's temporary upload URL or multipart API when available. Non-S3 disks use the package's multipart fallback.

The serialized field includes the documented flat route properties and an additive headless upload descriptor. `useFormUploads` and `HeadlessFormUploads` expose progress, cancellation, pause/resume, retry, removal, reordering, and clearing. Tokens are encrypted and expiring; the server rejects tampered, expired, disk-mismatched, or validation-profile-mismatched tokens.

File constraints such as `image()`, `accept()`, `minSize()`, `maxSize()`, and `dimensions()` are applied to upload content rather than token strings. Use `requireValidatedUploads()` and `validateUploadsUsing()` for reusable validation profiles. Existing files can be serialized with `ExistingFile::fromDisk()` or the optional Media Library adapter.

Spatie Media Library remains an optional suggested dependency. When the model exposes Media Library's public API, serialize its existing collection and synchronize retained, new, removed, and reordered items after validation:

```php
use Inertify\Form\Uploads\ExistingFile;
use Inertify\Form\Uploads\MediaLibraryUploads;

File::make('gallery')
    ->multiple()
    ->mediaCollection('gallery')
    ->existingFiles(
        fn () => ExistingFile::fromMediaLibrary($profile->getMedia('gallery')),
    );

MediaLibraryUploads::syncCollection(
    request: request(),
    model: $profile,
    field: 'gallery',
    collection: 'gallery',
    disk: 'public',
);
```

The adapter is conditional: it throws a clear exception for models that do not expose compatible Media Library methods and does not install or configure Spatie's package for the host application.

Clean expired temporary uploads manually or from Laravel's scheduler:

```bash
php artisan form:cleanup-uploads
php artisan form:cleanup-uploads --lifetime=7200
```

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('form:cleanup-uploads')->hourly();
```

Cleanup scans only package-owned upload directories on the configured temporary and direct disks. It removes expired temporary uploads, aborts expired pending S3 multipart sessions before deletion, retains completed direct uploads for the submission-token lifetime, continues after individual failures, and leaves failures retriable. The request macros `formUpload('avatar')` and `orderedFormUploads('photos')` are available when direct request access is preferable.

## Composer and rich text

Neither content field bundles an editor. `Composer` is a string or `null` until attachments are enabled; with attachments its submitted value is `{ text: string, attachments: array }`. `validated(files: false)` keeps `text` and removes `attachments`.

```php
use Inertify\Form\Fields\Composer;

Composer::make('message')
    ->allowAttachments()
    ->acceptedFileTypes(['image/png', 'application/pdf'])
    ->maxFileSize(5 * 1024)
    ->reorderable();
```

`RichText` remains an HTML string. `imageUploads()` adds a separate token array named `<field>_images`; for `body`, that is `body_images`. The application-owned editor inserts each returned token into the matching image as `data-inertia-forms-upload="<token>"` and keeps the companion token array synchronized.

```php
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\UploadConfig;

RichText::make('body')
    ->maxLength(50_000)
    ->imageUploads(
        fn (UploadConfig $images): UploadConfig => $images
            ->maxSize(2 * 1024)
            ->directToStorage('private'),
    );
```

After form validation, rewrite the temporary markers while storing the matching uploads. Passing `deleteTemporary: false` lets `RichTextUploads` remove every token only after the whole rewrite succeeds:

```php
use Inertify\Form\RichText\RichTextImage;
use Inertify\Form\RichText\RichTextUploads;
use Inertify\Form\Uploads\SubmittedUpload;

$data = $form->validated(files: false);
$data['body'] = RichTextUploads::from(request(), 'body')
    ->storeImagesUsing(function (
        SubmittedUpload $upload,
        RichTextImage $image,
    ): RichTextImage {
        $path = $upload->store(
            'post-images',
            'private',
            deleteTemporary: false,
        );

        return $image->identifier($path, ['disk' => 'private']);
    })
    ->keepTokenized()
    ->toHtml();
```

For tokenized stored HTML, resolve a fresh URL before returning content to the client:

```php
use Illuminate\Support\Facades\Storage;
use Inertify\Form\RichText\RichTextContent;
use Inertify\Form\RichText\RichTextImage;
use Inertify\Form\RichText\RichTextStoredImage;

$body = RichTextContent::from($post->body)
    ->replaceImagesUsing(function (
        RichTextStoredImage $stored,
        RichTextImage $image,
    ): RichTextImage {
        $disk = (string) $stored->meta('disk', 'private');
        $url = Storage::disk($disk)->temporaryUrl(
            $stored->identifier(),
            now()->addMinutes(5),
        );

        return $image->src($url);
    })
    ->toHtml();
```

`storeImagesInMediaLibrary()` is the optional Media Library shortcut. These helpers verify that submitted HTML markers and companion tokens match, but they do not sanitize HTML; apply the application's HTML policy before rendering untrusted content.

## Extension points

Create a field subclass and return a stable component discriminator. No presenter registration is required:

```php
use Inertify\Form\Fields\Field;

final class Money extends Field
{
    public function getComponent(): string
    {
        return 'Money';
    }

    public function currency(string $currency): static
    {
        return $this->meta('currency', $currency);
    }
}
```

Render it with the `type-money` slot or build a field-specific controller in the application. Laravel macros are available on `Form` and `Field` for small, shared fluent extensions.

Connect a preferred editor through `useComposer()` or `useRichText()` in the matching type slot. The controllers manage values and upload state; the editor and its markup remain application-owned.

## Serialized resource

The stable top-level resource is:

```json
{
  "action": "/profiles/1",
  "method": "PATCH",
  "fieldsets": [],
  "data": {},
  "dataAttributes": null,
  "meta": null,
  "unsavedWarning": true,
  "scrollToFirstError": true,
  "wizard": null
}
```

Each authorized field serializes its component discriminator, canonical name, content metadata, rules, defaults, semantic state, visibility conditions, data attributes, metadata, and behavior-specific options.

## Workbench

The Testbench application demonstrates create and edit binding, validation, conditional fields, remote comboboxes, nested collections, a wizard, and uploads with app-owned shadcn-vue-style components. Tailwind and shadcn-vue are workbench-only development dependencies; neither ships in the runtime package.

```bash
composer build
npm run workbench:build
composer serve
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## Security

Report security issues through the process in the [GitHub security policy](https://github.com/enkot/inertify-form/blob/main/.github/SECURITY.md).

## License

Inertify Form is open source software licensed under the [MIT license](LICENSE.md).
