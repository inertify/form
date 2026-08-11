<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertify\Form\RichText\RichTextContent;
use Inertify\Form\RichText\RichTextImage;
use Inertify\Form\RichText\RichTextStoredImage;
use Inertify\Form\RichText\RichTextUploads;
use Inertify\Form\Uploads\Exceptions\InvalidUploadToken;
use Inertify\Form\Uploads\SubmittedUpload;
use Inertify\Form\Uploads\UploadManager;

it('stores validated rich-text image tokens and rewrites their attributes', function () {
    $manager = app(UploadManager::class);
    $temporary = $manager->store(UploadedFile::fake()->create('diagram.png', 12, 'image/png'));
    $token = $manager->tokenFor($temporary);
    $request = Request::create('/posts', 'POST', [
        'body' => '<p>Hello</p><img src="blob:preview" width="320" data-inertia-forms-upload="'.$token.'">',
        'body_images' => [$token],
    ]);

    $html = RichTextUploads::from($request, 'body')
        ->storeImagesUsing(function (SubmittedUpload $upload, RichTextImage $image): RichTextImage {
            expect($upload->getName())->toBe('diagram.png');

            return $image
                ->src('/content/diagram.png')
                ->identifier('image-42', ['tenant' => ['id' => 7]]);
        })
        ->toHtml();

    expect($html)
        ->toContain('src="/content/diagram.png"')
        ->toContain('width="320"')
        ->toContain('data-inertia-forms-image="v1.')
        ->not->toContain('data-inertia-forms-upload');

    expect(fn () => $manager->resolve($token))->toThrow(InvalidUploadToken::class);
});

it('rejects tokens that are not represented in the submitted html', function () {
    $request = Request::create('/posts', 'POST', [
        'body' => '<p>No image</p>',
        'body_images' => ['untrusted-token'],
    ]);

    expect(fn () => RichTextUploads::from($request, 'body')
        ->storeImagesUsing(fn (SubmittedUpload $upload, RichTextImage $image) => $image)
        ->toHtml())
        ->toThrow(ValidationException::class);
});

it('resolves durable rich-text image markers without owning the rendered markup', function () {
    $original = RichTextImage::fromAttributes(['alt' => 'Diagram', 'width' => '160'])
        ->identifier('image-42', ['tenant' => ['id' => 7]])
        ->toAttributes();
    $marker = htmlspecialchars($original['data-inertia-forms-image'], ENT_QUOTES);
    $html = '<img alt="Diagram" width="160" data-inertia-forms-image="'.$marker.'">';

    $rendered = RichTextContent::from($html)
        ->replaceImagesUsing(function (RichTextStoredImage $stored, RichTextImage $image): RichTextImage {
            expect($stored->identifier())->toBe('image-42')
                ->and($stored->meta('tenant.id'))->toBe(7)
                ->and($stored->attributes()['width'])->toBe('160');

            return $image->src('/images/42');
        })
        ->toHtml();

    expect($rendered)->toContain('src="/images/42"')
        ->and(RichTextContent::from(null)->toHtml())->toBeNull();
});
