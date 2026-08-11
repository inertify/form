<?php

declare(strict_types=1);

namespace Inertify\Form;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Precognition;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Illuminate\Support\Traits\Tappable;
use Illuminate\Validation\Rule;
use Inertia\PropertyContext;
use Inertia\ProvidesInertiaProperty;
use Inertify\Form\Fields\Blocks;
use Inertify\Form\Fields\BlockSet;
use Inertify\Form\Fields\CheckboxGroup;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\Composer;
use Inertify\Form\Fields\DatePicker;
use Inertify\Form\Fields\Field;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\KeyValue;
use Inertify\Form\Fields\Link;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\Slider;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Support\Concerns\Authorizable;
use Inertify\Form\Support\Concerns\HasResourceMetadata;
use Inertify\Form\Support\Value;
use Inertify\Form\Uploads\SubmittedUpload;
use Inertify\Form\Uploads\UploadResolver;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionObject;
use stdClass;

/** @implements Arrayable<string, mixed> */
class Form implements Arrayable, JsonSerializable, ProvidesInertiaProperty
{
    use Authorizable;
    use Conditionable;
    use HasResourceMetadata;
    use Macroable;
    use Tappable;

    protected ?string $configuredRoute = null;

    /** @var array<string, mixed> */
    protected array $configuredRouteParameters = [];

    protected ?string $configuredUrl = null;

    protected bool $usesRawUrl = false;

    protected string $configuredMethod = 'POST';

    protected bool $warnsAboutUnsavedChanges = false;

    protected bool $scrollsToFirstError = false;

    /** @var array<string, mixed> */
    protected array $boundData = [];

    protected ?Model $boundModel = null;

    /** @var array<string, mixed> */
    protected array $explicitData = [];

    protected bool $hasExplicitData = false;

    /** @var list<string> */
    protected array $bindingExceptions = [];

    /** @var list<Fieldset>|null */
    protected ?array $resolvedFieldsets = null;

    protected ?WizardConfig $wizardConfiguration = null;

    protected ?Request $request = null;

    protected ?string $propertyKey = null;

    /** @var array<string, mixed>|null */
    protected ?array $activeValidationInput = null;

    /** @var array<string, mixed>|null */
    protected ?array $validatedData = null;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function make(array $parameters = []): static
    {
        /** @var static */
        $form = Container::getInstance()->make(static::class, $parameters);

        if (Container::getInstance()->bound('request')) {
            $form->setRequest(Container::getInstance()->make('request'));
        }

        return $form;
    }

    /** @return array<Field|Fieldset> */
    public function fields(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function route(string $name, array $parameters = []): static
    {
        $this->configuredRoute = $name;
        $this->configuredRouteParameters = $parameters;
        $this->configuredUrl = null;
        $this->usesRawUrl = false;

        return $this;
    }

    public function url(string $url): static
    {
        $this->configuredUrl = $url;
        $this->configuredRoute = null;
        $this->usesRawUrl = true;

        return $this;
    }

    public function method(string $method): static
    {
        $method = strtoupper($method);

        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new InvalidArgumentException("Unsupported form method [{$method}].");
        }

        $this->configuredMethod = $method;

        return $this;
    }

    public function get(?string $url = null): static
    {
        return $this->verb('GET', $url);
    }

    public function post(?string $url = null): static
    {
        return $this->verb('POST', $url);
    }

    public function put(?string $url = null): static
    {
        return $this->verb('PUT', $url);
    }

    public function patch(?string $url = null): static
    {
        return $this->verb('PATCH', $url);
    }

    public function delete(?string $url = null): static
    {
        return $this->verb('DELETE', $url);
    }

    public function unsavedWarning(bool $enabled = true): static
    {
        $this->warnsAboutUnsavedChanges = $enabled;

        return $this;
    }

    public function scrollToFirstError(bool $enabled = true): static
    {
        $this->scrollsToFirstError = $enabled;

        return $this;
    }

