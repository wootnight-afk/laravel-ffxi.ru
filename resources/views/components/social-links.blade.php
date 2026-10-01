@props(['show' => false, 'links' => null])

@if ($show && $links !== null && $links->isNotEmpty())
    <div class="social-links" style="display: flex; gap: 8px; flex-wrap: wrap;">
        @foreach ($links as $link)
            <a
                href="{{ $link->url }}"
                target="_blank"
                rel="nofollow noopener noreferrer"
                class="social-link"
                style="font-size: 13px; padding: 4px 10px; border: 1px solid var(--border-color); border-radius: 20px; text-decoration: none;"
            >
                {{ $link->label ?: $link->type }}
            </a>
        @endforeach
    </div>
@endif
