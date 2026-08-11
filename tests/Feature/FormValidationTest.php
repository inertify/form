<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Fields\Blocks;
use Inertify\Form\Fields\BlockSet;
use Inertify\Form\Fields\CheckboxGroup;
use Inertify\Form\Fields\Composer;
use Inertify\Form\Fields\DatePicker;
use Inertify\Form\Fields\File;
use Inertify\Form\Fields\KeyValue;
use Inertify\Form\Fields\Link;
use Inertify\Form\Fields\Repeater;
use Inertify\Form\Fields\RichText;
use Inertify\Form\Fields\Slider;
use Inertify\Form\Fields\Textarea;
use Inertify\Form\Fields\TextInput;
use Inertify\Form\Fields\UploadConfig;
use Inertify\Form\Form;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadRules;
use Inertify\Form\Uploads\UploadToken;
use Inertify\Form\Validate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CoreValidationForm extends Form
{
    public function fields(): array
    {
        return [
            TextInput::make('name')->required()->minLength(2),
            TextInput::make('email')->email()->required(),
            File::make('avatar')->nullable()->temporaryUploadUrl('/uploads/avatar'),
            TextInput::make('company')->required()->visibleWhen('kind', 'business'),
        ];
    }
}

class CoreNestedForm extends Form
{
    public function fields(): array
    {
        return [
            Repeater::make('items')->rules(['array'])->schema([
                TextInput::make('name')->required(),
            ]),
            Blocks::make('content')->sets([
                BlockSet::make('hero')->schema([
                    TextInput::make('headline')->required(),
                ]),
                BlockSet::make('quote')->schema([
                    Textarea::make('quote')->required(),
                ]),
            ]),
        ];
    }
}

class CoreRowAwareForm extends Form
{
    public function fields(): array
    {
        return [
            Repeater::make('contacts')->schema([
                TextInput::make('kind')->required(),
                TextInput::make('company')->required()->visibleWhen('kind', 'business'),
                TextInput::make('private')->authorize(false),
                TextInput::make('retained')->hidden()->clearWhenHidden(),
            ]),
            Blocks::make('content')->sets([
                BlockSet::make('hero')->schema([
                    TextInput::make('title')->required(),
                    TextInput::make('secret')->authorize(false),
                ]),
            ]),
        ];
    }
}

class CoreTransformForm extends Form
{
    public function fields(): array
    {
        return [
            DatePicker::make('starts_at')
                ->withTime()
                ->timezone('Europe/Kiev')
                ->valueFormat('YYYY-MM-DD HH:mm'),
            Slider::make('price')->range()->min(0)->max(100)->step(5)->minStepsBetween(2),
            KeyValue::make('metadata')->keyRules(['regex:/^[a-z_]+$/'])->valueRules(['required', 'string']),
            Repeater::make('items')->schema([
                TextInput::make('label')->required(),
                File::make('attachments')->multiple()->temporaryUploadUrl('/custom-upload'),
            ]),
            Blocks::make('sections')->sets([
                BlockSet::make('download')->schema([
                    TextInput::make('title')->required(),
                    File::make('asset')->temporaryUploadUrl('/custom-upload'),
                ]),
            ]),
        ];
    }
}

class CoreManagedUploadForm extends Form
{
    public function fields(): array
    {
        return [File::make('avatar')->image()->maxSize(2048)];
    }
}

class CoreNativeUploadForm extends Form
{
    public function fields(): array
    {
        return [File::make('avatar')->image()->maxSize(512)->storeWithForm()];
    }
}

class CoreComposerForm extends Form
{
    public function fields(): array
    {
        return [
            Composer::make('message')
                ->allowAttachments()
                ->maxLength(20)
                ->acceptedFileTypes('text/plain')
                ->temporaryUploadUrl('/custom-upload'),
            RichText::make('body')->imageUploads(
                fn (UploadConfig $uploads): UploadConfig => $uploads->temporaryUploadUrl('/custom-images'),
            ),
        ];
    }
}

