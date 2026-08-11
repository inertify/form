<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\PropertyContext;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Form;

class ComboboxParityOption extends Model
{
    public $timestamps = false;

    protected $table = 'combobox_parity_options';

    protected $guarded = [];
}

class ComboboxParityForm extends Form
{
    public function fields(): array
    {
        return [
            Combobox::make('option_id')
                ->options(ComboboxParityOption::query()->orderBy('id'), 'name', 'id')
                ->searchOptionsUsing(
                    fn (Builder $availableOptions, string $queryText) => $availableOptions
                        ->where('search_code', $queryText)
                        ->get(),
                ),
        ];
    }
}

beforeEach(function () {
    Schema::dropIfExists('combobox_parity_options');
    Schema::create('combobox_parity_options', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('search_code');
        $table->string('image');
        $table->string('avatar');
        $table->string('badge');
        $table->string('url');
        $table->string('suffix');
        $table->boolean('locked');
        $table->string('locked_reason')->nullable();
    });

    ComboboxParityOption::query()->insert([
        [
            'id' => 1,
            'name' => 'Alpha',
            'search_code' => 'first',
            'image' => '/alpha-large.png',
            'avatar' => '/alpha-avatar.png',
            'badge' => 'internal',
            'url' => '/options/1',
            'suffix' => 'Current',
            'locked' => false,
            'locked_reason' => null,
        ],
        [
            'id' => 2,
            'name' => 'Beta',
            'search_code' => 'second',
            'image' => '/beta-large.png',
            'avatar' => '/beta-avatar.png',
            'badge' => 'external',
            'url' => '/options/2',
            'suffix' => 'Pending',
            'locked' => true,
            'locked_reason' => 'Unavailable',
        ],
    ]);
});

it('passes source models and resolved values to option mapping callbacks', function () {
    $options = Combobox::make('option_id')
        ->options(ComboboxParityOption::query()->orderBy('id')->get(), value: 'id')
        ->optionLabel(fn (ComboboxParityOption $currentModel): string => strtolower($currentModel->name))
        ->mapAs(fn (string $value, ComboboxParityOption $currentModel): string => $currentModel->id.'-'.$value)
        ->optionDescription(fn (ComboboxParityOption $row): string => $row->search_code)
        ->mapDescriptionAs(fn (ComboboxParityOption $model): string => strtoupper($model->search_code))
        ->mapValueAs(fn (int $value, ComboboxParityOption $sourceModel): string => 'option-'.$value.'-'.$sourceModel->name)
        ->toArray()['options'];

    expect($options[0])->toMatchArray([
        'label' => '1-alpha',
        'value' => 'option-1-Alpha',
        'description' => 'FIRST',
    ]);
});

it('resolves union and intersection types in option callbacks', function () {
    $options = Combobox::make('option_id')
        ->options(ComboboxParityOption::query()->orderBy('id')->get(), value: 'id')
        ->optionLabel(
            fn (ComboboxParityOption|array $source): string => $source instanceof ComboboxParityOption
                ? $source->name
                : 'array',
        )
        ->optionDescription(
            fn (ComboboxParityOption&JsonSerializable $source): string => $source->search_code,
        )
        ->toArray()['options'];

    expect($options[0])->toMatchArray([
        'label' => 'Alpha',
        'description' => 'first',
    ]);
});

it('serializes semantic option content and state mappings', function () {
    $options = Combobox::make('option_id')
        ->options(ComboboxParityOption::query()->orderBy('id')->get(), 'name', 'id')
        ->optionImage('image')
        ->mapImageAs(fn (ComboboxParityOption $model): array => [
            'url' => $model->image,
            'alt' => $model->name,
        ])
        ->optionAvatar('avatar')
        ->mapAvatarAs(fn (string $value): array => ['url' => $value])
        ->optionBadge('badge')
        ->mapBadgeAs(['internal' => 'Staff', 'external' => 'Guest'])
        ->optionUrl('url')
        ->optionMetadata(fn (ComboboxParityOption $item): array => ['searchCode' => $item->search_code])
        ->optionDisabled('locked')
        ->mapDisabledAs(fn (bool $value): bool => $value)
        ->optionDisabledReason('locked_reason')
        ->mapDisabledReasonAs(fn (ComboboxParityOption $item): ?string => $item->locked_reason)
        ->optionSelectedSuffix('suffix')
        ->mapSelectedSuffixAs(fn (string $value): string => strtoupper($value))
        ->toArray()['options'];

    expect($options[0])->toMatchArray([
        'image' => ['url' => '/alpha-large.png', 'alt' => 'Alpha'],
        'avatar' => ['url' => '/alpha-avatar.png'],
        'badge' => 'Staff',
        'url' => '/options/1',
        'metadata' => ['searchCode' => 'first'],
        'disabled' => false,
        'disabledReason' => null,
        'selectedSuffix' => 'CURRENT',
    ])->and($options[1])->toMatchArray([
        'badge' => 'Guest',
        'disabled' => true,
        'disabledReason' => 'Unavailable',
        'selectedSuffix' => 'PENDING',
    ]);
});

