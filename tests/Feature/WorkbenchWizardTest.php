<?php

declare(strict_types=1);

use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use Inertify\Form\Fields\Field;
use Inertify\Form\Fields\Fieldset;
use Inertify\Form\Routing\UploadRoutes;
use Symfony\Component\HttpFoundation\Response;
use Workbench\App\Forms\ProfileForm;

beforeEach(function (): void {
    app(UploadRoutes::class)->register(
        app(Router::class),
        prefix: '/workbench/uploads',
        middleware: [],
    );
    require __DIR__.'/../../workbench/routes/web.php';
});

it('demonstrates every serialized field component in the workbench', function () {
    $components = collect(ProfileForm::make()->getFieldsets())
        ->flatMap(fn (Fieldset $fieldset): array => $fieldset->getFields())
        ->map(fn (Field $field): string => $field->getComponent())
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($components)->toBe([
        'Blocks',
        'Checkbox',
        'CheckboxGroup',
        'ColorPicker',
        'Combobox',
        'Composer',
        'DatePicker',
        'File',
        'Hidden',
        'KeyValue',
        'Link',
        'Otp',
        'Radio',
        'Repeater',
        'RichText',
        'Slider',
        'Slug',
        'Submit',
        'Text',
        'Textarea',
        'TimePicker',
        'Toggle',
    ]);
});

it('keeps the expanded workbench defaults valid when required fields are filled', function () {
    $form = ProfileForm::make();
    $data = $form->data();
    $data['name'] = 'Ada Lovelace';
    $data['email'] = 'ada@example.com';
    $data['skill'] = 'php';
    $data['projects'] = [['title' => 'Analytical Engine', 'summary' => '']];

    expect($form->validate($data))->toMatchArray([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'skill' => 'php',
    ]);
});

it('serves the expanded component workbench resource', function (string $uri, string $mode) {
    $this->withHeader('X-Inertia', 'true')
        ->get($uri)
        ->assertOk()
        ->assertJsonPath('component', 'Forms/Demo')
        ->assertJsonPath('props.mode', $mode)
        ->assertJsonCount(4, 'props.form.fieldsets');
})->with([
    'create' => ['/', 'create'],
    'edit' => ['/edit', 'edit'],
]);

it('validates only the current wizard step during precognition', function (string $method, string $uri) {
    /** @var TestResponse<Response> $response */
    $response = $this->json($method, $uri, [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'name,email',
    ]);

    $response
        ->assertNoContent()
        ->assertHeader('Precognition', 'true');
})->with([
    'create' => ['POST', '/profiles'],
    'edit' => ['PATCH', '/profiles/1'],
]);

it('returns only current-field errors during wizard precognition', function () {
    $this->postJson('/profiles', [
        'name' => '',
        'email' => 'ada@example.com',
    ], [
        'Precognition' => 'true',
        'Precognition-Validate-Only' => 'name,email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('name')
        ->assertJsonMissingValidationErrors(['skill', 'projects.0.title']);
});
