<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertify\Form\Conditions\Condition;
use Inertify\Form\Fields\Blocks;
use Inertify\Form\Fields\BlockSet;
use Inertify\Form\Fields\Checkbox;
use Inertify\Form\Fields\CheckboxGroup;
use Inertify\Form\Fields\ColorPicker;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\DatePicker;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\Hidden;
use Inertify\Form\Fields\OtpInput;
use Inertify\Form\Fields\Radio;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\Slider;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Fields\Textarea;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Fields\TimePicker;

class CoreFiniteChoiceOption extends Model
{
    public $timestamps = false;

    protected $table = 'core_finite_choice_options';

    protected $guarded = [];
}

it('serializes canonical field state and generated rules', function () {
    $field = TextInput::make('email_address')
        ->email()
        ->required()
        ->minLength(5)
        ->clearable()
        ->dataAttribute('testId', 'email')
        ->meta(['source' => 'profile']);

    expect($field->toArray())
        ->component->toBe('Text')
        ->name->toBe('email_address')
        ->label->toBe('Email Address')
        ->inputType->toBe('email')
        ->required->toBeTrue()
        ->clearable->toBeTrue()
        ->dataAttributes->toBe(['data-test-id' => 'email'])
        ->meta->toBe(['source' => 'profile'])
        ->and($field->getRules())->toBe(['required', 'string', 'email', 'min:5']);
});

it('serializes semantic renderer discriminators independently of PHP class names', function () {
    expect([
        TextInput::make('name')->getComponent(),
        Textarea::make('summary')->getComponent(),
        Checkbox::make('terms')->getComponent(),
        OtpInput::make('code')->getComponent(),
        Hidden::make('token')->getComponent(),
    ])->toBe(['Text', 'Textarea', 'Checkbox', 'Otp', 'Hidden']);
});

it('rejects undocumented visual options', function () {
    expect(fn () => TextInput::make('name')->variant('outline'))
        ->toThrow(BadMethodCallException::class);
});

it('serializes and evaluates visibility conditions with root dependencies', function () {
    $field = Textarea::make('reason')
        ->visibleWhen('status', 'active')
        ->visibleWhenNot(fn ($visibility) => $visibility->where('archived', true));

    expect($field->isVisible(['status' => 'active', 'archived' => false]))->toBeTrue()
        ->and($field->isVisible(['status' => 'active', 'archived' => true]))->toBeFalse()
        ->and($field->toArray()['visibility'])->toMatchArray([
            'mode' => 'and',
            'dependsOn' => ['status', 'archived'],
        ]);

    expect(Condition::make('$.age', '>=', 18)->matches(['age' => '18']))->toBeTrue();
});

it('normalizes finite combobox options and functional configuration', function () {
    $field = Combobox::make('role')
        ->options([
            ['id' => 1, 'name' => 'Admin'],
            ['id' => 2, 'name' => 'Editor'],
        ], label: 'name', value: 'id')
        ->searchable()
        ->records();

    expect($field->toArray())
        ->options->toHaveCount(2)
        ->options->{0}->toMatchArray(['label' => 'Admin', 'value' => 1])
        ->searchable->toBeTrue()
        ->records->toBeTrue();
});

it('provides functional submit and otp semantics', function () {
    expect(Submit::make('Save draft')->value('draft')->toArray())
        ->name->toBe('_submit')
        ->label->toBe('Save draft')
        ->value->toBe('draft')
        ->and(OtpInput::make('code')->length(6)->numeric()->getRules())
        ->toBe(['numeric', 'digits:6']);
});

it('keeps otp input mode metadata and managed length rules in sync', function () {
    $alphanumeric = OtpInput::make('code')->length(6)->alphanumeric();

    expect($alphanumeric->toArray())
        ->numeric->toBeFalse()
        ->alphanumeric->toBeTrue()
        ->rules->toBe(['alpha_num', 'size:6'])
        ->and(OtpInput::make('code')->numeric()->length(6)->alphanumeric(false)->toArray())
        ->numeric->toBeTrue()
        ->alphanumeric->toBeFalse()
        ->rules->toBe(['size:6']);
});