it('serializes consumer-owned combobox state text', function () {
    $field = Combobox::make('option_id')
        ->emptyText('Choose a search term')
        ->noResultsText('No matching options')
        ->loadingText('Loading matching options')
        ->errorText('Options could not be loaded')
        ->maxItemsText('Selection limit reached')
        ->toArray();

    expect($field)->toMatchArray([
        'emptyText' => 'Choose a search term',
        'noResultsText' => 'No matching options',
        'loadingText' => 'Loading matching options',
        'errorText' => 'Options could not be loaded',
        'maxItemsText' => 'Selection limit reached',
    ]);
});

it('passes array source rows and keys to group mapping callbacks', function () {
    $options = Combobox::make('option_id')
        ->options([
            ['id' => 10, 'name' => 'Avery', 'role' => 'admin'],
            ['id' => 20, 'name' => 'Morgan', 'role' => 'editor'],
        ], 'name', 'id')
        ->groupBy('role')
        ->mapGroupAs(fn (array $person, int $sourceKey): string => $sourceKey.'-'.strtoupper($person['role']))
        ->toArray()['options'];

    expect($options[0]['group'])->toBe('0-ADMIN')
        ->and($options[1]['group'])->toBe('1-EDITOR');
});

it('uses the search callback for request-aware query options without infinite paging', function () {
    $request = Request::create('/options', 'GET', [
        'q' => 'second',
        '_inertify_form' => [
            'prop' => 'profileForm',
            'field' => 'option_id',
        ],
    ]);

    $resource = ComboboxParityForm::make()->toInertiaProperty(
        new PropertyContext('profileForm', [], $request),
    );
    $page = $resource['meta']['options']['option_id'];

    expect($page['data'])->toHaveCount(1)
        ->and($page['data'][0])->toMatchArray(['label' => 'Beta', 'value' => 2])
        ->and($page)->toMatchArray([
            'current_page' => 1,
            'per_page' => 1,
            'total' => 1,
            'last_page' => 1,
            'next_page_url' => null,
        ]);
});

it('accepts mutated and returned builders from search callbacks', function () {
    $mutated = Combobox::make('option_id')
        ->options(ComboboxParityOption::query()->orderBy('id'), 'name', 'id')
        ->searchOptionsUsing(function (Builder $options, string $q): void {
            $options->where('search_code', $q);
        })
        ->resolveInertiaOptions([], 'first');

    $returned = Combobox::make('option_id')
        ->options(ComboboxParityOption::query()->orderBy('id'), 'name', 'id')
        ->searchOptionsUsing(
            fn (Builder $options, string $search): Builder => $options->where('search_code', $search),
        )
        ->resolveInertiaOptions([], 'second');

    expect($mutated['data'])->toHaveCount(1)
        ->and($mutated['data'][0])->toMatchArray(['label' => 'Alpha', 'value' => 1])
        ->and($returned['data'])->toHaveCount(1)
        ->and($returned['data'][0])->toMatchArray(['label' => 'Beta', 'value' => 2]);
});

it('accepts normalized option rows returned by a search callback', function () {
    $page = Combobox::make('option_id')
        ->options(ComboboxParityOption::query(), 'name', 'id')
        ->searchOptionsUsing(fn (): iterable => collect([
            [
                'value' => 'custom',
                'label' => 'Synthetic result',
                'metadata' => ['source' => 'callback'],
            ],
        ]))
        ->resolveInertiaOptions([], 'synthetic');

    expect($page['data'])->toBe([
        [
            'value' => 'custom',
            'label' => 'Synthetic result',
            'metadata' => ['source' => 'callback'],
        ],
    ])->and($page['next_page_url'])->toBeNull();
});

it('skips finite membership rules for search-backed choices', function () {
    $field = Combobox::make('framework')
        ->options(['laravel' => 'Laravel'])
        ->searchOptionsUsing(fn (): array => []);

    expect($field->getItemRules())->toBe([]);
});
