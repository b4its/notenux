<?php

namespace App\Services;

use Dom\HTMLDocument;

class NoteContentSanitizer
{
    /**
     * Critical studio element IDs that must not be clobbered by user content (CWE-79 / DOM Clobbering).
     */
    public const RESERVED_DOM_IDS = [
        'editorarea', 'plain-text-editor', 'markdownraweditor', 'note-title-input',
        'note-category-select', 'save-now-btn', 'delete-note-btn', 'save-status-text',
        'app-favicon', 'csrf-token', 'studio-dynamic-island', 'dynamic-island-tray',
        'dynamic-island-toggle-btn', 'favorite-note-btn', 'pin-note-btn', 'location',
        'window', 'document', 'form', 'body', 'cookie',
    ];

    public function sanitize(?string $content): string
    {
        if (! $content || ! preg_match('/<[a-z!]/i', $content)) {
            return $content ?? '';
        }

        $document = HTMLDocument::createFromString($content, LIBXML_NOERROR, 'UTF-8');
        $allowed = ['p', 'div', 'span', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'sub', 'sup', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'hr', 'a', 'img', 'input', 'font', 'figure', 'figcaption'];

        foreach (iterator_to_array($document->body->querySelectorAll('*')) as $element) {
            $tag = strtolower($element->tagName);
            if (! in_array($tag, $allowed, true)) {
                $element->remove();

                continue;
            }

            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = $attribute->value;
                $safe = in_array($name, ['class', 'title', 'alt', 'data-align', 'data-placeholder', 'contenteditable', 'data-color-token'], true)
                    || ($name === 'id' && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $value) && ! in_array(strtolower($value), self::RESERVED_DOM_IDS, true))
                    || (in_array($name, ['checked', 'disabled'], true) && $tag === 'input')
                    || ($name === 'type' && $tag === 'input' && $value === 'checkbox')
                    || (in_array($name, ['colspan', 'rowspan', 'width', 'height', 'size', 'start', 'border', 'cellpadding', 'cellspacing'], true) && ctype_digit($value))
                    || (in_array($name, ['color', 'bgcolor', 'bordercolor'], true) && preg_match('/^(#[a-f0-9]{3,8}|[a-z]+|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*[0-9.]+\s*\))$/i', $value))
                    || (in_array($name, ['align', 'valign'], true) && in_array(strtolower($value), ['left', 'center', 'right', 'justify', 'top', 'middle', 'bottom', 'baseline'], true))
                    || ($name === 'style' && preg_match('/^(\s*(color|background-color|background|text-align|vertical-align|border|border-color|border-width|border-style|border-radius|padding|padding-left|padding-right|padding-top|padding-bottom|margin|margin-left|margin-right|margin-top|margin-bottom|width|height|min-width|max-width|min-height|max-height|font-size|font-family|font-weight|font-style|text-decoration|display)\s*:\s*[a-z0-9#(),.%\s\'"-]+;?\s*)+$/i', $value) && ! preg_match('/(expression|behavior|javascript|vbscript|data:|url\s*\()/i', $value))
                    || ($name === 'href' && $tag === 'a' && preg_match('~^(https?://|mailto:|\#|/(?!/))~i', $value))
                    || ($name === 'target' && $tag === 'a' && in_array(strtolower($value), ['_blank', '_self'], true))
                    || ($name === 'rel' && $tag === 'a' && preg_match('/^[a-z\s_-]+$/i', $value))
                    || ($name === 'src' && $tag === 'img' && preg_match('~^(https?://|data:image/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=\s]+$)~is', trim($value)));

                if (! $safe) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if ($tag === 'a' && strtolower((string) $element->getAttribute('target')) === '_blank') {
                $existingRel = trim((string) $element->getAttribute('rel'));
                if (! str_contains($existingRel, 'noopener')) {
                    $element->setAttribute('rel', $existingRel !== '' ? $existingRel.' noopener' : 'noopener');
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