it('normalizes numeric visibility membership and keyed combobox choices', function () {
    expect(Condition::make('ids', 'contains', '10')->matches(['ids' => [10, 20]]))->toBeTrue()
        ->and(Condition::make('id', 'in', ['10', '20'])->matches(['id' => 10]))->toBeTrue();

    $field = Combobox::make('country')->options(['us' => 'United States', 'ca' => 'Canada']);
    expect($field->toArray()['options'][0])->toMatchArray(['label' => 'United States', 'value' => 'us'])
        ->and((string) $field->getRules()[0])->toBe('in:"us","ca"');
});

it('serializes repeater templates and canonical block-set resources', function () {
    $repeater = Repeater::make('items')->schema([
        TextInput::make('name'),
        TextInput::make('quantity')->default(1),
    ])->defaultItem(['name' => 'New'])->itemLabel('name')->addButtonText('Add item');

    expect($repeater->toArray())
        ->defaultItem->toBe(['name' => 'New', 'quantity' => 1])
        ->itemLabel->toBe('name')
        ->addButtonText->toBe('Add item');

    $blocks = Blocks::make('content')->set(
        BlockSet::make('hero')
            ->description('Page hero')
            ->schema([TextInput::make('headline'), TextInput::make('tone')->default('calm')])
            ->default(['headline' => 'Welcome'])
            ->maxItems(1),
    );
    expect($blocks->toArray()['sets'][0])->toMatchArray([
        'type' => 'hero',
        'description' => 'Page hero',
        'maxItems' => 1,
        'defaultData' => ['headline' => 'Welcome', 'tone' => 'calm'],
    ])->and($blocks->toArray()['sets'][0])->toHaveKey('schema')->not->toHaveKeys(['name', 'fields']);
});

it('generates functional validation rules for colors and finite choices', function () {
    expect(ColorPicker::make('color')->getRules())->toBe(['string', 'hex_color'])
        ->and(ColorPicker::make('color')->rgb()->getRules())->toBe(['string'])
        ->and(Radio::make('status')->options(['draft' => 'Draft'])->toArray()['rules'][0])->toBe('in:"draft"')
        ->and(CheckboxGroup::make('roles')->options(['admin' => 'Admin'])->minSelected(1)->maxSelected(2)->getRules())
        ->toBe(['min:1', 'max:2', 'array']);
});

it('validates specialized value configuration and supports palette reset', function () {
    expect(ColorPicker::make('color')->swatches(null)->toArray())
        ->swatches->toBeNull()
        ->and(ColorPicker::make('color')->formats(['hex', 'rgb', 'hex'])->toArray())
        ->formats->toBe(['hex', 'rgb'])
        ->and(fn () => ColorPicker::make('color')->formats(['cmyk']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DatePicker::make('date')->firstDayOfWeek(7))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => DatePicker::make('date')->minDate('2026-02-01')->maxDate('2026-01-01'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimePicker::make('time')->minuteStep(0))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimePicker::make('time')->disabledHours([24]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimePicker::make('time')->minTime('17:00')->maxTime('09:00'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Slider::make('range')->max(10)->min(11))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Slider::make('range')->step(1, jump: 0))
        ->toThrow(InvalidArgumentException::class);
});

it('uses documented date tokens and only enforces disabled seconds when enabled', function () {
    expect(DatePicker::make('date')->single()->valueFormat('M/D/YYYY')->getRules())
        ->toContain('date_format:n/j/Y')
        ->and(DatePicker::make('month')->month()->valueFormat('DD/MM/YYYY')->getRules())
        ->toContain('date_format:Y-m');

    $withoutSeconds = TimePicker::make('time')->disabledSeconds([0]);
    $withSeconds = TimePicker::make('time')->showSeconds()->disabledSeconds([0]);

    expect(validator(['time' => '09:15'], ['time' => $withoutSeconds->getRules()])->passes())->toBeTrue()
        ->and(validator(['time' => '09:15:00'], ['time' => $withSeconds->getRules()])->passes())->toBeFalse()
        ->and(TimePicker::make('time')->showSeconds()->showSeconds(false)->toArray())
        ->showSeconds->toBeFalse()
        ->valueFormat->toBe('HH:mm')
        ->displayFormat->toBe('h:mm A');
});

it('keeps rich text character limits as editor metadata rather than markup-length validation', function () {
    expect(RichText::make('body')->maxLength(500)->toArray())
        ->maxLength->toBe(500)
        ->rules->toBe(['string']);
});

it('resolves finite model query and closure choices with value and description remapping', function () {
    Schema::dropIfExists('core_finite_choice_options');
    Schema::create('core_finite_choice_options', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('summary');
    });
    CoreFiniteChoiceOption::query()->insert([
        ['id' => 1, 'name' => 'Starter', 'summary' => 'basic'],
        ['id' => 2, 'name' => 'Team', 'summary' => 'shared'],
    ]);

    $radio = Radio::make('plan')
        ->options(CoreFiniteChoiceOption::class, 'name', 'id', 'summary')
        ->mapValueAs([1 => 'starter', 2 => 'team'])
        ->mapDescriptionAs(['basic' => 'For one person', 'shared' => 'For teams']);

    expect($radio->toArray()['options'])->toBe([
        [
            'label' => 'Starter',
            'value' => 'starter',
            'description' => 'For one person',
            'disabled' => false,
            'disabledReason' => null,
        ],
        [
            'label' => 'Team',
            'value' => 'team',
            'description' => 'For teams',
            'disabled' => false,
            'disabledReason' => null,
        ],
    ])->and((string) $radio->getRules()[0])->toBe('in:"starter","team"');

    $checkboxes = CheckboxGroup::make('plans')
        ->options(
            fn () => CoreFiniteChoiceOption::query()->orderByDesc('id'),
            'name',
            'id',
            'summary',
        )
        ->mapDescriptionAs(fn (CoreFiniteChoiceOption $option): string => strtoupper($option->summary));

    expect($checkboxes->toArray()['options'])
        ->toHaveCount(2)
        ->and($checkboxes->toArray()['options'][0])->toMatchArray([
            'label' => 'Team',
            'value' => 2,
            'description' => 'SHARED',
        ])
        ->and((string) $checkboxes->getChoiceRules()[0])->toBe('in:"2","1"');

    $queryOptions = Radio::make('query_plan')
        ->options(CoreFiniteChoiceOption::query()->getQuery()->orderBy('id'), 'name', 'id');

    expect($queryOptions->toArray()['options'])->toHaveCount(2)
        ->and($queryOptions->toArray()['options'][1])->toMatchArray(['label' => 'Team', 'value' => 2]);
});

