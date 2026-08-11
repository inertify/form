<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertify\Form\Support\Rules\QueryOptionValue;
use Inertify\Form\Support\Value;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Traversable;

class Combobox extends Field
{
    /** @var array<string, Closure> */
    protected array $callbacks = [];

    protected mixed $choiceSource = [];

    protected string|Closure $labelMapping = 'label';

    protected string|Closure $valueMapping = 'value';

    protected string|Closure|null $descriptionMapping = 'description';

    protected string|Closure|null $imageMapping = 'image';

    protected string|Closure|null $avatarMapping = 'avatar';

    protected string|Closure|null $badgeMapping = 'badge';

    protected string|Closure|null $urlMapping = 'url';

    protected string|Closure|null $metadataMapping = 'metadata';

    protected string|Closure|null $selectedSuffixMapping = 'selectedSuffix';

    protected string|Closure|bool|null $groupMapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $labelRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $valueRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $descriptionRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $imageRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $avatarRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $badgeRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $urlRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $metadataRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $disabledRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $disabledReasonRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $selectedSuffixRemapping = null;

    protected string|Closure|null $disabledMapping = 'disabled';

    protected string|Closure|null $disabledReasonMapping = 'disabledReason';

    protected ?Closure $optionRemapping = null;

    /** @var array<int|string, mixed>|Closure|null */
    protected array|Closure|null $groupRemapping = null;

    protected ?string $existsSource = null;

    protected ?string $existsColumn = null;

    protected bool $usesRuleIn = true;

    protected ?string $inertiaOptionsKey = null;

    /** @var list<array<string, mixed>>|null */
    protected ?array $resolvedSelectedOptions = null;

    protected mixed $selectedRows = null;

    protected bool $hasSelectedRows = false;

    public function options(
        mixed $source,
        string|Closure $label = 'label',
        string|Closure $value = 'value',
        string|Closure|null $description = 'description',
    ): static {
        $this->choiceSource = $source;
        $this->labelMapping = $label;
        $this->valueMapping = $value;
        $this->descriptionMapping = $description;

        return $this->option('optionMapping', [
            'label' => $label instanceof Closure ? null : $label,
            'value' => $value instanceof Closure ? null : $value,
            'description' => $description instanceof Closure ? null : $description,
        ]);
    }

    public function groupBy(string|Closure|null $group = null): static
    {
        $this->groupMapping = $group ?? true;

        return $this->option('groupBy', $group instanceof Closure ? true : ($group ?? true));
    }

    /** @param array<string, string>|Closure $mapping */
    public function mapGroupAs(array|Closure $mapping): static
    {
        $this->groupRemapping = $mapping;

        return $this->option('mapGroupAs', $mapping instanceof Closure ? true : $mapping);
    }

    public function perPage(int $perPage): static
    {
        return $this->option('perPage', $perPage);
    }

    public function searchable(bool $searchable = true): static
    {
        return $this->option('searchable', $searchable);
    }

    public function clearable(bool $clearable = true): static
    {
        return $this->option('clearable', $clearable);
    }

    public function multiple(bool $multiple = true): static
    {
        $this->managedRule('multiple', $multiple ? 'array' : null);

        return $this->option('multiple', $multiple);
    }

