<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans HTML written in the admin panel (questions, notes, custom pages) before it is stored, because the student site
 * shows it as HTML. Only a short list of harmless tags and attributes survives: no scripts, no event handlers
 * (onclick...), no javascript: links, no unknown iframes. LaTeX formulas ($x<4$, \(..\), $$..$$) are kept exactly.
 */
class HtmlSanitizer
{
    private const TAGS = [
        'p', 'br', 'hr', 'div', 'span', 'b', 'strong', 'i', 'em', 'u', 's', 'sub', 'sup', 'small', 'mark', 'code', 'pre', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'colgroup', 'col',
        'a', 'img', 'figure', 'figcaption', 'iframe', 'details', 'summary',
    ];

    private const ATTRS = [
        '*' => ['class', 'style', 'title', 'id', 'dir', 'lang'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'td' => ['colspan', 'rowspan', 'align'], 'th' => ['colspan', 'rowspan', 'align', 'scope'],
        'col' => ['span', 'width'], 'ol' => ['start', 'type'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'title', 'allow', 'loading'],
    ];

    private const STYLES = ['color', 'background-color', 'background', 'text-align', 'font-size', 'font-weight', 'font-style', 'text-decoration', 'width', 'max-width', 'height',
        'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right', 'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right',
        'border', 'border-radius', 'line-height', 'display', 'vertical-align', 'white-space', 'list-style-type'];

    private const IFRAME_HOSTS = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        // 1. Protect formulas: their < > & must survive exactly (stored escaped, as the student site expects).
        $maths = [];
        $html = preg_replace_callback('/\$\$[\s\S]+?\$\$|\\\\\[[\s\S]+?\\\\\]|\$[^$\n]+?\$|\\\\\([\s\S]+?\\\\\)/', function ($m) use (&$maths) {
            $key = 'MMNMATH'.count($maths).'X';
            $maths[$key] = htmlspecialchars(html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5), ENT_NOQUOTES, 'UTF-8');

            return $key;
        }, $html);

        // 2. Parse and walk the tree.
        $dom = new DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="mmn-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $dom->getElementById('mmn-root');
        if (! $root) {
            return strip_tags($html);
        }
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        // 3. Put the formulas back.
        return strtr($out, $maths);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (! in_array($tag, self::TAGS, true)) {
                    // dangerous containers vanish with their content; harmless unknown tags are unwrapped
                    if (in_array($tag, ['script', 'style', 'object', 'embed', 'applet', 'form', 'meta', 'link', 'base', 'svg', 'math', 'template', 'noscript', 'textarea', 'select', 'option', 'button', 'input'], true)) {
                        $node->removeChild($child);
                    } else {
                        self::walk($child);
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    }

                    continue;
                }
                if (! self::cleanElement($child, $tag)) {
                    $node->removeChild($child);

                    continue;
                }
                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
            }
        }
    }

    /** @return bool false = remove the element */
    private static function cleanElement(DOMElement $el, string $tag): bool
    {
        $allowed = array_merge(self::ATTRS['*'], self::ATTRS[$tag] ?? []);
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($el->hasAttribute('style')) {
            $style = self::cleanStyle($el->getAttribute('style'));
            $style === '' ? $el->removeAttribute('style') : $el->setAttribute('style', $style);
        }

        if ($tag === 'a') {
            $href = self::url($el->getAttribute('href'), ['http', 'https', 'mailto', 'tel']);
            $href === null ? $el->removeAttribute('href') : $el->setAttribute('href', $href);
            if ($el->hasAttribute('target')) {
                $el->setAttribute('target', '_blank');
            }
            $el->setAttribute('rel', 'noopener noreferrer');
        }
        if ($tag === 'img') {
            $src = self::url($el->getAttribute('src'), ['http', 'https'], true);
            if ($src === null) {
                return false;
            }
            $el->setAttribute('src', $src);
            $el->setAttribute('loading', 'lazy');
        }
        if ($tag === 'iframe') {
            $src = self::url($el->getAttribute('src'), ['https']);
            $host = $src ? strtolower((string) parse_url($src, PHP_URL_HOST)) : '';
            if (! in_array($host, self::IFRAME_HOSTS, true)) {
                return false;
            }
            $el->setAttribute('src', $src);
            $el->setAttribute('sandbox', 'allow-scripts allow-same-origin allow-presentation allow-popups');
            $el->setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        }

        return true;
    }

    private static function cleanStyle(string $style): string
    {
        $keep = [];
        foreach (explode(';', $style) as $decl) {
            if (! str_contains($decl, ':')) {
                continue;
            }
            [$prop, $val] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if (! in_array($prop, self::STYLES, true) || $val === '' || preg_match('/url\s*\(|expression|javascript|@import|behavior|binding|[\\\\<>]/i', $val)) {
                continue;
            }
            $keep[] = "{$prop}: {$val}";
        }

        return implode('; ', $keep);
    }

    /** A safe web address, or null. Relative paths ("/x", "#top") are fine. */
    public static function url(?string $url, array $schemes = ['http', 'https'], bool $allowDataImage = false): ?string
    {
        $url = trim((string) $url);
        $compact = preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5));
        if ($compact === '') {
            return null;
        }
        if ($allowDataImage && preg_match('#^data:image/(png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+$#i', $compact)) {
            return $compact;
        }
        if (preg_match('#^([a-z][a-z0-9+.-]*):#i', $compact, $m)) {
            return in_array(strtolower($m[1]), $schemes, true) ? $url : null;
        }

        return str_starts_with($compact, '//') ? null : $url; // "//evil.com" is another site
    }
}
