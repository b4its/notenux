<?php

namespace App\Services;

use Dom\HTMLDocument;

class NoteContentSanitizer
{
    public function sanitize(?string $content): string
    {
        if (! $content || ! preg_match('/<[a-z!]/i', $content)) {
            return $content ?? '';
        }

        $document = HTMLDocument::createFromString($content, LIBXML_NOERROR, 'UTF-8');
        $allowed = ['p', 'div', 'span', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr', 'a', 'img', 'input', 'font'];

        foreach (iterator_to_array($document->body->querySelectorAll('*')) as $element) {
            $tag = strtolower($element->tagName);
            if (! in_array($tag, $allowed, true)) {
                $element->remove();

                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $safe = in_array($name, ['class', 'title', 'alt'], true)
                    || ($name === 'checked' && $tag === 'input')
                    || ($name === 'type' && $tag === 'input' && $value === 'checkbox')
                    || (in_array($name, ['colspan', 'rowspan', 'width', 'height', 'size', 'start'], true) && ctype_digit($value))
                    || ($name === 'color' && preg_match('/^(#[a-f0-9]{3,8}|[a-z]+)$/i', $value))
                    || ($name === 'style' && preg_match('/^(\s*(color|background-color|text-align|font-size|font-family|font-weight|font-style|text-decoration)\s*:\s*[a-z0-9#(),.%\s\'"-]+;?\s*)+$/i', $value))
                    || ($name === 'href' && $tag === 'a' && preg_match('~^(https?://|mailto:|\#|/(?!/))~i', $value))
                    || ($name === 'src' && $tag === 'img' && preg_match('~^https?://~i', $value));

                if (! $safe) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'input' && $element->getAttribute('type') !== 'checkbox') {
                $element->remove();
            }
        }

        $html = '';
        foreach ($document->body->childNodes as $node) {
            $html .= $document->saveHtml($node);
        }

        return $html;
    }
}