    /**
     * @param  array<string, mixed>|Model  $source
     * @param  list<string>  $except
     */
    public function bind(Model|array $source, array $except = []): static
    {
        $this->boundModel = $source instanceof Model ? $source : null;
        $this->boundData = $source instanceof Model ? [] : $source;
        $this->bindingExceptions = $except;

        if ($this->boundModel === null) {
            Arr::forget($this->boundData, $except);
        }

        return $this;
    }

    /** @param array<string, mixed> $data */
    /** @param array<string, mixed>|null $data
     * @return ($data is null ? array<string, mixed> : static)
     */
    public function data(?array $data = null): static|array
    {
        if ($data === null) {
            return $this->resolvedData();
        }

        $this->explicitData = $data;
        $this->hasExplicitData = true;

        return $this;
    }

    /** @return WizardConfig|array<int, Fieldset|array<string, mixed>|int>|null */
    public function wizard(): WizardConfig|array|null
    {
        return $this->wizardConfiguration;
    }

    public function withWizard(WizardConfig|bool|null $wizard = true): static
    {
        $this->wizardConfiguration = match (true) {
            $wizard instanceof WizardConfig => $wizard,
            $wizard === null => null,
            default => WizardConfig::make()->enabled($wizard),
        };

        return $this;
    }

    public function setRequest(Request $request): static
    {
        $this->request = $request;

        return $this;
    }

    /** @return list<Fieldset> */
    public function getFieldsets(): array
    {
        if ($this->resolvedFieldsets !== null) {
            return $this->resolvedFieldsets;
        }

        $fieldsets = [];
        $pending = [];

        foreach ($this->fields() as $field) {
            if ($field instanceof Fieldset) {
                if (count($pending) > 0) {
                    $fieldsets[] = Fieldset::make($pending);
                    $pending = [];
                }
                $fieldsets[] = $field;
            } else {
                $pending[] = $field;
            }
        }

        if (count($pending) > 0) {
            $fieldsets[] = Fieldset::make($pending);
        }

        return $this->resolvedFieldsets = $fieldsets;
    }