class CoreLinkChoiceForm extends Form
{
    public function fields(): array
    {
        return [
            Link::make('website')->structured()->withLabel()->withTarget()->required()->allowedSchemes('https', 'mailto'),
            Link::make('profile')->allowedSchemes('https'),
            CheckboxGroup::make('roles')->options(['admin' => 'Admin', 'editor' => 'Editor'])->minSelected(1),
        ];
    }
}

class CoreNestedRichTextForm extends Form
{
    public function fields(): array
    {
        return [
            Repeater::make('items')->schema([
                RichText::make('body')->imageUploads(
                    fn (UploadConfig $uploads): UploadConfig => $uploads->temporaryUploadUrl('/custom-images'),
                ),
            ]),
            Blocks::make('content')->set(
                BlockSet::make('copy')->schema([
                    RichText::make('copy')->imageUploads(
                        fn (UploadConfig $uploads): UploadConfig => $uploads->temporaryUploadUrl('/custom-images'),
                    ),
                ]),
            ),
        ];
    }
}

it('validates the current request and excludes hidden fields', function () {
    $request = Request::create('/users', 'POST', [
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'avatar' => 'opaque-token',
        'kind' => 'personal',
    ]);
    app()->instance('request', $request);

    $form = CoreValidationForm::make();

    expect($form->validated())->toBe([
        'name' => 'Ada',
        'email' => 'ada@example.test',
        'avatar' => 'opaque-token',
    ])->and($form->validated(files: false))->toBe([
        'name' => 'Ada',
        'email' => 'ada@example.test',
    ]);
});

it('throws validation and authorization exceptions', function () {
    expect(fn () => CoreValidationForm::make()->validate(['name' => 'A', 'email' => 'bad']))
        ->toThrow(ValidationException::class)
        ->and(fn () => CoreValidationForm::make()->authorize(false)->validate([]))
        ->toThrow(AuthorizationException::class);
});

it('resolves and validates form parameters through the contextual attribute', function () {
    app()->instance('request', Request::create('/users', 'POST', [
        'name' => 'Ada',
        'email' => 'ada@example.test',
    ]));

    $result = app()->call(function (#[Validate] CoreValidationForm $form): array {
        return $form->validated();
    });

    expect($result)->toBe(['name' => 'Ada', 'email' => 'ada@example.test']);
});

it('filters rules for a precognitive validate-only request', function () {
    $request = Request::create('/users', 'POST', [
        'name' => 'Ada',
        'email' => 'invalid',
    ], [], [], [
        'HTTP_PRECOGNITION' => 'true',
        'HTTP_PRECOGNITION_VALIDATE_ONLY' => 'name',
    ]);
    $request->attributes->set('precognitive', true);

    expect(fn () => CoreValidationForm::make()->setRequest($request)->validate())
        ->toThrow(HttpException::class);
});

it('collects request-indexed repeater and block rules', function () {
    $form = CoreNestedForm::make()->setRequest(Request::create('/content', 'POST', [
        'items' => [['name' => 'First']],
        'content' => [
            ['type' => 'hero', 'data' => ['headline' => 'Launch']],
            ['type' => 'quote', 'data' => ['quote' => 'Hello']],
        ],
    ]));

    expect($form->rules())
        ->toHaveKeys([
            'items', 'items.0.name',
            'content.0.data.headline', 'content.1.data.quote',
        ])
        ->not->toHaveKey('content.0.data.quote')
        ->and($form->validate())->toMatchArray([
            'items' => [['name' => 'First']],
            'content' => [
                ['type' => 'hero', 'data' => ['headline' => 'Launch']],
                ['type' => 'quote', 'data' => ['quote' => 'Hello']],
            ],
        ]);
});

