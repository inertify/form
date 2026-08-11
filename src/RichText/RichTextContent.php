<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use Closure;
use DOMElement;
use UnexpectedValueException;

final class RichTextContent
{
    private ?Closure $replaceImages = null;

    private function __construct(private readonly ?string $html) {}

    public static function from(?string $html): self
    {
        return new self($html);
    }

    public function replaceImagesUsing(Closure $callback): self
    {
        $this->replaceImages = $callback;

        return $this;
    }

    public function toHtml(): ?string
    {
        if ($this->html === null || $this->replaceImages === null) {
            return $this->html;
        }

        $fragment = HtmlImages::from($this->html);

        foreach ($fragment->imagesWith(RichTextMarker::STORED_ATTRIBUTE) as $element) {
            $this->replaceImage($element);
        }

        return $fragment->toHtml();
    }

    private function replaceImage(DOMElement $element): void
    {
        $attributes = HtmlImages::attributes($element);
        $payload = RichTextMarker::decode($attributes[RichTextMarker::STORED_ATTRIBUTE]);
        $stored = new RichTextStoredImage(
            $payload['identifier'],
            $payload['metadata'],
            $attributes,
        );
        $image = RichTextImage::fromAttributes($attributes);
        $replacement = ($this->replaceImages)($stored, $image);

        if ($replacement === null) {
            return;
        }

        if (! $replacement instanceof RichTextImage) {
            throw new UnexpectedValueException('The rich-text image callback must return RichTextImage or null.');
        }

        HtmlImages::replaceAttributes($element, $replacement->toAttributes());
    }
}
