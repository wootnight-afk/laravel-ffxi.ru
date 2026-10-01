<?php

use App\Services\HtmlSanitizer;

beforeEach(function () {
    $this->sanitizer = new HtmlSanitizer();
});

it('strips script tags with content', function () {
    $input = '<p>Hello</p><script>alert(1)</script><p>World</p>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('<script');
    expect($output)->not->toContain('alert(1)');
    expect($output)->toContain('<p>Hello</p>');
    expect($output)->toContain('<p>World</p>');
});

it('strips img onerror', function () {
    $input = '<img src="x" onerror="alert(1)">';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('onerror');
    expect($output)->not->toContain('<img');
});

it('blocks javascript: scheme in links', function () {
    $input = '<a href="javascript:alert(1)">click</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('javascript:');
    expect($output)->toContain('click');
});

it('blocks data: scheme in links', function () {
    $input = '<a href="data:text/html,<script>alert(1)</script>">click</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('data:');
    expect($output)->toContain('click');
});

it('blocks vbscript: scheme', function () {
    $input = '<a href="vbscript:msgbox(1)">click</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('vbscript:');
});

it('blocks mixed-case javascript scheme', function () {
    $input = '<a href="jAvAsCrIpT:alert(1)">click</a>';
    $output = $this->sanitizer->sanitize($input);

    expect(strtolower($output))->not->toContain('javascript:');
});

it('strips iframe', function () {
    $input = '<iframe src="https://evil.com"></iframe>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('<iframe');
});

it('strips svg with onload', function () {
    $input = '<svg onload="alert(1)"><circle /></svg>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('<svg');
    expect($output)->not->toContain('onload');
});

it('strips style tags', function () {
    $input = '<style>body{display:none}</style><p>text</p>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('<style');
    expect($output)->toContain('text');
});

it('removes all event attributes', function () {
    $input = '<p onclick="alert(1)" onmouseover="alert(2)">text</p>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('onclick');
    expect($output)->not->toContain('onmouseover');
    expect($output)->toContain('text');
});

it('removes style class id data attributes', function () {
    $input = '<p style="color:red" class="x" id="y" data-foo="bar">text</p>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('style=');
    expect($output)->not->toContain('class=');
    expect($output)->not->toContain('id=');
    expect($output)->not->toContain('data-foo');
});

it('allows safe tags', function () {
    $input = '<p><strong>bold</strong> <em>italic</em> <del>gone</del></p><ul><li>item</li></ul><blockquote>quote</blockquote><pre><code>code</code></pre>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->toContain('<strong>bold</strong>');
    expect($output)->toContain('<em>italic</em>');
    expect($output)->toContain('<del>gone</del>');
    expect($output)->toContain('<li>item</li>');
    expect($output)->toContain('<blockquote>quote</blockquote>');
    expect($output)->toContain('<code>code</code>');
});

it('adds rel and target to external links', function () {
    $input = '<a href="https://example.com">external</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->toContain('rel="nofollow noopener noreferrer"');
    expect($output)->toContain('target="_blank"');
});

it('does not add rel to internal anchor links', function () {
    $input = '<a href="#section">anchor</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->not->toContain('rel=');
    expect($output)->not->toContain('target=');
});

it('allows mailto links', function () {
    $input = '<a href="mailto:test@example.com">mail</a>';
    $output = $this->sanitizer->sanitize($input);

    expect($output)->toContain('mailto:test@example.com');
});

it('handles empty input', function () {
    expect($this->sanitizer->sanitize(''))->toBe('');
    expect($this->sanitizer->sanitize('   '))->toBe('');
});

it('handles plain text', function () {
    expect($this->sanitizer->sanitize('Hello world'))->toBe('Hello world');
});