it('evaluates nested visibility per submitted row and filters unauthorized nested values', function () {
    $input = [
        'contacts' => [
            ['kind' => 'personal', 'company' => 'Ignored', 'private' => 'secret', 'retained' => 'keep'],
            ['kind' => 'business', 'company' => 'Acme', 'private' => 'secret', 'retained' => 'keep'],
        ],
        'content' => [['type' => 'hero', 'data' => ['title' => 'Launch', 'secret' => 'hidden']]],
    ];
    $form = CoreRowAwareForm::make()->setRequest(Request::create('/contacts', 'POST', $input));

    expect($form->rules())
        ->and($form->rules()['contacts.0.company'])->toBe(['exclude'])
        ->and($form->rules()['contacts.1.company'])->toContain('required')
        ->and($form->rules())->not->toHaveKeys(['contacts.0.private', 'contacts.1.private', 'content.0.data.secret'])
        ->and($form->data($input)->data())->toBe([
            'contacts' => [
                ['kind' => 'personal', 'company' => 'Ignored', 'retained' => 'keep'],
                ['kind' => 'business', 'company' => 'Acme', 'retained' => 'keep'],
            ],
            'content' => [['type' => 'hero', 'data' => ['title' => 'Launch']]],
        ])
        ->and($form->validate())->toBe([
            'contacts' => [
                ['kind' => 'personal'],
                ['kind' => 'business', 'company' => 'Acme'],
            ],
            'content' => [['type' => 'hero', 'data' => ['title' => 'Launch']]],
        ]);
});

it('keeps validate raw, transforms validated dates, enforces slider gaps, and removes nested files', function () {
    $input = [
        'starts_at' => '2026-08-11 12:30',
        'price' => [10, 25],
        'metadata' => ['release_stage' => 'stable'],
        'items' => [['label' => 'Guide', 'attachments' => ['one', 'two']]],
        'sections' => [['type' => 'download', 'data' => ['title' => 'Assets', 'asset' => 'three']]],
    ];
    $form = CoreTransformForm::make()->setRequest(Request::create('/release', 'POST', $input));

    expect($form->validate()['starts_at'])->toBe('2026-08-11 12:30')
        ->and($form->validated()['starts_at'])->toBe('2026-08-11 09:30')
        ->and($form->validated(files: false))->toBe([
            'starts_at' => '2026-08-11 09:30',
            'price' => [10, 25],
            'metadata' => ['release_stage' => 'stable'],
            'items' => [['label' => 'Guide']],
            'sections' => [['type' => 'download', 'data' => ['title' => 'Assets']]],
        ]);

    expect(fn () => CoreTransformForm::make()->validate([
        ...$input,
        'price' => [25, 30],
    ]))->toThrow(ValidationException::class);
});

it('validates managed upload tokens against the encrypted field profile', function () {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('u', 32)));
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'form-core');
    Storage::fake('form-core');

    $profile = UploadRules::make(['image', 'mimetypes:image/*', 'max:2048']);
    $stored = app(UploadManager::class)->store(HttpUploadedFile::fake()->image('avatar.jpg'), $profile);
    $valid = app(UploadManager::class)->tokenFor($stored);

    expect(CoreManagedUploadForm::make()->validate(['avatar' => $valid]))->toBe(['avatar' => $valid])
        ->and(fn () => CoreManagedUploadForm::make()->validate(['avatar' => $valid.'tampered']))
        ->toThrow(ValidationException::class);

    $wrong = app(UploadManager::class)->store(
        HttpUploadedFile::fake()->image('other.jpg'),
        UploadRules::make(['image']),
    );
    expect(fn () => CoreManagedUploadForm::make()->validate([
        'avatar' => app(UploadManager::class)->tokenFor($wrong),
    ]))->toThrow(ValidationException::class);

    $expired = app(UploadToken::class)->encode('temporary-upload', $stored->toArray(), now()->subSecond()->timestamp);
    expect(fn () => CoreManagedUploadForm::make()->validate(['avatar' => $expired]))
        ->toThrow(ValidationException::class);
});

