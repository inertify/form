<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\PropertyContext;
use Inertify\Form\Fields\Combobox;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\Submit;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Form;
use Inertify\Form\WizardConfig;

class CoreProfileForm extends Form
{
    protected ?string $actionRoute = 'core-profile.update';

    protected bool $unsavedWarning = true;

    public function fields(): array
    {
        return [
            TextInput::make('name')->required(),
            TextInput::make('password')->password(),
            TextInput::make('secret')->authorize(false),
            Fieldset::make([
                TextInput::make('timezone')->default('UTC'),
            ])->legend('Preferences'),
            Submit::make('Save'),
        ];
    }

    public function wizard(): WizardConfig
    {
        return WizardConfig::make()
            ->step('Account')
            ->step('Preferences')
            ->validateOnStep()
            ->labels(next: 'Continue', prev: 'Back', submit: 'Finish');
    }
}

class CoreQueryOption extends Model
{
    public $timestamps = false;

    protected $table = 'core_query_options';

    protected $guarded = [];
}

class CoreQueryForm extends Form
{
    public function fields(): array
    {
        return [
            Combobox::make('primary_id')->options(CoreQueryOption::class, 'name', 'id')->perPage(1)->default(3),
            Combobox::make('secondary_id')->options(CoreQueryOption::class, 'name', 'id')->perPage(1)->default(2),
        ];
    }
}

class CoreScopedQueryForm extends Form
{
    public function fields(): array
    {
        return [
            Combobox::make('option_id')
                ->options(CoreQueryOption::query()->where('name', 'Alpha'), 'name', 'id'),
            Combobox::make('selected_id')
                ->options(CoreQueryOption::class, 'name', 'id')
                ->selected(fn (mixed $value): array => [['value' => $value, 'label' => 'Selected '.$value]])
                ->default(2),
        ];
    }
}

class CoreFileBindingForm extends Form
{
    public function fields(): array
    {
        return [
            File::make('document')->disk('public'),
            File::make('gallery')->multiple()->disk('public'),
            File::make('media')->multiple()->mediaCollection('images'),
        ];
    }
}

class CoreMediaBindingModel extends Model
{
    /** @var array<object> */
    public array $mediaItems = [];

    /** @return array<object> */
    public function getMedia(string $collection): array
    {
        return $this->mediaItems;
    }
}

class CoreMediaItem
{
    public int $id = 1;

    public function getDiskDriverName(): string
    {
        return 'public';
    }

    public function getPathRelativeToRoot(): string
    {
        return 'media/photo.jpg';
    }
}

class CoreBindingUser extends Model
{
    public $timestamps = false;

    protected $table = 'core_binding_users';

    protected $guarded = [];

    public function profile(): HasOne
    {
        return $this->hasOne(CoreBindingProfile::class, 'user_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return strtoupper((string) $this->first_name);
    }
}

class CoreBindingProfile extends Model
{
    public $timestamps = false;

    protected $table = 'core_binding_profiles';

