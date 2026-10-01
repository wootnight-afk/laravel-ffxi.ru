<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlSanitizer
{
    /** @var array<int, string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'del',
        'ul', 'ol', 'li',
        'blockquote', 'code', 'pre',
        'h2', 'h3', 'h4', 'hr', 'a',
    ];

    /** @var array<int, string> */
    private const DROP_WITH_CONTENT = [
        'script', 'iframe', 'style', 'svg', 'form',
        'object', 'embed', 'link', 'meta', 'base',
    ];

    /** @var array<int, string> */
    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"><div id="__ffxi_root__">'.$html.'</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__ffxi_root__');
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function cleanChildren(DOMNode $node): void
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMText) {
            return;
        }

        if (! $node instanceof DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $name = strtolower($node->nodeName);

        if (in_array($name, self::DROP_WITH_CONTENT, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        if (! in_array($name, self::ALLOWED_TAGS, true)) {
            // Заменяем тег на его текстовое содержимое.
            $this->unwrap($node);

            return;
        }

        $this->cleanAttributes($node);
        $this->cleanChildren($node);

        if ($name === 'a') {
            $this->decorateExternalLink($node);
        }
    }

    private function cleanAttributes(DOMElement $element): void
    {
        $children = [];
        foreach ($element->attributes as $attr) {
            $children[] = $attr->name;
        }

        foreach ($children as $name) {
            $lower = strtolower($name);

            // Оставляем только href у <a>.
            if ($element->nodeName === 'a' && $lower === 'href') {
                $href = $element->getAttribute('href');

                if (! $this->isSafeHref($href)) {
                    $element->removeAttribute('href');

                    continue;
                }

                $element->setAttribute('href', $href);

                continue;
            }

            $element->removeAttribute($name);
        }
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function isSafeHref(string $href): bool
    {
        $href = trim($href);

        if ($href === '') {
            return false;
        }

        // Якорные ссылки без схемы
        if (str_starts_with($href, '#')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            return false;
        }

        return in_array(strtolower($scheme), self::ALLOWED_SCHEMES, true);
    }

    private function decorateExternalLink(DOMElement $link): void
    {
        $href = $link->getAttribute('href');

        if ($href === '' || str_starts_with($href, '#')) {
            return;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);
        $host = parse_url($href, PHP_URL_HOST);

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($scheme === 'mailto' || $host === null || $host === $appHost) {
            return;
        }

        $link->setAttribute('rel', 'nofollow noopener noreferrer');
        $link->setAttribute('target', '_blank');
    }
}