it('applies file rules to native store-with-form uploads and omits them from mass assignment', function () {
    $valid = HttpUploadedFile::fake()->image('avatar.jpg', 40, 40)->size(100);
    $request = Request::create('/profile', 'POST', [], [], ['avatar' => $valid]);
    $form = CoreNativeUploadForm::make()->setRequest($request);

    expect($form->validated()['avatar'])->toBe($valid)
        ->and($form->validated(files: false))->toBe([])
        ->and($form->upload('avatar'))->toBeNull();

    $invalid = HttpUploadedFile::fake()->create('avatar.txt', 10, 'text/plain');
    expect(fn () => CoreNativeUploadForm::make()->setRequest(
        Request::create('/profile', 'POST', [], [], ['avatar' => $invalid]),
    )->validate())->toThrow(ValidationException::class);
});

it('normalizes and validates composer attachments and rich-text companion tokens', function () {
    $input = [
        'message' => ['text' => 'Hello', 'attachments' => ['one', 'two']],
        'body' => '<p>Body</p>',
        'body_images' => ['image-one'],
    ];
    $form = CoreComposerForm::make()->setRequest(Request::create('/messages', 'POST', $input));

    expect($form->rules())->toHaveKeys([
        'message', 'message.text', 'message.attachments', 'message.attachments.*',
        'body', 'body_images', 'body_images.*',
    ])->and($form->validate())->toBe($input)
        ->and($form->validated(files: false))->toBe([
            'message' => ['text' => 'Hello'],
            'body' => '<p>Body</p>',
        ])
        ->and(CoreComposerForm::make()->data(['message' => 'Scalar'])->data()['message'])
        ->toBe(['text' => 'Scalar', 'attachments' => []]);
});

it('qualifies and preserves rich-text image companions inside repeaters and blocks', function () {
    $input = [
        'items' => [[
            'body' => '<p>Body</p>',
            'body_images' => ['body-image'],
        ]],
        'content' => [[
            'type' => 'copy',
            'data' => [
                'copy' => '<p>Copy</p>',
                'copy_images' => ['copy-image'],
            ],
        ]],
    ];
    $form = CoreNestedRichTextForm::make()->setRequest(Request::create('/content', 'POST', $input));

    expect($form->rules())->toHaveKeys([
        'items.0.body',
        'items.0.body_images',
        'items.0.body_images.*',
        'content.0.data.copy',
        'content.0.data.copy_images',
        'content.0.data.copy_images.*',
    ])->not->toHaveKeys(['body_images', 'copy_images'])
        ->and($form->validate())->toBe($input)
        ->and($form->validated(files: false))->toBe([
            'items' => [['body' => '<p>Body</p>']],
            'content' => [[
                'type' => 'copy',
                'data' => ['copy' => '<p>Copy</p>'],
            ]],
        ]);
});

it('validates structured links and finite checkbox-group values', function () {
    $valid = [
        'website' => ['url' => 'https://example.com', 'label' => 'Example', 'target' => '_blank'],
        'profile' => 'example.com/profile',
        'roles' => ['admin'],
    ];

    expect(CoreLinkChoiceForm::make()->validate($valid))->toBe($valid)
        ->and(CoreLinkChoiceForm::make()->data([
            ...$valid,
            'website' => 'https://example.com',
        ])->data()['website'])->toBe([
            'url' => 'https://example.com',
            'label' => '',
            'target' => '',
        ]);

    expect(fn () => CoreLinkChoiceForm::make()->validate([
        ...$valid,
        'website' => ['url' => 'javascript:alert(1)', 'label' => '', 'target' => '_blank'],
    ]))->toThrow(ValidationException::class)
        ->and(fn () => CoreLinkChoiceForm::make()->validate([...$valid, 'roles' => ['owner']]))
        ->toThrow(ValidationException::class);
});
