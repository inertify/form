<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    require __DIR__.'/../../workbench/routes/web.php';
});

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
