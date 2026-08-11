<?php

declare(strict_types=1);

namespace Inertify\Form\RichText;

use DOMDocument;
use DOMElement;
use RuntimeException;

final class HtmlImages
{
    private const string WRAPPER_ID = '__inertify_form_fragment__';

    private function __construct(
        private readonly DOMDocument $document,
        private readonly DOMElement $wrapper,
    ) {}

    public static function from(string $html): self
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="'.self::WRAPPER_ID.'">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException('The rich-text HTML could not be parsed.');
        }

        $wrapper = $document->getElementById(self::WRAPPER_ID);

        if (! $wrapper instanceof DOMElement) {
            throw new RuntimeException('The rich-text HTML wrapper could not be found.');
        }

        return new self($document, $wrapper);
    }

    /** @return list<DOMElement> */
    public function imagesWith(string $attribute): array
    {
        $images = [];

        foreach ($this->wrapper->getElementsByTagName('img') as $image) {
            if ($image->hasAttribute($attribute)) {
                $images[] = $image;
            }
        }

        return $images;
    }

    /** @return array<string, string> */
    public static function attributes(DOMElement $element): array
    {
        $attributes = [];

        foreach ($element->attributes as $attribute) {
            $attributes[$attribute->name] = $attribute->value;
        }

        return $attributes;
    }

    /** @param array<string, string> $attributes */
    public static function replaceAttributes(DOMElement $element, array $attributes): void
    {
        $names = [];

        foreach ($element->attributes as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $element->removeAttribute($name);
        }

        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }
    }

    public function toHtml(): string
    {
        $html = '';

        foreach ($this->wrapper->childNodes as $node) {
            $html .= $this->document->saveHTML($node) ?: '';
        }

        return $html;
    }
}