    protected $guarded = [];
}

class CoreDirectModelBindingForm extends Form
{
    public function fields(): array
    {
        return [
            TextInput::make('display_name'),
            TextInput::make('nickname')->default('Fallback'),
            TextInput::make('profile.bio'),
        ];
    }
}

beforeEach(function () {
    Route::put('/core-profiles/{profile?}', fn () => null)->name('core-profile.update');
});

it('resolves action method data fieldsets and wizard into a stable resource', function () {
    $resource = CoreProfileForm::make()
        ->post()
        ->route('core-profile.update')
        ->bind(['name' => null, 'password' => 'hashed', 'unused' => 'private'])
        ->dataAttribute('testId', 'profile')
        ->meta(['version' => 1])
        ->toArray();

    expect(array_keys($resource))->toBe([
        'action', 'method', 'fieldsets', 'data', 'dataAttributes', 'meta',
        'unsavedWarning', 'scrollToFirstError', 'wizard',
    ])->and($resource)
        ->method->toBe('PUT')
        ->data->toBe(['name' => null, 'password' => null, 'timezone' => 'UTC'])
        ->dataAttributes->toBe(['data-test-id' => 'profile'])
        ->meta->toBe(['version' => 1])
        ->unsavedWarning->toBeTrue()
        ->fieldsets->toHaveCount(3)
        ->wizard->validateOnStep->toBeTrue()
        ->wizard->nextLabel->toBe('Continue');
});

it('returns the exact empty resource when unauthorized', function () {
    $resource = Form::make()->authorize(false)->toArray();

    expect($resource)
        ->action->toBeNull()
        ->method->toBe('POST')
        ->fieldsets->toBe([])
        ->data->toBeInstanceOf(stdClass::class)
        ->dataAttributes->toBeNull()
        ->meta->toBeNull()
        ->unsavedWarning->toBeFalse()
        ->scrollToFirstError->toBeFalse()
        ->wizard->toBeNull();
});

it('uses data as a getter and accepts raw urls with explicit methods', function () {
    $form = CoreProfileForm::make()->url('/profiles')->patch()->bind(['name' => 'Ada']);

    expect($form->data())->toBe(['name' => 'Ada', 'password' => null, 'timezone' => 'UTC'])
        ->and($form->toArray())->action->toBe('/profiles')->method->toBe('PATCH');
});

it('receives inertia property request context', function () {
    $request = Request::create('/profiles', 'GET');
    $form = CoreProfileForm::make();
    $resource = $form->toInertiaProperty(new PropertyContext('profileForm', [], $request));

    expect($form->getPropertyKey())->toBe('profileForm')
        ->and($resource['method'])->toBe('PUT');
});

it('serializes collision-safe query-backed combobox pages into form metadata', function () {
    Schema::dropIfExists('core_query_options');
    Schema::create('core_query_options', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    CoreQueryOption::query()->insert([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
        ['id' => 3, 'name' => 'Gamma'],
    ]);

    $request = Request::create('/profiles', 'GET');
    $resource = CoreQueryForm::make()->meta(['owner' => 'application'])->toInertiaProperty(
        new PropertyContext('profileForm', [], $request),
    );

    $fields = $resource['fieldsets'][0]['fields'];
    expect($resource['meta']['owner'])->toBe('application')
        ->and(array_keys($resource['meta']['options']))->toBe(['primary_id', 'secondary_id'])
        ->and($resource['meta']['options']['primary_id'])->toMatchArray([
            'current_page' => 1,
            'per_page' => 1,
            'total' => 3,
            'last_page' => 3,
        ])
        ->and($resource['meta']['options']['primary_id']['data'][0])->toMatchArray(['label' => 'Alpha', 'value' => 1])
        ->and($fields[0])->toMatchArray([
            'options' => [],
            'optionsKey' => 'primary_id',
            'optionsMode' => 'inertia',
        ])
        ->and($fields[0]['selected'][0])->toMatchArray(['label' => 'Gamma', 'value' => 3]);
});

it('targets one query-backed field by inertia prop, search, and page without collisions', function () {
    Schema::dropIfExists('core_query_options');
    Schema::create('core_query_options', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    CoreQueryOption::query()->insert([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
        ['id' => 3, 'name' => 'Gamma'],
    ]);

    $targeted = Request::create('/profiles', 'GET', [
        '_inertify_form' => [
            'prop' => 'profileForm',
            'field' => 'primary_id',
            'page' => 2,
        ],
    ]);
    $resource = CoreQueryForm::make()->toInertiaProperty(new PropertyContext('profileForm', [], $targeted));

    expect(array_keys($resource['meta']['options']))->toBe(['primary_id'])
        ->and($resource['meta']['options']['primary_id']['current_page'])->toBe(2)
        ->and($resource['meta']['options']['primary_id']['data'][0])->toMatchArray(['label' => 'Beta', 'value' => 2]);

    $searched = Request::create('/profiles', 'GET', [
        '_inertify_form' => ['prop' => 'profileForm', 'field' => 'primary_id', 'search' => 'Gamma'],
    ]);
    $resource = CoreQueryForm::make()->toInertiaProperty(new PropertyContext('profileForm', [], $searched));
    expect($resource['meta']['options']['primary_id']['data'][0])->toMatchArray(['label' => 'Gamma', 'value' => 3]);

    $mismatch = Request::create('/profiles', 'GET', [
        '_inertify_form' => ['prop' => 'anotherForm', 'field' => 'primary_id', 'search' => 'Gamma'],
    ]);
    $resource = CoreQueryForm::make()->toInertiaProperty(new PropertyContext('profileForm', [], $mismatch));
    expect(array_keys($resource['meta']['options']))->toBe(['primary_id', 'secondary_id'])
        ->and($resource['meta']['options']['primary_id']['data'][0]['label'])->toBe('Alpha');
});

it('normalizes bound file paths and optional media collections as existing-file payloads', function () {
    Storage::fake('public');
    Storage::disk('public')->put('documents/report.txt', 'report');
    Storage::disk('public')->put('gallery/one.jpg', 'one');
    Storage::disk('public')->put('gallery/two.jpg', 'two');
    Storage::disk('public')->put('media/photo.jpg', 'photo');

    $data = CoreFileBindingForm::make()->bind([
        'document' => 'documents/report.txt',
        'gallery' => ['gallery/one.jpg', 'gallery/two.jpg'],
    ])->data();

    expect($data['document'])->toMatchArray([
        'identifier' => 'public:documents/report.txt',
        'filename' => 'report.txt',
    ])->and($data['gallery'])->toHaveCount(2)
        ->and($data['gallery'][1])->toMatchArray(['identifier' => 'public:gallery/two.jpg']);

    $model = new CoreMediaBindingModel;
    $model->mediaItems = [new CoreMediaItem];
    $media = CoreFileBindingForm::make()->bind($model)->data()['media'];
    expect($media)->toHaveCount(1)
        ->and($media[0])->toMatchArray(['identifier' => 'public:media/photo.jpg']);
});

it('binds directly through model accessors and unloaded relations while serializing plain data', function () {
    Schema::dropIfExists('core_binding_profiles');
    Schema::dropIfExists('core_binding_users');
    Schema::create('core_binding_users', function (Blueprint $table): void {
        $table->id();
        $table->string('first_name');
        $table->string('nickname')->nullable();
    });
    Schema::create('core_binding_profiles', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('user_id');
        $table->string('bio');
    });

    $user = CoreBindingUser::query()->create(['first_name' => 'Ada', 'nickname' => null]);
    CoreBindingProfile::query()->create(['user_id' => $user->getKey(), 'bio' => 'Mathematician']);
    $user = CoreBindingUser::query()->findOrFail($user->getKey());

    expect($user->relationLoaded('profile'))->toBeFalse();

    $form = CoreDirectModelBindingForm::make()->bind($user);
    $data = $form->data();

    expect($data)->toBe([
        'display_name' => 'ADA',
        'nickname' => null,
        'profile' => ['bio' => 'Mathematician'],
    ])->and($user->relationLoaded('profile'))->toBeTrue()
        ->and($form->toArray()['data'])->toBe($data)
        ->and(CoreDirectModelBindingForm::make()->bind($user, except: ['display_name'])->data()['display_name'])
        ->toBeNull();
});

it('omits wizard steps whose authorized fieldset has no visible child fields', function () {
    $fieldsets = [
        Fieldset::make([
            TextInput::make('company')->visibleWhen('kind', 'business'),
        ]),
        Fieldset::make([
            TextInput::make('name'),
        ]),
        Fieldset::make([
            Fieldset::make([
                TextInput::make('secret')->hidden(),
            ]),
        ]),
    ];
    $wizard = WizardConfig::make()
        ->step('Business')
        ->step('Identity')
        ->step('Hidden');

    expect($wizard->toArrayFor($fieldsets, ['kind' => 'personal'])['steps'])->toBe([
        ['fieldset' => 1, 'title' => 'Identity', 'description' => null],
    ])->and($wizard->toArrayFor($fieldsets, ['kind' => 'business'])['steps'])->toBe([
        ['fieldset' => 0, 'title' => 'Business', 'description' => null],
        ['fieldset' => 1, 'title' => 'Identity', 'description' => null],
    ]);
});

it('validates constrained query membership and resolves selected callbacks with the bound value', function () {
    Schema::dropIfExists('core_query_options');
    Schema::create('core_query_options', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    CoreQueryOption::query()->insert([
        ['id' => 1, 'name' => 'Alpha'],
        ['id' => 2, 'name' => 'Beta'],
    ]);

    expect(CoreScopedQueryForm::make()->validate(['option_id' => 1]))->toBe(['option_id' => 1])
        ->and(fn () => CoreScopedQueryForm::make()->validate(['option_id' => 2]))
        ->toThrow(ValidationException::class);

    $resource = CoreScopedQueryForm::make()->toInertiaProperty(
        new PropertyContext('queryForm', [], Request::create('/options', 'GET')),
    );
    expect($resource['fieldsets'][0]['fields'][1]['selected'][0])->toBe([
        'value' => 2,
        'label' => 'Selected 2',
    ]);
});
