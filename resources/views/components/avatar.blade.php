@props(['url' => null, 'initial' => '?', 'size' => 48, 'backgroundColor' => '#5fa8ac'])

@if ($url)
    <img
        src="{{ $url }}"
        alt=""
        class="avatar"
        style="width: {{ $size }}px; height: {{ $size }}px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
        loading="lazy"
    >
@else
    <span
        class="avatar avatar--placeholder"
        aria-hidden="true"
        style="display: inline-flex; align-items: center; justify-content: center; width: {{ $size }}px; height: {{ $size }}px; border-radius: 50%; background: {{ $backgroundColor }}; color: #fff; font-weight: 600; font-size: {{ (int) ($size * 0.4) }}px; flex-shrink: 0;"
    >
        {{ $initial }}
    </span>
@endif
