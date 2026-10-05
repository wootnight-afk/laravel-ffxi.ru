<div class="space-y-4">
    <h2 class="text-lg font-semibold">{{ $news->title }}</h2>

    @if ($news->excerpt)
        <p class="text-sm text-gray-500">{{ $news->excerpt }}</p>
    @endif

    <div class="prose max-w-none dark:prose-invert">
        {!! $news->body_html !!}
    </div>
</div>