    /** @return array<string, mixed> */
    public function resolvedData(): array
    {
        $data = [];

        foreach ($this->topLevelFields() as $field) {
            $name = $field->getName();

            if ($field instanceof File && $this->boundModel !== null && $field->hasMediaCollection()) {
                data_set($data, $name, $field->resolveMediaCollection($this->boundModel));

                continue;
            }

            if ($field->usesModelBinding()) {
                [$hasBoundValue, $value] = $this->boundValue($name);

                if ($hasBoundValue) {
                    data_set($data, $name, $field instanceof File ? $field->normalizeBoundValue($value) : $value);
                }
            }
        }

        if ($this->hasExplicitData) {
            $data = array_replace_recursive($data, $this->explicitData);
        }

        foreach ($this->topLevelFields() as $field) {
            $name = $field->getName();

            if (! Arr::has($data, $name)) {
                data_set($data, $name, $field->hasDefault() ? $field->resolveDefault($data) : $field->emptyValue());
            }

            data_set($data, $name, $field->normalizeValue(data_get($data, $name)));

            if ($field instanceof Repeater && is_array(data_get($data, $name))) {
                data_set($data, $name, $this->applyRepeaterDefaults($field, data_get($data, $name), $data));
            }

            if ($field instanceof Blocks && is_array(data_get($data, $name))) {
                data_set($data, $name, $this->normalizeBlockRows($field, data_get($data, $name), $data, true));
            }

            if ($field instanceof RichText && $field->hasImageUploads()) {
                $images = $field->getImageUploadFieldName();
                $imageValue = match (true) {
                    Arr::has($this->explicitData, $images) => data_get($this->explicitData, $images),
                    Arr::has($this->boundData, $images) => data_get($this->boundData, $images),
                    default => [],
                };
                data_set($data, $images, $imageValue);
            }
        }

        $filtered = [];
        foreach ($this->topLevelFields() as $field) {
            if (Arr::has($data, $field->getName())) {
                data_set($filtered, $field->getName(), data_get($data, $field->getName()));
            }

            if ($field instanceof RichText && $field->hasImageUploads() && Arr::has($data, $field->getImageUploadFieldName())) {
                data_set($filtered, $field->getImageUploadFieldName(), data_get($data, $field->getImageUploadFieldName()));
            }
        }

        return $filtered;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = [];
        $data = $this->validationInput();

        foreach ($this->getFieldsets() as $fieldset) {
            if (! $fieldset->isAuthorized()) {
                continue;
            }

            foreach ($fieldset->getFields() as $field) {
                $this->collectRules($field, $rules, $data, null, $fieldset->isVisible($data));
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function validate(?array $data = null): array
    {
        if (! $this->isAuthorized()) {
            throw new AuthorizationException;
        }

        if ($data !== null) {
            $this->data($data);
        }

        $input = $data ?? $this->validationInput();
        $this->activeValidationInput = $input;
        $validator = Container::getInstance()->make('validator')->make(
            $input,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        $request = $this->request;

        if ($request !== null && $request->isPrecognitive()) {
            $validator->after(Precognition::afterValidationHook($request))->setRules(
                $request->filterPrecognitiveRules($validator->getRulesWithoutPlaceholders()),
            );
        }

        try {
            $validated = $validator->validate();
            $this->filterValidatedFields($this->getFieldsets(), $validated);

            return $this->validatedData = $validated;
        } finally {
            $this->activeValidationInput = null;
        }
    }

    /** @return array<string, mixed> */
    public function validated(bool $files = true): array
    {
        $validated = $this->validatedData ?? $this->validate();

        $this->transformValidatedFields($this->getFieldsets(), $validated, $files);

        return $validated;
    }

    public function upload(string $name): ?SubmittedUpload
    {
        return $this->uploadResolver()?->one($this->currentRequest(), $name);
    }

    /** @return list<SubmittedUpload> */
    public function uploads(string $name): array
    {
        return array_values($this->uploadResolver()?->ordered($this->currentRequest(), $name)->all() ?? []);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if (! $this->isAuthorized()) {
            if ((bool) config('inertia-forms.authorization.throw_on_unauthorized', false)) {
                throw new AuthorizationException;
            }

            return $this->emptyResource();
        }

        $data = $this->resolvedData();
        $fieldsets = $this->getFieldsets();
        $generatedOptions = $this->prepareInertiaOptions($fieldsets, $data);
        $meta = $this->resourceMeta;

        if ($generatedOptions !== []) {
            $meta ??= [];
            $existingOptions = is_array($meta['options'] ?? null) ? $meta['options'] : [];
            $meta['options'] = [...$existingOptions, ...$generatedOptions];
        }

        $wizard = $this->wizard();

        if (is_array($wizard)) {
            $wizard = WizardConfig::make($wizard);
        }

        return [
            'action' => $this->resolveAction(),
            'method' => $this->resolveMethod(),
            'fieldsets' => array_values(array_map(
                fn (Fieldset $fieldset): array => $fieldset->toArrayFor($data),
                array_filter($fieldsets, fn (Fieldset $fieldset): bool => $fieldset->isAuthorized()),
            )),
            'data' => $data === [] ? new stdClass : Value::normalize($data),
            'dataAttributes' => Value::normalize($this->resourceDataAttributes),
            'meta' => Value::normalize($meta),
            'unsavedWarning' => $this->declaredBoolean('unsavedWarning', $this->warnsAboutUnsavedChanges),
            'scrollToFirstError' => $this->declaredBoolean('scrollToFirstError', $this->scrollsToFirstError),
            'wizard' => $wizard?->toArrayFor($fieldsets, $data),
        ];
    }

    /** @return array<string, mixed> */
    public function toInertiaProperty(PropertyContext $prop): array
    {
        $this->propertyKey = $prop->key;
        $this->setRequest($prop->request);

        return $this->toArray();
    }

    public function getPropertyKey(): ?string
    {
        return $this->propertyKey;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return array<string, mixed> */
    protected function evaluationParameters(): array
    {
        return [
            'form' => $this,
            'request' => $this->request ?? (Container::getInstance()->bound('request')
                ? Container::getInstance()->make('request')
                : null),
        ];
    }

    protected function verb(string $method, ?string $url): static
    {
        $this->method($method);

        return $url === null ? $this : $this->url($url);
    }

    protected function resolveAction(): ?string
    {
        $route = $this->configuredRoute ?? ($this->usesRawUrl ? null : $this->declaredActionRoute());

        if ($route !== null) {
            return Container::getInstance()->make('url')->route($route, $this->configuredRouteParameters);
        }

        return $this->configuredUrl;
    }

    protected function resolveMethod(): string
    {
        $route = $this->configuredRoute ?? ($this->usesRawUrl ? null : $this->declaredActionRoute());

        if ($route === null || ! Container::getInstance()->bound('router')) {
            return $this->configuredMethod;
        }

        /** @var Router $router */
        $router = Container::getInstance()->make('router');
        $namedRoute = $router->getRoutes()->getByName($route);

        if ($namedRoute === null) {
            return $this->configuredMethod;
        }

        foreach ($namedRoute->methods() as $method) {
            if ($method !== 'HEAD') {
                return strtoupper($method);
            }
        }

        return $this->configuredMethod;
    }

    protected function declaredActionRoute(): ?string
    {
        $reflection = new ReflectionObject($this);

        if (! $reflection->hasProperty('actionRoute')) {
            return null;
        }

        $property = $reflection->getProperty('actionRoute');
        $value = $property->getValue($this);

        return is_string($value) ? $value : null;
    }

    /** @return list<Field> */
    protected function topLevelFields(): array
    {
        $fields = [];

        foreach ($this->getFieldsets() as $fieldset) {
            if (! $fieldset->isAuthorized()) {
                continue;
            }

            foreach ($fieldset->getFields() as $field) {
                if ($field instanceof Field && $field->isAuthorized() && ! $field instanceof Submit) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /** @return list<Field> */
    protected function allFields(): array
    {
        $fields = [];

        $walk = function (Field|Fieldset|BlockSet $item) use (&$walk, &$fields): void {
            if ($item instanceof Field) {
                $fields[] = $item;
            }

            if ($item instanceof Repeater || $item instanceof Fieldset || $item instanceof BlockSet) {
                foreach ($item->getFields() as $child) {
                    $walk($child);
                }
            }

            if ($item instanceof Blocks) {
                foreach ($item->getBlockSets() as $block) {
                    $walk($block);
                }
            }
        };

        foreach ($this->getFieldsets() as $fieldset) {
            $walk($fieldset);
        }

        return $fields;
    }

    /**
     * @param  array<string, list<mixed>>  $rules
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $row
     */
    protected function collectRules(
        Field|Fieldset $item,
        array &$rules,
        array $data,
        ?string $prefix = null,
        bool $ancestorVisible = true,
        ?array $row = null,
    ): void {
        if (! $item->isAuthorized()) {
            return;
        }

        if ($item instanceof Fieldset) {
            $visible = $ancestorVisible && $item->isVisible($data, $row);
            foreach ($item->getFields() as $field) {
                $this->collectRules($field, $rules, $data, $prefix, $visible, $row);
            }

            return;
        }

        $name = $this->qualifiedName($prefix, $item->getName());
        $fieldRules = $ancestorVisible ? $item->getRules($data, $row) : ['exclude'];

        if ($fieldRules !== []) {
            $rules[$name] = $fieldRules;
        }

        if ($fieldRules === ['exclude']) {
            return;
        }

        if ($item instanceof Repeater) {
            $rows = data_get($data, $name, []);
            $visible = $ancestorVisible && $item->isVisible($data, $row);
            $rules[$name.'.*'] = ['array'];

            if (is_array($rows) && $rows !== []) {
                foreach ($rows as $index => $repeaterRow) {
                    if (! is_array($repeaterRow)) {
                        continue;
                    }

                    foreach ($item->getFields() as $field) {
                        $this->collectRules($field, $rules, $data, $name.'.'.$index, $visible, $repeaterRow);
                    }
                }
            } else {
                foreach ($item->getFields() as $field) {
                    $this->collectRules($field, $rules, $data, $name.'.*', $visible);
                }
            }
        }

        if ($item instanceof Blocks) {
            $rows = data_get($data, $name, []);
            $rules[$name] ??= ['array'];

            if (! in_array('array', $rules[$name], true)) {
                $rules[$name][] = 'array';
            }
            $rules[$name.'.*'] = ['array'];
            $rules[$name.'.*.type'] = ['required', 'string', Rule::in(array_map(
                fn (BlockSet $set): string => $set->getName(),
                array_filter($item->getBlockSets(), fn (BlockSet $set): bool => $set->isAuthorized()),
            ))];
            $rules[$name.'.*.data'] = ['required', 'array'];

            foreach (is_array($rows) ? $rows : [] as $index => $blockRow) {
                if (! is_array($blockRow)) {
                    continue;
                }

                $type = $blockRow['type'] ?? $blockRow['name'] ?? null;
                foreach ($item->getBlockSets() as $block) {
                    if (! $block->isAuthorized() || $block->getName() !== $type) {
                        continue;
                    }

                    foreach ($block->getFields() as $field) {
                        $blockData = is_array($blockRow['data'] ?? null) ? $blockRow['data'] : [];
                        $this->collectRules(
                            $field,
                            $rules,
                            $data,
                            $name.'.'.$index.'.data',
                            $ancestorVisible && $item->isVisible($data, $row),
                            $blockData,
                        );
                    }
                }
            }
        }

        if ($item instanceof KeyValue && $item->getValueRules() !== []) {
            $rules[$name.'.*'] = $item->getValueRules();
        }

        if ($item instanceof File && $item->isMultiple()) {
            $rules[$name.'.*'] = $item->getItemRules();
        }

        if ($item instanceof Combobox && $item->hasArrayValue()) {
            $rules[$name.'.*'] = $item->getItemRules($data);
        }

        if ($item instanceof CheckboxGroup) {
            $rules[$name.'.*'] = $item->getChoiceRules($data);
        }

        if ($item instanceof Composer && $item->hasAttachments()) {
            $rules[$name.'.text'] = $item->getTextRules();
            $rules[$name.'.attachments'] = ['array'];
            $rules[$name.'.attachments.*'] = $item->getAttachmentField()->getItemRules();
        }

        if ($item instanceof RichText && $item->hasImageUploads() && $item->getImageUploadField() !== null) {
            $images = $this->qualifiedName($prefix, $item->getImageUploadFieldName());
            $rules[$images] = ['array'];
            $rules[$images.'.*'] = $item->getImageUploadField()->getItemRules();
        }

        if ($item instanceof Link && $item->isStructured()) {
            $rules[$name.'.url'] = $item->getUrlRules($data, $row);

            if ($item->includesLabel()) {
                $rules[$name.'.label'] = ['nullable', 'string'];
            }

            if ($item->includesTarget()) {
                $rules[$name.'.target'] = $item->getTargetRules();
            }
        }

        if ($item instanceof Slider && $item->hasRangeValue()) {
            $rules[$name.'.*'] = $item->getItemRules();
        }

        if ($item instanceof DatePicker && $item->hasArrayValue()) {
            $rules[$name.'.*'] = $item->getItemRules();
        }
    }

    protected function qualifiedName(?string $prefix, string $name): string
    {
        if (str_starts_with($name, '$.')) {
            return substr($name, 2);
        }

        return $prefix === null ? $name : $prefix.'.'.$name;
    }

    /** @return array<string, mixed> */
    protected function validationInput(): array
    {
        if ($this->activeValidationInput !== null) {
            return $this->activeValidationInput;
        }

        if ($this->request !== null) {
            return $this->request->all();
        }

        return $this->resolvedData();
    }

    protected function currentRequest(): Request
    {
        return $this->request ??= Container::getInstance()->make('request');
    }

    protected function uploadResolver(): ?UploadResolver
    {
        $container = Container::getInstance();

        if (! $container->bound(UploadResolver::class) && ! $container->has(UploadResolver::class)) {
            return null;
        }

        return $container->make(UploadResolver::class);
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  array<string, mixed>  $root
     * @return array<int|string, mixed>
     */
    protected function applyRepeaterDefaults(Repeater $repeater, array $rows, array $root): array
    {
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[$index] = $this->normalizeNestedRow($repeater->getFields(), $row, $root, false);
        }

        return $rows;
    }

    /**
     * @param  array<Field|Fieldset>  $fields
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>
     */
    protected function normalizeNestedRow(array $fields, array $row, array $root, bool $defaults): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (! $field->isAuthorized()) {
                continue;
            }

            if ($field instanceof Fieldset) {
                $normalized = array_replace_recursive(
                    $normalized,
                    $this->normalizeNestedRow($field->getFields(), $row, $root, $defaults),
                );

                continue;
            }

            $name = $field->getName();

            if (str_starts_with($name, '$.')) {
                continue;
            }

            $images = $field instanceof RichText && $field->hasImageUploads()
                ? $field->getImageUploadFieldName()
                : null;

            if (! Arr::has($row, $name)) {
                if ($defaults && $field->hasDefault()) {
                    data_set($normalized, $name, $field->resolveDefault($root));
                }

                if ($images !== null && Arr::has($row, $images)) {
                    data_set($normalized, $images, data_get($row, $images));
                }

                continue;
            }

            $value = data_get($row, $name);

            if ($field instanceof Repeater && is_array($value)) {
                $value = $defaults
                    ? $this->applyRepeaterDefaults($field, $value, $root)
                    : array_map(
                        fn (mixed $nestedRow): mixed => is_array($nestedRow)
                            ? $this->normalizeNestedRow($field->getFields(), $nestedRow, $root, false)
                            : $nestedRow,
                        $value,
                    );
            }

            if ($field instanceof Blocks && is_array($value)) {
                $value = $this->normalizeBlockRows($field, $value, $root, $defaults);
            }

            data_set($normalized, $name, $defaults ? $field->normalizeValue($value) : $value);

            if ($images !== null && Arr::has($row, $images)) {
                data_set($normalized, $images, data_get($row, $images));
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  array<string, mixed>  $root
     * @return array<int|string, mixed>
     */
    protected function normalizeBlockRows(Blocks $blocks, array $rows, array $root, bool $defaults): array
    {
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! is_string($row['type'] ?? null)) {
                continue;
            }

            foreach ($blocks->getBlockSets() as $set) {
                if (! $set->isAuthorized() || $set->getName() !== $row['type']) {
                    continue;
                }

                $blockData = is_array($row['data'] ?? null) ? $row['data'] : [];

                if ($defaults) {
                    $blockData = array_replace_recursive($set->resolveDefaultData($root), $blockData);
                }
                $normalized[$index] = [
                    'type' => $row['type'],
                    'data' => $this->normalizeNestedRow($set->getFields(), $blockData, $root, $defaults),
                ];
            }

            if (! array_key_exists($index, $normalized)) {
                $normalized[$index] = [
                    'type' => $row['type'],
                    'data' => is_array($row['data'] ?? null) ? $row['data'] : [],
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<Field|Fieldset>  $fields
     * @param  array<string, mixed>  $values
     */
    protected function filterValidatedFields(array $fields, array &$values): void
    {
        $filtered = [];

        foreach ($fields as $field) {
            if (! $field->isAuthorized()) {
                continue;
            }

            if ($field instanceof Fieldset) {
                $fieldsetValues = $values;
                $this->filterValidatedFields($field->getFields(), $fieldsetValues);
                $filtered = array_replace_recursive($filtered, $fieldsetValues);

                continue;
            }

            $name = $field->getName();

            if (! Arr::has($values, $name)) {
                if ($field instanceof RichText && $field->hasImageUploads()) {
                    $images = $field->getImageUploadFieldName();

                    if (Arr::has($values, $images)) {
                        data_set($filtered, $images, data_get($values, $images));
                    }
                }

                continue;
            }

            $value = data_get($values, $name);

            if ($field instanceof Repeater && is_array($value)) {
                $value = array_map(
                    fn (mixed $row): mixed => is_array($row)
                        ? $this->normalizeNestedRow($field->getFields(), $row, $values, false)
                        : $row,
                    $value,
                );
            }

            if ($field instanceof Blocks && is_array($value)) {
                $value = $this->normalizeBlockRows($field, $value, $values, false);
            }

            data_set($filtered, $name, $value);

            if ($field instanceof RichText && $field->hasImageUploads()) {
                $images = $field->getImageUploadFieldName();

                if (Arr::has($values, $images)) {
                    data_set($filtered, $images, data_get($values, $images));
                }
            }
        }

        $values = $filtered;
    }

    /**
     * @param  array<Field|Fieldset>  $fields
     * @param  array<string, mixed>  $values
     */
    protected function transformValidatedFields(array $fields, array &$values, bool $files): void
    {
        foreach ($fields as $field) {
            if ($field instanceof Fieldset) {
                $this->transformValidatedFields($field->getFields(), $values, $files);

                continue;
            }

            $name = $field->getName();

            if (! $files && $field instanceof File) {
                $values = $this->withoutDataPath($values, $name);

                continue;
            }

            if (! $files && $field instanceof RichText && $field->hasImageUploads()) {
                $values = $this->withoutDataPath($values, $field->getImageUploadFieldName());
            }

            if (! Arr::has($values, $name)) {
                continue;
            }

            $value = data_get($values, $name);

            if (! $files && $field instanceof Composer && $field->hasAttachments() && is_array($value)) {
                unset($value['attachments']);
            }

            if ($field instanceof Repeater && is_array($value)) {
                foreach ($value as &$row) {
                    if (is_array($row)) {
                        $this->transformValidatedFields($field->getFields(), $row, $files);
                    }
                }
                unset($row);
            }

            if ($field instanceof Blocks && is_array($value)) {
                foreach ($value as &$blockRow) {
                    if (! is_array($blockRow) || ! is_array($blockRow['data'] ?? null)) {
                        continue;
                    }

                    $type = $blockRow['type'] ?? null;
                    foreach ($field->getBlockSets() as $blockSet) {
                        if ($blockSet->getName() === $type) {
                            $blockData = [];
                            foreach ($blockRow['data'] as $key => $nestedValue) {
                                if (is_string($key)) {
                                    $blockData[$key] = $nestedValue;
                                }
                            }
                            $this->transformValidatedFields($blockSet->getFields(), $blockData, $files);
                            $blockRow['data'] = $blockData;
                        }
                    }
                }
                unset($blockRow);
            }

            data_set($values, $name, $field->transformValidatedValue($value));
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function withoutDataPath(array $values, string $path): array
    {
        [$head, $tail] = array_pad(explode('.', $path, 2), 2, null);

        if ($tail === null) {
            unset($values[$head]);

            return $values;
        }

        if (! is_array($values[$head] ?? null)) {
            return $values;
        }

        $nested = [];
        foreach ($values[$head] as $key => $value) {
            if (is_string($key)) {
                $nested[$key] = $value;
            }
        }
        $values[$head] = $this->withoutDataPath($nested, $tail);

        return $values;
    }

    /**
     * @param  list<Fieldset>  $fieldsets
     * @param  array<string, mixed>  $data
     * @return array<string, array<string, mixed>>
     */
    protected function prepareInertiaOptions(array $fieldsets, array $data): array
    {
        $comboboxes = [];

        $walk = function (Field|Fieldset|BlockSet $item, ?string $prefix = null) use (&$walk, &$comboboxes): void {
            if (! $item->isAuthorized()) {
                return;
            }

            if ($item instanceof Fieldset || $item instanceof BlockSet) {
                foreach ($item->getFields() as $child) {
                    $walk($child, $prefix);
                }

                return;
            }

            $path = str_starts_with($item->getName(), '$.')
                ? substr($item->getName(), 2)
                : ($prefix === null ? $item->getName() : $prefix.'.'.$item->getName());

            if ($item instanceof Combobox) {
                $item->optionsKey($path)->resolvedSelectedOptions(null);
                $comboboxes[$path] = $item;
            }

            if ($item instanceof Repeater) {
                foreach ($item->getFields() as $child) {
                    $walk($child, $path.'.*');
                }
            }

            if ($item instanceof Blocks) {
                foreach ($item->getBlockSets() as $set) {
                    if ($set->isAuthorized()) {
                        $walk($set, $path.'.*.data');
                    }
                }
            }
        };

        foreach ($fieldsets as $fieldset) {
            $walk($fieldset);
        }

        $target = $this->targetedOptionsRequest();
        $generated = [];

        foreach ($comboboxes as $path => $combobox) {
            if (! $combobox->hasInertiaOptions($data)
                || ($target !== null && $target['field'] !== $path)) {
                continue;
            }

            $generated[$path] = $combobox->resolveInertiaOptions(
                $data,
                $target['search'] ?? null,
                $target['page'] ?? 1,
            );
        }

        return $generated;
    }

    /** @return array{field: string, search: string|null, page: int}|null */
    protected function targetedOptionsRequest(): ?array
    {
        if ($this->propertyKey === null || $this->request === null) {
            return null;
        }

        $payload = $this->request->input('_inertify_form');

        if (! is_array($payload)
            || ($payload['prop'] ?? null) !== $this->propertyKey
            || ! is_string($payload['field'] ?? null)) {
            return null;
        }

        $search = $payload['search'] ?? $this->request->input('q', $this->request->input('search'));
        $page = $payload['page'] ?? $this->request->input('page', 1);

        return [
            'field' => $payload['field'],
            'search' => is_string($search) ? $search : null,
            'page' => max(1, is_numeric($page) ? (int) $page : 1),
        ];
    }

    /** @return array{bool, mixed} */
    protected function boundValue(string $path): array
    {
        foreach ($this->bindingExceptions as $exception) {
            if ($path === $exception || str_starts_with($path, $exception.'.')) {
                return [false, null];
            }
        }

        if ($this->boundModel === null) {
            return Arr::has($this->boundData, $path)
                ? [true, data_get($this->boundData, $path)]
                : [false, null];
        }

        $value = $this->boundModel;

        foreach (explode('.', $path) as $segment) {
            if ($value === null) {
                return [true, null];
            }

            if ($value instanceof Model) {
                if (! $value->hasAttribute($segment) && ! $value->relationLoaded($segment) && ! $value->isRelation($segment)) {
                    return [false, null];
                }

                $value = $value->getAttribute($segment);

                continue;
            }

            if (Arr::accessible($value) && Arr::exists($value, $segment)) {
                $value = $value[$segment];

                continue;
            }

            if (is_object($value) && (isset($value->{$segment}) || property_exists($value, $segment))) {
                $value = $value->{$segment};

                continue;
            }

            return [false, null];
        }

        return [true, $value];
    }

    /** @return array<string, mixed> */
    protected function emptyResource(): array
    {
        return [
            'action' => null,
            'method' => 'POST',
            'fieldsets' => [],
            'data' => new stdClass,
            'dataAttributes' => null,
            'meta' => null,
            'unsavedWarning' => false,
            'scrollToFirstError' => false,
            'wizard' => null,
        ];
    }

    protected function declaredBoolean(string $propertyName, bool $fallback): bool
    {
        $reflection = new ReflectionObject($this);

        if (! $reflection->hasProperty($propertyName)) {
            return $fallback;
        }

        return (bool) $reflection->getProperty($propertyName)->getValue($this);
    }
}