it('normalizes checkbox group bound-value shapes', function () {
    $field = CheckboxGroup::make('permissions');

    expect($field->normalizeValue(null))->toBe([])
        ->and($field->normalizeValue(['view', 'edit']))->toBe(['view', 'edit'])
        ->and($field->normalizeValue('view, edit'))->toBe(['view', 'edit'])
        ->and($field->normalizeValue('view'))->toBe(['view'])
        ->and($field->normalizeValue(7))->toBe([7])
        ->and($field->normalizeValue(new stdClass))->toBe([]);
});

it('keeps file transport overrides authoritative in flat and nested resources', function () {
    Route::inertiaFormUploads(prefix: '/core-uploads', middleware: [], name: 'core-forms');

    $field = File::make('archive')
        ->uploadRoutes('core-forms')
        ->temporaryUploadUrl('/custom/store')
        ->temporaryUploadDeleteUrl('/custom/delete')
        ->chunked(1024)
        ->partSize(2048)
        ->multipartThreshold(4096)
        ->maxSize(512)
        ->toArray();

    expect($field)
        ->temporaryUploadUrl->toBe('/custom/store')
        ->temporaryUploadDeleteUrl->toBe('/custom/delete')
        ->chunkSize->toBe(1024)
        ->uploadPartSize->toBe(2048)
        ->uploadMultipartThreshold->toBe(4096)
        ->and($field['upload']['endpoints']['store']['url'])->toBe('/custom/store')
        ->and($field['upload']['endpoints']['destroy']['url'])->toBe('/custom/delete')
        ->and($field['upload']['limits'])->toMatchArray([
            'maxSizeKiB' => 512,
            'directMaxSizeKiB' => 512,
            'chunkSizeBytes' => 1024,
            'partSizeBytes' => 2048,
            'multipartThresholdBytes' => 4096,
        ]);
});

it('rejects invalid file count and size bounds', function () {
    expect(fn () => File::make('files')->maxFiles(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => File::make('files')->minFiles(3)->maxFiles(2))->toThrow(InvalidArgumentException::class)
        ->and(fn () => File::make('file')->minSize(-1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => File::make('file')->maxSize(10)->minSize(11))->toThrow(InvalidArgumentException::class);
});
