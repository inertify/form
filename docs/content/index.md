---
seo:
  title: Headless forms for Laravel, Inertia, and Vue
  description: Keep form schema, authorization, validation, and uploads in Laravel while your Vue application owns every rendered element.
---

::u-page-hero
---
orientation: horizontal
---
#title
[Laravel]{.text-primary} owns the form. :br Your app owns the [UI.]{.text-primary}

#description
Define schema, authorization, validation, and uploads once in Laravel. :br Render every control with your own Vue components and design system.

#links
  :::u-button
  ---
  color: neutral
  size: xl
  to: /getting-started/installation
  trailing-icon: i-lucide-arrow-right
  ---
  Get started
  :::

  :::u-button
  ---
  color: neutral
  icon: i-simple-icons-github
  size: xl
  target: _blank
  to: https://github.com/inertify/form
  variant: outline
  ---
  View on GitHub
  :::

#headline
  :::div{.flex.flex-col.items-start.gap-4}
    ::::u-button
    ---
    color: neutral
    icon: i-lucide-box
    size: sm
    to: /getting-started/installation
    variant: outline
    ---
    Laravel + Inertia + Vue
    ::::
  :::

#default
  :::hero-form-showcase
  #php
  ```php
  <?php

  namespace App\Forms;

  use Inertify\Form\Fields\Checkbox;
  use Inertify\Form\Fields\Combobox;
  use Inertify\Form\Fields\Fieldset;
  use Inertify\Form\Fields\File;
  use Inertify\Form\Fields\Radio;
  use Inertify\Form\Fields\TextInput;
  use Inertify\Form\Fields\Toggle;
  use Inertify\Form\Form;

  final class ProfileForm extends Form
  {
      public function fields(): array
      {
          return [
              Fieldset::make()
                  ->id('main')
                  ->fields([
                      File::make('avatar', 'Avatar')
                          ->image()
                          ->maxSize(5 * 1024),
                      TextInput::make('name', 'Name')
                          ->required()
                          ->precognitive(),
                      TextInput::make('email', 'Email')
                          ->email()
                          ->required()
                          ->precognitive(),
                      Combobox::make('skill', 'Primary skill')
                          ->source(route('skills.index'))
                          ->searchable()
                          ->preload()
                          ->required(),
                      TextInput::make('company', 'Company')
                          ->visibleWhen('is_employed', true)
                          ->clearWhenHidden(),
                  ]),
              Fieldset::make()
                  ->id('extra')
                  ->fields([
                      Radio::make('work_mode', 'Preferred work mode')
                          ->options([
                              'remote' => 'Remote',
                              'hybrid' => 'Hybrid',
                              'office' => 'Office',
                          ])
                          ->default('remote'),
                      Checkbox::make('is_employed', 'Currently employed')
                          ->default(true),
                      Toggle::make('notifications', 'Project notifications')
                          ->default(true),
                  ]),
          ];
      }
  }
  ```

  #vue
  ```vue
  <script setup lang="ts">
  import {
    Form,
    FormFieldsets,
    FormSubmit,
    type FormResource,
  } from '@inertify/form-vue'
  import { FormField, FormFields } from '@/components/form'
  import Button from '@/components/ui/Button.vue'
  import Card from '@/components/ui/Card.vue'

  defineProps<{ form: FormResource }>()
  </script>

  <template>
    <Form :form="form" v-slot="{ form: api, processing, canSubmit }">
      <Card class="space-y-5">
        <FormField name="avatar" />

        <FormFieldsets v-slot="{ id, fieldset }">
            <section
              :class="id === 'main'
                ? 'grid gap-4 sm:grid-cols-2'
                : 'space-y-4'"
            >
              <FormFields
                :fieldset="fieldset"
                except="avatar"
              />
          </section>
        </FormFieldsets>

        <Button
          type="submit"
          :disabled="!canSubmit || processing"
        >
          {{ processing ? 'Saving…' : 'Save profile' }}
        </Button>
      </Card>
    </Form>
  </template>
  ```
  :::
::

