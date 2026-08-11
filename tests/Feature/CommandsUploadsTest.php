<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertify\Form\Uploads\UploadManager;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
});

it('generates a form class with the make form command', function (): void {
    $path = app_path('Forms/GeneratedByPackageTestForm.php');
    File::delete($path);

    $this->artisan('make:form', ['name' => 'GeneratedByPackageTestForm'])
        ->assertSuccessful();

    expect(File::get($path))->toContain('class GeneratedByPackageTestForm extends Form')
        ->toContain('public function fields(): array');

    File::delete($path);
});

it('removes expired temporary upload directories with the cleanup command', function (): void {
    Storage::fake('cleanup-uploads');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'cleanup-uploads');

    $stored = app(UploadManager::class)->store(
        UploadedFile::fake()->createWithContent('expired.txt', 'expired'),
    );

    Storage::disk('cleanup-uploads')->assertExists($stored->getPath());

    $this->artisan('form:cleanup-uploads', ['--lifetime' => 0])
        ->expectsOutputToContain('Removed 1 expired upload')
        ->assertSuccessful();

    Storage::disk('cleanup-uploads')->assertMissing($stored->getPath());
});

it('reports cleanup failures after continuing with other package uploads', function (): void {
    Storage::fake('cleanup-report');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'cleanup-report');
    $manager = app(UploadManager::class);
    $corrupt = $manager->store(UploadedFile::fake()->createWithContent('corrupt.txt', 'corrupt'));
    $valid = $manager->store(UploadedFile::fake()->createWithContent('valid.txt', 'valid'));
    $corruptDirectory = dirname($corrupt->getPath());
    Storage::disk('cleanup-report')->put($corruptDirectory.'/.metadata.json', 'invalid-json');

    $this->artisan('form:cleanup-uploads', ['--lifetime' => 0])
        ->expectsOutputToContain('Removed 1 expired upload')
        ->expectsOutputToContain('1 upload cleanup operation failed and can be retried')
        ->assertFailed();

    Storage::disk('cleanup-report')->assertExists($corruptDirectory.'/.metadata.json');
    Storage::disk('cleanup-report')->assertMissing($valid->getPath());
});

it('never deletes paths outside an allow-listed package upload directory', function (): void {
    Storage::fake('cleanup-safe-paths');
    config()->set('inertia-forms.file_uploads.temporary_uploads.disk', 'cleanup-safe-paths');
    $identifier = '00000000-0000-4000-8000-000000000000';
    $directory = 'inertia-forms-upload-'.$identifier;
    Storage::disk('cleanup-safe-paths')->put('keep/victim.txt', 'keep');
    Storage::disk('cleanup-safe-paths')->put($directory.'/.metadata.json', json_encode([
        'identifier' => $identifier,
        'path' => 'keep/victim.txt',
        'created_at' => 1,
    ], JSON_THROW_ON_ERROR));
    Storage::disk('cleanup-safe-paths')->put('inertia-forms-upload-not-owned/file.txt', 'keep');

    $report = app(UploadManager::class)->cleanupReport(0);

    expect($report['removed'])->toBe(0)
        ->and($report['failed'])->toBe(1);
    Storage::disk('cleanup-safe-paths')->assertExists('keep/victim.txt');
    Storage::disk('cleanup-safe-paths')->assertExists($directory.'/.metadata.json');
    Storage::disk('cleanup-safe-paths')->assertExists('inertia-forms-upload-not-owned/file.txt');
});