    public function maxSelected(?int $maximum): static
    {
        $this->managedRule('maxSelected', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxSelected', $maximum);
    }

    public function minSelected(?int $minimum): static
    {
        $this->managedRule('minSelected', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minSelected', $minimum);
    }

    public function allowCustomValues(bool $allow = true): static
    {
        return $this->option('allowCustomValues', $allow);
    }

    public function customValueText(?string $text): static
    {
        return $this->option('customValueText', $text);
    }

    public function tokens(bool $tokens = true): static
    {
        $this->managedRule('tokens', $tokens ? 'array' : null);

        return $this->option('tokens', $tokens);
    }

    public function delimiter(string $delimiter): static
    {
        return $this->option('delimiter', $delimiter);
    }

    public function allowDuplicates(bool $allow = true): static
    {
        return $this->option('allowDuplicates', $allow);
    }

    public function caseSensitive(bool $caseSensitive = true): static
    {
        return $this->option('caseSensitive', $caseSensitive);
    }

    public function pattern(string $pattern): static
    {
        return $this->option('pattern', $pattern);
    }

    public function maxLength(?int $length): static
    {
        return $this->option('maxLength', $length);
    }

    public function createOnBlur(bool $create = true): static
    {
        return $this->option('createOnBlur', $create);
    }

    public function reorderable(bool $reorderable = true): static
    {
        return $this->option('reorderable', $reorderable);
    }

    public function records(bool $records = true): static
    {
        return $this->option('records', $records);
    }

    /** @param array<mixed>|mixed $selected */
    public function selected(mixed $selected): static
    {
        $this->selectedRows = $selected;
        $this->hasSelectedRows = true;

        return $this;
    }

    public function exists(string $tableOrModel, ?string $column = null): static
    {
        $this->existsSource = $tableOrModel;
        $this->existsColumn = $column;

        return $this->option('exists', [
            'source' => $tableOrModel,
            'column' => $column,
        ]);
    }

    public function distinct(bool $distinct = true): static
    {
        return $this->option('distinct', $distinct);
    }

    /** @param array<string, mixed> $params */
    public function source(string $source, array $params = []): static
    {
        $this->option('source', $source);

        return $params === [] ? $this : $this->params($params);
    }

    public function selectedSource(string $source): static
    {
        return $this->option('selectedSource', $source);
    }

    public function searchOptionsUsing(Closure $callback): static
    {
        $this->callbacks['searchOptionsUsing'] = $callback;

        return $this->option('searchOptionsUsing', true);
    }

    /** @param array<string, mixed>|Closure $params */
    public function params(array|Closure $params): static
    {
        return $this->option('params', $params);
    }

    /** @param array<string, mixed>|Closure $filters */
    public function filters(array|Closure $filters): static
    {
        return $this->option('filters', $filters);
    }

    /** @param array<string, mixed>|list<string> $scopes */
    public function scopes(array $scopes): static
    {
        return $this->option('scopes', $scopes);
    }

    public function searchParam(string $parameter): static
    {
        return $this->option('searchParam', $parameter);
    }

    public function valuesParam(string $parameter): static
    {
        return $this->option('valuesParam', $parameter);
    }

    public function pageParam(string $parameter): static
    {
        return $this->option('pageParam', $parameter);
    }

    public function perPageParam(string $parameter): static
    {
        return $this->option('perPageParam', $parameter);
    }

    public function minSearchLength(int $length): static
    {
        return $this->option('minSearchLength', $length);
    }

    public function debounce(int $milliseconds): static
    {
        return $this->option('debounce', $milliseconds);
    }

    public function preload(bool|int $preload = true): static
    {
        return $this->option('preload', $preload);
    }

    public function emptyText(?string $text): static
    {
        return $this->option('emptyText', $text);
    }

    public function noResultsText(?string $text): static
    {
        return $this->option('noResultsText', $text);
    }

    public function loadingText(?string $text): static
    {
        return $this->option('loadingText', $text);
    }

    public function errorText(?string $text): static
    {
        return $this->option('errorText', $text);
    }

    public function maxItemsText(?string $text): static
    {
        return $this->option('maxItemsText', $text);
    }

    public function createRecordUsing(string|Closure $url, string $method = 'post', string $param = 'name'): static
    {
        if ($url instanceof Closure) {
            $this->callbacks['createRecordUsing'] = $url;

            return $this->option('createRecordUsing', true);
        }

        return $this->option('createRecordUsing', [
            'url' => $url,
            'method' => strtoupper($method),
            'param' => $param,
        ]);
    }

    public function createRecordText(?string $text): static
    {
        return $this->option('createRecordText', $text);
    }

    public function canCreateRecord(bool|Closure $condition = true): static
    {
        return $this->option('canCreateRecord', $condition);
    }

    public function ruleIn(bool $rule = true): static
    {
        $this->usesRuleIn = $rule;

        return $this->option('ruleIn', $rule);
    }

    public function optionLabel(string|Closure $mapping): static
    {
        $this->labelMapping = $mapping;

        return $this;
    }

    public function optionValue(string|Closure $mapping): static
    {
        $this->valueMapping = $mapping;

        return $this;
    }

    public function optionDescription(string|Closure|null $mapping): static
    {
        $this->descriptionMapping = $mapping;

        return $this;
    }

    public function optionImage(string|Closure|null $mapping): static
    {
        $this->imageMapping = $mapping;

        return $this;
    }

    public function optionAvatar(string|Closure|null $mapping): static
    {
        $this->avatarMapping = $mapping;

        return $this;
    }

    public function optionBadge(string|Closure|null $mapping): static
    {
        $this->badgeMapping = $mapping;

        return $this;
    }

    public function optionUrl(string|Closure|null $mapping): static
    {
        $this->urlMapping = $mapping;

        return $this;
    }

    public function optionMetadata(string|Closure|null $mapping): static
    {
        $this->metadataMapping = $mapping;

        return $this;
    }

    public function optionDisabled(string|Closure|null $mapping): static
    {
        $this->disabledMapping = $mapping;

        return $this;
    }

    public function optionDisabledReason(string|Closure|null $mapping): static
    {
        $this->disabledReasonMapping = $mapping;

        return $this;
    }

    public function optionSelectedSuffix(string|Closure|null $mapping): static
    {
        $this->selectedSuffixMapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapAs(array|Closure $mapping): static
    {
        $this->labelRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapValueAs(array|Closure $mapping): static
    {
        $this->valueRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapDescriptionAs(array|Closure $mapping): static
    {
        $this->descriptionRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapImageAs(array|Closure $mapping): static
    {
        $this->imageRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapAvatarAs(array|Closure $mapping): static
    {
        $this->avatarRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapBadgeAs(array|Closure $mapping): static
    {
        $this->badgeRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapUrlAs(array|Closure $mapping): static
    {
        $this->urlRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapMetadataAs(array|Closure $mapping): static
    {
        $this->metadataRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapDisabledAs(array|Closure $mapping): static
    {
        $this->disabledRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapDisabledReasonAs(array|Closure $mapping): static
    {
        $this->disabledReasonRemapping = $mapping;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $mapping */
    public function mapSelectedSuffixAs(array|Closure $mapping): static
    {
        $this->selectedSuffixRemapping = $mapping;

        return $this;
    }

    public function mapOptionAs(Closure $mapping): static
    {
        $this->optionRemapping = $mapping;

        return $this;
    }

    public function getCallback(string $name): ?Closure
    {
        return $this->callbacks[$name] ?? null;
    }

    public function optionsKey(string $key): static
    {
        $this->inertiaOptionsKey = $key;

        return $this;
    }

    /** @param list<array<string, mixed>>|null $options */
    public function resolvedSelectedOptions(?array $options): static
    {
        $this->resolvedSelectedOptions = $options;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function hasInertiaOptions(array $data = []): bool
    {
        $source = $this->resolveChoiceSource($data);

        return ($source instanceof EloquentBuilder || $source instanceof QueryBuilder)
            && ($this->hasSearchOptionsCallback() || ! $this->usesFiniteQueryResolution());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{data: list<array<string, mixed>>, current_page: int, per_page: int, total: int, last_page: int, next_page_url: string|null}
     */
    public function resolveInertiaOptions(array $data, ?string $search = null, int $page = 1): array
    {
        $source = $this->resolveChoiceSource($data);

        if (! $source instanceof EloquentBuilder && ! $source instanceof QueryBuilder) {
            return [
                'data' => [],
                'current_page' => 1,
                'per_page' => (int) ($this->options['perPage'] ?? 25),
                'total' => 0,
                'last_page' => 1,
                'next_page_url' => null,
            ];
        }

        $query = clone $source;

        if ($this->hasSearchOptionsCallback()) {
            return $this->resolveSearchedOptions($query, $data, $search);
        }

        if ($search !== null && $search !== '' && is_string($this->labelMapping) && ! str_contains($this->labelMapping, '.')) {
            $query->where($this->labelMapping, 'like', '%'.$search.'%');
        }

        $perPage = max(1, (int) ($this->options['perPage'] ?? 25));
        $paginator = $query->paginate($perPage, ['*'], 'page', max(1, $page));
        $records = $paginator->getCollection()->all();
        $mapped = [];

        foreach ($records as $key => $record) {
            $mapped[] = $this->mapOption($record, $key);
        }

        $this->hydrateSelectedOptions($source, $data);

        return [
            'data' => $mapped,
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $query
     * @param  array<string, mixed>  $data
     * @return array{data: list<array<string, mixed>>, current_page: int, per_page: int, total: int, last_page: int, next_page_url: null}
     */
    protected function resolveSearchedOptions(EloquentBuilder|QueryBuilder $query, array $data, ?string $search): array
    {
        $this->hydrateSelectedOptions($query, $data);

        if ($search === null || $search === '') {
            return $this->singlePageOptions([]);
        }

        $callback = $this->getCallback('searchOptionsUsing');
        $result = $callback === null ? null : $this->evaluateOptionCallback(
            $callback,
            [
                'query' => $query,
                'builder' => $query,
                'search' => $search,
                'q' => $search,
                'data' => $data,
                'field' => $this,
                $query::class => $query,
            ],
            [$query, $search],
        );

        if ($result instanceof EloquentBuilder || $result instanceof QueryBuilder) {
            $records = $result->get();
        } elseif ($result === null) {
            $records = $query->get();
        } else {
            $records = $result;
        }

        return $this->singlePageOptions($this->mapResolvedOptions($records));
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array{data: list<array<string, mixed>>, current_page: int, per_page: int, total: int, last_page: int, next_page_url: null}
     */
    protected function singlePageOptions(array $options): array
    {
        $total = count($options);

        return [
            'data' => $options,
            'current_page' => 1,
            'per_page' => max(1, $total),
            'total' => $total,
            'last_page' => 1,
            'next_page_url' => null,
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function mapResolvedOptions(mixed $source): array
    {
        if ($source instanceof LengthAwarePaginator) {
            $source = $source->getCollection();
        }

        if ($source instanceof Traversable) {
            $source = iterator_to_array($source, false);
        } elseif ($source instanceof Arrayable) {
            $source = $source->toArray();
        }

        if (! is_array($source)) {
            return [];
        }

        if (! array_is_list($source) && (array_key_exists('value', $source) || array_key_exists('label', $source))) {
            $source = [$source];
        }

        $mapped = [];
        $isList = array_is_list($source);
        foreach ($source as $key => $record) {
            if (is_array($record) && array_key_exists('value', $record) && array_key_exists('label', $record)) {
                $mapped[] = Value::normalize($record);

                continue;
            }

            if ($this->groupMapping === true && is_array($record)) {
                foreach ($record as $nestedKey => $nestedRecord) {
                    $mapped[] = $this->mapOption($nestedRecord, $nestedKey, array_is_list($record), (string) $key);
                }
            } else {
                $mapped[] = $this->mapOption($record, $key, $isList);
            }
        }

        return $mapped;
    }

    public function emptyValue(): mixed
    {
        return ($this->options['multiple'] ?? false) || ($this->options['tokens'] ?? false) ? [] : null;
    }

    public function hasArrayValue(): bool
    {
        return (bool) ($this->options['multiple'] ?? false) || (bool) ($this->options['tokens'] ?? false);
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude'] || $this->hasArrayValue()) {
            return $rules;
        }

        return [...$rules, ...$this->getItemRules($data)];
    }

    /** @param array<string, mixed> $data
     * @return list<mixed>
     */
    public function getItemRules(array $data = []): array
    {
        $rules = [];

        if ($this->existsSource !== null) {
            $source = $this->existsSource;
            $column = $this->existsColumn;

            if (is_subclass_of($source, Model::class)) {
                $model = new $source;
                $source = $model->getTable();
                $column ??= $model->getKeyName();
            }

            $rules[] = Rule::exists($source, $column ?? 'id');
        }

        if ((bool) ($this->options['distinct'] ?? false)
            || ((bool) ($this->options['records'] ?? false) && $this->hasArrayValue() && ! array_key_exists('distinct', $this->options))) {
            $rules[] = 'distinct';
        }

        if ((bool) ($this->options['tokens'] ?? false)) {
            if (($length = $this->options['maxLength'] ?? null) !== null) {
                $rules[] = 'max:'.$length;
            }

            if (is_string($pattern = $this->options['pattern'] ?? null)) {
                $rules[] = str_starts_with($pattern, 'regex:') ? $pattern : 'regex:/'.$pattern.'/';
            }
        }

        $allowCustom = (bool) ($this->options['allowCustomValues'] ?? ((bool) ($this->options['tokens'] ?? false)));
        $values = $this->finiteValues($data);

        if ($this->usesRuleIn && ! $allowCustom && ! $this->hasSearchOptionsCallback() && $values !== null) {
            $rules[] = Rule::in($values);
        }

        $source = $this->resolveChoiceSource($data);

        if ($this->usesRuleIn
            && ! $allowCustom
            && ! $this->hasSearchOptionsCallback()
            && ($source instanceof EloquentBuilder || $source instanceof QueryBuilder)
            && is_string($this->valueMapping)
            && ! str_contains($this->valueMapping, '.')) {
            $rules[] = new QueryOptionValue($source, $this->valueMapping);
        }

        return $rules;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function serializedOptions(array $data): array
    {
        $options = parent::serializedOptions($data);

        if ($this->hasSelectedRows) {
            $value = data_get($data, $this->inertiaOptionsKey ?? $this->getName());

            if (str_contains($this->inertiaOptionsKey ?? $this->getName(), '*') && is_array($value)) {
                $value = Arr::flatten($value);
            }

            $options['selected'] = Value::normalize(Value::resolve($this->selectedRows, [
                'value' => $value,
                'data' => $data,
                'field' => $this,
            ]));
        }
        $source = $this->resolveChoiceSource($data);

        if (($source instanceof EloquentBuilder || $source instanceof QueryBuilder)
            && ($this->hasSearchOptionsCallback() || ! $this->usesFiniteQueryResolution())) {
            return [
                ...$options,
                'options' => [],
                'optionsKey' => $this->inertiaOptionsKey ?? $this->getName(),
                'optionsMode' => 'inertia',
                ...($this->resolvedSelectedOptions === null || array_key_exists('selected', $options)
                    ? []
                    : ['selected' => $this->resolvedSelectedOptions]),
            ];
        }

        $perPage = (int) ($this->options['perPage'] ?? 25);
        $pagination = null;

        if ($source instanceof EloquentBuilder || $source instanceof QueryBuilder) {
            $source = $source->get();
        }

        if ($source instanceof LengthAwarePaginator) {
            $pagination = [
                'currentPage' => $source->currentPage(),
                'lastPage' => $source->lastPage(),
                'perPage' => $source->perPage(),
                'total' => $source->total(),
            ];
            $source = $source->getCollection();
        }

        if ($source instanceof Traversable) {
            $source = iterator_to_array($source, false);
        } elseif ($source instanceof Arrayable) {
            $source = $source->toArray();
        }

        if (is_array($source)) {
            $mapped = [];
            $isList = array_is_list($source);

            foreach ($source as $key => $record) {
                if ($this->groupMapping === true && is_array($record)) {
                    foreach ($record as $nestedKey => $nestedRecord) {
                        $mapped[] = $this->mapOption($nestedRecord, $nestedKey, array_is_list($record), (string) $key);
                    }
                } else {
                    $mapped[] = $this->mapOption($record, $key, $isList);
                }
            }

            $options['options'] = $mapped;
        } elseif ($source !== null) {
            $options['options'] = Value::normalize($source);
        } else {
            $options['options'] = [];
        }

        if ($pagination !== null) {
            $options['pagination'] = $pagination;
        }

        return $options;
    }

    /** @param array<string, mixed> $data */
    protected function resolveChoiceSource(array $data): mixed
    {
        $source = Value::resolve($this->choiceSource, [
            'data' => $data,
            'field' => $this,
        ]);

        if (is_string($source) && is_subclass_of($source, Model::class)) {
            $source = $source::query();

            if (is_string($this->labelMapping) && ! str_contains($this->labelMapping, '.')) {
                $source->orderBy($this->labelMapping);
            }
        }

        if ($source instanceof EloquentBuilder) {
            $relations = [];
            foreach ([
                $this->labelMapping,
                $this->valueMapping,
                $this->descriptionMapping,
                $this->groupMapping,
                $this->imageMapping,
                $this->avatarMapping,
                $this->badgeMapping,
                $this->urlMapping,
                $this->metadataMapping,
                $this->disabledMapping,
                $this->disabledReasonMapping,
                $this->selectedSuffixMapping,
            ] as $mapping) {
                if (is_string($mapping) && str_contains($mapping, '.')) {
                    $relations[] = str($mapping)->beforeLast('.')->toString();
                }
            }

            if ($relations !== []) {
                $source->with(array_values(array_unique($relations)));
            }
        }

        return $source;
    }

    protected function hasSearchOptionsCallback(): bool
    {
        return $this->getCallback('searchOptionsUsing') !== null;
    }

    protected function usesFiniteQueryResolution(): bool
    {
        return $this->groupMapping !== null
            || $this->labelMapping instanceof Closure
            || $this->valueMapping instanceof Closure
            || $this->descriptionMapping instanceof Closure
            || $this->valueRemapping instanceof Closure;
    }

    /**
     * @param  EloquentBuilder<Model>|QueryBuilder  $source
     * @param  array<string, mixed>  $data
     */
    protected function hydrateSelectedOptions(EloquentBuilder|QueryBuilder $source, array $data): void
    {
        if ($this->hasSelectedRows
            || ! is_string($this->valueMapping)
            || str_contains($this->valueMapping, '.')) {
            return;
        }

        $value = data_get($data, $this->inertiaOptionsKey ?? $this->getName());
        $values = array_values(array_filter(
            is_array($value) ? Arr::flatten($value) : Arr::wrap($value),
            static fn (mixed $selected): bool => is_scalar($selected) && $selected !== '',
        ));

        if ($values === []) {
            $this->resolvedSelectedOptions = [];

            return;
        }

        $records = (clone $source)->whereIn($this->valueMapping, $values)->get()->all();
        $mapped = [];
        foreach ($records as $key => $record) {
            $mapped[] = $this->mapOption($record, $key);
        }

        $positions = array_flip(array_map(static fn (mixed $item): string => (string) $item, $values));
        usort($mapped, static fn (array $left, array $right): int => ($positions[(string) ($left['value'] ?? '')] ?? PHP_INT_MAX)
            <=> ($positions[(string) ($right['value'] ?? '')] ?? PHP_INT_MAX));

        $this->resolvedSelectedOptions = $mapped;
    }

    /** @return array<string, mixed> */
    protected function mapOption(mixed $record, int|string|null $sourceKey = null, bool $sourceIsList = true, ?string $explicitGroup = null): array
    {
        $parameters = [
            'record' => $record,
            'row' => $record,
            'model' => $record,
            'source' => $record,
            'option' => $record,
            'field' => $this,
            'key' => $sourceKey,
            'sourceKey' => $sourceKey,
        ];

        if (is_object($record)) {
            $parameters[$record::class] = $record;
        }

        $read = fn (mixed $mapping) => $mapping instanceof Closure
            ? $this->evaluateOptionCallback($mapping, $parameters, [$record, $sourceKey])
            : data_get($record, (string) $mapping);

        $isScalar = is_scalar($record) || $record === null;
        $resolvedLabel = $isScalar ? $record : $read($this->labelMapping);
        $resolvedValue = $isScalar ? ($sourceIsList ? $record : $sourceKey) : $read($this->valueMapping);
        $resolvedDescription = $this->descriptionMapping === null ? null : $read($this->descriptionMapping);

        $option = [
            'label' => Value::normalize($this->remap($resolvedLabel, $this->labelRemapping, $parameters)),
            'value' => Value::normalize($this->remap($resolvedValue, $this->valueRemapping, $parameters)),
            'description' => $this->descriptionMapping === null
                ? null
                : Value::normalize($this->remap($resolvedDescription, $this->descriptionRemapping, $parameters)),
        ];

        foreach ([
            'image' => [$this->imageMapping, $this->imageRemapping],
            'avatar' => [$this->avatarMapping, $this->avatarRemapping],
            'badge' => [$this->badgeMapping, $this->badgeRemapping],
            'url' => [$this->urlMapping, $this->urlRemapping],
            'metadata' => [$this->metadataMapping, $this->metadataRemapping],
            'selectedSuffix' => [$this->selectedSuffixMapping, $this->selectedSuffixRemapping],
        ] as $name => [$mapping, $remapping]) {
            if ($mapping === null) {
                continue;
            }

            $resolved = $read($mapping);

            if ($resolved !== null) {
                $option[$name] = Value::normalize($this->remap($resolved, $remapping, $parameters));
            }
        }

        if ($explicitGroup !== null || ($this->groupMapping !== null && $this->groupMapping !== true)) {
            $group = $explicitGroup ?? $read($this->groupMapping);
            $option['group'] = Value::normalize($this->remap($group, $this->groupRemapping, $parameters));
        }

        if (! $isScalar && $this->disabledMapping !== null) {
            $option['disabled'] = (bool) $this->remap(
                $read($this->disabledMapping),
                $this->disabledRemapping,
                $parameters,
            );
        }

        if (! $isScalar && $this->disabledReasonMapping !== null) {
            $option['disabledReason'] = Value::normalize($this->remap(
                $read($this->disabledReasonMapping),
                $this->disabledReasonRemapping,
                $parameters,
            ));
        }

        if ((bool) ($this->options['records'] ?? false)) {
            $option['record'] = Value::normalize($record);
        }

        $remapped = $this->optionRemapping === null
            ? $option
            : $this->evaluateOptionCallback(
                $this->optionRemapping,
                [...$parameters, 'option' => $option, 'normalizedOption' => $option],
                [$option, $record, $sourceKey],
            );

        return is_array($remapped) ? $remapped : $option;
    }

    /** @param array<int|string, mixed>|Closure|null $mapping
     * @param  array<string, mixed>  $parameters
     */
    protected function remap(mixed $value, array|Closure|null $mapping, array $parameters): mixed
    {
        if ($mapping instanceof Closure) {
            return $this->evaluateOptionCallback(
                $mapping,
                [...$parameters, 'value' => $value, 'resolvedValue' => $value],
                [$parameters['record'] ?? null, $parameters['key'] ?? null],
            );
        }

        return is_array($mapping) && (is_int($value) || is_string($value))
            ? ($mapping[$value] ?? $value)
            : $value;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<mixed>  $positional
     */
    protected function evaluateOptionCallback(Closure $callback, array $parameters, array $positional): mixed
    {
        $reflection = new ReflectionFunction($callback);

        foreach ($reflection->getParameters() as $index => $parameter) {
            if (array_key_exists($parameter->getName(), $parameters) || $parameter->isVariadic()) {
                continue;
            }

            $type = $parameter->getType();

            if ($type !== null && $this->hasNonBuiltinType($type)) {
                foreach ($positional as $candidate) {
                    if ($this->acceptsCallbackValue($type, $candidate)) {
                        $parameters[$parameter->getName()] = $candidate;

                        continue 2;
                    }
                }

                continue;
            }

            if (! array_key_exists($index, $positional)) {
                continue;
            }

            $candidate = $positional[$index];

            if ($this->acceptsCallbackValue($type, $candidate)) {
                $parameters[$parameter->getName()] = $candidate;
            }
        }

        return Value::resolve($callback, $parameters);
    }

    protected function acceptsCallbackValue(?ReflectionType $type, mixed $value): bool
    {
        if ($type === null) {
            return true;
        }

        if ($type instanceof ReflectionUnionType) {
            return collect($type->getTypes())->contains(
                fn (ReflectionType $member): bool => $this->acceptsCallbackValue($member, $value),
            );
        }

        if ($type instanceof ReflectionIntersectionType) {
            return collect($type->getTypes())->every(
                fn (ReflectionType $member): bool => $this->acceptsCallbackValue($member, $value),
            );
        }

        if (! $type instanceof ReflectionNamedType) {
            return false;
        }

        if (! $type->isBuiltin()) {
            return is_object($value) && is_a($value, $type->getName());
        }

        return $this->acceptsBuiltInValue($type, $value);
    }

    protected function hasNonBuiltinType(ReflectionType $type): bool
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            return collect($type->getTypes())->contains(
                fn (ReflectionType $member): bool => $this->hasNonBuiltinType($member),
            );
        }

        return $type instanceof ReflectionNamedType && ! $type->isBuiltin();
    }

    protected function acceptsBuiltInValue(ReflectionNamedType $type, mixed $value): bool
    {
        if (! $type->isBuiltin()) {
            return false;
        }

        if ($value === null) {
            return $type->allowsNull() || $type->getName() === 'mixed' || $type->getName() === 'null';
        }

        return match ($type->getName()) {
            'array' => is_array($value),
            'bool' => is_bool($value),
            'callable' => is_callable($value),
            'false' => $value === false,
            'float' => is_float($value),
            'int' => is_int($value),
            'iterable' => is_iterable($value),
            'mixed' => true,
            'null' => false,
            'object' => is_object($value),
            'scalar' => is_scalar($value),
            'string' => is_string($value),
            'true' => $value === true,
            default => false,
        };
    }

    /** @param array<string, mixed> $data
     * @return list<mixed>|null
     */
    protected function finiteValues(array $data): ?array
    {
        $source = Value::resolve($this->choiceSource, ['data' => $data, 'field' => $this]);

        if ($source instanceof Traversable && ! $source instanceof EloquentBuilder && ! $source instanceof QueryBuilder) {
            $source = iterator_to_array($source);
        } elseif ($source instanceof Arrayable) {
            $source = $source->toArray();
        }

        if (! is_array($source)) {
            return null;
        }

        $values = [];
        $isList = array_is_list($source);
        foreach ($source as $key => $record) {
            if ($this->groupMapping === true && is_array($record)) {
                foreach ($record as $nestedKey => $nestedRecord) {
                    $values[] = $this->mapOption($nestedRecord, $nestedKey, array_is_list($record))['value'] ?? null;
                }
            } else {
                $values[] = $this->mapOption($record, $key, $isList)['value'] ?? null;
            }
        }

        return array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
    }
}