::u-page-section
  :::u-page-grid
    ::::u-page-card
    ---
    spotlight: true
    class: group col-span-2 lg:col-span-1
    to: /guide/forms-data-validation
    ---
      :::::div{.flex.flex-1.items-center.justify-center.py-12}
      :u-icon{name="i-simple-icons-laravel" class="size-24 text-primary"}
      :::::

    #title
    Laravel-first schemas

    #description
    Define fields, rules, authorization, visibility, binding, routes, and upload behavior in container-resolved PHP classes.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2
    to: /getting-started/vue-rendering
    ---
      :::::div{.flex.flex-1.items-center.justify-center.gap-3.py-12.sm:gap-6}
        ::::::div{.flex.size-16.items-center.justify-center.rounded-2xl.border.border-default.bg-default.shadow-sm.sm:size-20}
        :u-icon{name="i-simple-icons-laravel" class="size-8 text-primary sm:size-10"}
        ::::::

      :u-icon{name="i-lucide-arrow-right" class="size-5 text-muted"}

        ::::::div{.flex.size-16.items-center.justify-center.rounded-2xl.border.border-default.bg-default.shadow-sm.sm:size-20}
        :u-icon{name="i-simple-icons-inertia" class="size-8 text-primary sm:size-10"}
        ::::::

      :u-icon{name="i-lucide-arrow-right" class="size-5 text-muted"}

        ::::::div{.flex.size-16.items-center.justify-center.rounded-2xl.border.border-default.bg-default.shadow-sm.sm:size-20}
        :u-icon{name="i-simple-icons-vuedotjs" class="size-8 text-primary sm:size-10"}
        ::::::
      :::::

    #title
    One form contract, three layers

    #description
    Pass a form directly through Inertia, then use the renderless Vue engine to connect server behavior to application-owned controls.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2
    to: /guide/renderers-composables
    ---
    ```ts [resources/js/components/form/index.ts]
    export const { FormFields } = createFormRenderer({
        renderers: {
          Text: TextField,
          Textarea: TextareaField,
          Checkbox: CheckboxField,
          File: FileField,
        },
    })
    ```

    #title
    Behavior without package markup

    #description
    The schema describes what a field does. Your application decides its HTML, components, accessibility, layout, and visual states.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2 lg:col-span-1
    to: /guide/uploads
    ---
      :::::div{.flex.flex-1.items-center.justify-center.py-12}
        ::::::div{.relative.flex.size-28.items-center.justify-center.rounded-full.bg-primary/10}
        :u-icon{name="i-lucide-cloud-upload" class="size-14 text-primary"}

          :::::::div{.absolute.-right-3.-top-2.flex.size-9.items-center.justify-center.rounded-full.border.border-default.bg-default.shadow-sm}
          :u-icon{name="i-lucide-check" class="size-4 text-primary"}
          :::::::
        ::::::
      :::::

    #title
    Uploads for real applications

    #description
    Choose native, temporary, resumable chunked, or direct-to-storage uploads while retaining server-side validation.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2 lg:col-span-1
    to: /guide/collections-wizards
    ---
      :::::div{.flex.flex-1.items-center.justify-center.py-12}
      :u-icon{name="i-lucide-workflow" class="size-24 text-primary"}
      :::::

    #title
    Collections and wizards

    #description
    Build repeaters, typed blocks, and multi-step forms with stable nested paths and application-owned navigation.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2
    to: /guide/fields-visibility
    ---
      :::::div{.flex.flex-1.flex-col.items-center.justify-center.gap-5.py-10.sm:flex-row}
        ::::::div{.flex.items-center.gap-3.rounded-xl.border.border-default.bg-default.px-5.py-4.shadow-sm}
        :u-icon{name="i-lucide-list-tree" class="size-6 text-primary"}
        Schema
        ::::::

      :u-icon{name="i-lucide-equal" class="hidden size-5 text-muted sm:block"}

        ::::::div{.flex.items-center.gap-3.rounded-xl.border.border-default.bg-default.px-5.py-4.shadow-sm}
        :u-icon{name="i-lucide-database" class="size-6 text-primary"}
        Initial data
        ::::::

      :u-icon{name="i-lucide-equal" class="hidden size-5 text-muted sm:block"}

        ::::::div{.flex.items-center.gap-3.rounded-xl.border.border-default.bg-default.px-5.py-4.shadow-sm}
        :u-icon{name="i-lucide-shield-check" class="size-6 text-primary"}
        Validation
        ::::::
      :::::

    #title
    Authorization stays structural

    #description
    Unauthorized fields disappear from schema, initial data, and validation rules together instead of relying on client-side hiding.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2
    to: /getting-started/installation
    ---
    ```bash [Terminal]
    composer require inertify/form
    npm install @inertify/form-vue
    ```

    #title
    Two packages, one form contract

    #description
    Install the Laravel package for schema and validation, then add the Vue package for client-side state and renderless behavior.
    ::::

    ::::u-page-card
    ---
    spotlight: true
    class: col-span-2 lg:col-span-1
    ---
      :::::div{.flex.flex-1.flex-col.items-center.justify-center.py-8.text-center}
        ::::::div{.flex.w-full.max-w-xs.flex-col.gap-3}
          :::::::u-button
          ---
          block: true
          color: primary
          size: lg
          to: /getting-started/first-form
          trailing-icon: i-lucide-arrow-right
          ---
          Build your first form
          :::::::

          :::::::u-button
          ---
          block: true
          color: neutral
          icon: i-lucide-braces
          size: lg
          to: /reference/fields
          variant: outline
          ---
          Browse the reference
          :::::::
        ::::::
      :::::

    #title
    [Ready]{.text-primary} to build?

    #description
    Start with a small form, then add only the fields, controllers, and upload strategies your application needs.
    ::::
  :::
::
