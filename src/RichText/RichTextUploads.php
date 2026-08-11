<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use Closure;
use DOMElement;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertify\Form\Uploads\SubmittedUpload;
use Inertify\Form\Uploads\UploadManager;
use Inertify\Form\Uploads\UploadResolver;
use RuntimeException;
use UnexpectedValueException;

final class RichTextUploads
{
    private ?Closure $storeImages = null;

    private bool $keepTokenized = false;

    private function __construct(
        private readonly Request $request,
        private readonly string $field,
    ) {}

    public static function from(Request $request, string $field): self
    {
        return new self($request, $field);
    }

    public static function imageUploadFieldName(string $field): string
    {
        return $field.'_images';
    }

    public function storeImagesUsing(Closure $callback): self
    {
        $this->storeImages = $callback;

        return $this;
    }

    public function storeImagesInMediaLibrary(
        object $model,
        string $collection = 'default',
        ?string $disk = null,
    ): self {
        return $this->storeImagesUsing(function (SubmittedUpload $upload, RichTextImage $image) use ($model, $collection, $disk): RichTextImage {
            $remote = $upload->getRemoteFile();

            if ($remote !== null && method_exists($model, 'addMediaFromDisk')) {
                $adder = $model->addMediaFromDisk($remote->getPath(), $remote->getDisk());
            } elseif (($file = $upload->getUploadedFile()) !== null && method_exists($model, 'addMedia')) {
                $adder = $model->addMedia($file);
            } else {
                throw new RuntimeException('Media Library uploads require a compatible media model.');
            }

            if (is_object($adder) && method_exists($adder, 'usingFileName')) {
                $adder = $adder->usingFileName($upload->getName());
            }

            if (! is_object($adder) || ! method_exists($adder, 'toMediaCollection')) {
                throw new RuntimeException('The media model returned an incompatible file adder.');
            }

            $media = $disk === null || $disk === ''
                ? $adder->toMediaCollection($collection)
                : $adder->toMediaCollection($collection, $disk);

            if (! is_object($media)) {
                throw new RuntimeException('Media Library did not return the stored media model.');
            }

            $identifier = method_exists($media, 'getKey') ? $media->getKey() : ($media->id ?? null);

            if (! is_int($identifier) && ! is_string($identifier)) {
                throw new RuntimeException('The stored media item does not expose a scalar identifier.');
            }

            if (method_exists($media, 'getUrl')) {
                $image->src((string) $media->getUrl());
            }

            return $image->identifier('media:'.$identifier, [
                'id' => $identifier,
                'collection' => $collection,
            ]);
        });
    }

    public function keepTokenized(bool $keep = true): self
    {
        $this->keepTokenized = $keep;

        return $this;
    }

    public function toHtml(): ?string
    {
        $html = $this->request->input($this->field);

        if ($html === null) {
            return null;
        }

        if (! is_string($html)) {
            throw ValidationException::withMessages([
                $this->field => ['The rich-text value must be a string.'],
            ]);
        }

        $fragment = HtmlImages::from($html);
        $elements = $fragment->imagesWith(RichTextMarker::UPLOAD_ATTRIBUTE);
        $tokens = $this->tokens();
        $markers = array_map(
            static fn (DOMElement $element): string => $element->getAttribute(RichTextMarker::UPLOAD_ATTRIBUTE),
            $elements,
        );

        $this->assertTokensMatch($tokens, $markers);

        if ($markers === []) {
            return $html;
        }

        if ($this->storeImages === null) {
            throw new RuntimeException('Call storeImagesUsing() before storing rich-text uploads.');
        }

        $uploads = app(UploadResolver::class)
            ->ordered($this->request, self::imageUploadFieldName($this->field))
            ->keyBy(static fn (SubmittedUpload $upload): string => $upload->getKey());

        foreach ($elements as $element) {
            $token = $element->getAttribute(RichTextMarker::UPLOAD_ATTRIBUTE);
            $upload = $uploads->get($token);

            if (! $upload instanceof SubmittedUpload) {
                throw ValidationException::withMessages([
                    self::imageUploadFieldName($this->field) => ['An image upload token is invalid.'],
                ]);
            }

            $attributes = HtmlImages::attributes($element);
            $image = RichTextImage::fromAttributes($attributes)
                ->attribute(RichTextMarker::UPLOAD_ATTRIBUTE, null);
            $replacement = ($this->storeImages)($upload, $image);

            if ($replacement !== null && ! $replacement instanceof RichTextImage) {
                throw new UnexpectedValueException('The rich-text upload callback must return RichTextImage or null.');
            }

            $replacement ??= $image;

            if ($this->keepTokenized && isset($replacement->toAttributes()[RichTextMarker::STORED_ATTRIBUTE])) {
                $replacement->src(null);
            }

            HtmlImages::replaceAttributes($element, $replacement->toAttributes());
        }

        $rewritten = $fragment->toHtml();
        $manager = app(UploadManager::class);

        foreach (array_unique($markers) as $token) {
            $manager->delete($token);
        }

        return $rewritten;
    }

    /** @return list<string> */
    private function tokens(): array
    {
        $field = self::imageUploadFieldName($this->field);
        $value = $this->request->input($field, []);
        $values = is_array($value) ? $value : [$value];
        $tokens = [];

        foreach ($values as $token) {
            if (! is_string($token) || $token === '') {
                throw ValidationException::withMessages([
                    $field => ['The image upload token list is invalid.'],
                ]);
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param  list<string>  $tokens
     * @param  list<string>  $markers
     */
    private function assertTokensMatch(array $tokens, array $markers): void
    {
        $expected = array_values(array_unique($tokens));
        $actual = array_values(array_unique($markers));
        sort($expected);
        sort($actual);

        if ($expected !== $actual) {
            throw ValidationException::withMessages([
                self::imageUploadFieldName($this->field) => [
                    'The rich-text image tokens do not match the submitted content.',
                ],
            ]);
        }
    }
}
