@php
    /** @var \App\Models\User $user */

    $albums = \App\Models\Album::query()
        ->where('user_id', $user->id)
        ->where('scope', \App\Models\Album::SCOPE_PLAYER)
        ->with(['photos' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
        ->orderBy('sort_order')
        ->orderByDesc('id')
        ->get();

    $openId = (int) session('open_album_id', 0);

    $maxAlbums = app(\App\Services\SettingsRepository::class)
        ->int('gallery_max_albums_per_user', 10);
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">{{ __('site.cabinet.gallery.title') }}</h2>

<p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
    {{ __('site.cabinet.gallery.intro', ['max' => $maxAlbums]) }}
</p>

{{-- ================= Create album ================= --}}
@if ($albums->count() < $maxAlbums)
    <details style="margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card);">
        <summary style="padding: 12px 16px; cursor: pointer; font-weight: 600; font-size: 14px;">
            {{ __('site.cabinet.gallery.create_album') }}
        </summary>

        <form
            method="POST"
            action="{{ route('cabinet.gallery.albums.store') }}"
            style="padding: 16px; border-top: 1px solid var(--border-color); max-width: 720px; box-sizing: border-box;"
        >
            @csrf

            <div class="form-group">
                <label for="album_title">{{ __('site.cabinet.gallery.name') }}</label>
                <input type="text" id="album_title" name="title" class="form-control" value="{{ old('title') }}" minlength="3" maxlength="120" required>
                @error('title') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="album_description">{{ __('site.cabinet.gallery.description') }}</label>
                <textarea id="album_description" name="description" class="form-control" rows="3" maxlength="2000" style="resize: vertical;">{{ old('description') }}</textarea>
                @error('description') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                    <input type="checkbox" name="is_published" value="1" {{ old('is_published', true) ? 'checked' : '' }}>
                    {{ __('site.cabinet.gallery.show_in_profile') }}
                </label>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('site.cabinet.gallery.create') }}</button>
        </form>
    </details>
@else
    <div style="padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-light); font-size: 13px; color: var(--text-muted); margin-bottom: 24px;">
        {{ __('site.cabinet.gallery.limit_reached', ['max' => $maxAlbums]) }}
    </div>
@endif

{{-- ================= Albums list ================= --}}
@if ($albums->isEmpty())
    <p style="color: var(--text-muted); font-size: 14px;">{{ __('site.cabinet.gallery.empty') }}</p>
@else
    <div style="display: flex; flex-direction: column; gap: 16px;">
        @foreach ($albums as $album)
            <article style="border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card); overflow: hidden;">
                <details @if ($openId === $album->id) open @endif>
                    <summary style="padding: 12px 16px; cursor: pointer; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <span style="flex: 1; min-width: 200px; font-size: 14px; font-weight: 600;">
                            {{ $album->title }}
                            @if (! $album->is_published)
                                <span style="font-size: 11px; color: var(--text-muted); font-weight: 400; margin-left: 6px;">{{ __('site.cabinet.gallery.hidden') }}</span>
                            @endif
                        </span>
                        <span style="font-size: 12px; color: var(--text-muted);">
                            {{ trans_choice('site.cabinet.gallery.photos_count', $album->photos->count()) }}
                        </span>
                    </summary>

                    {{-- Album actions --}}
                    <div style="padding: 12px 16px; border-top: 1px solid var(--border-color); display: flex; gap: 6px; flex-wrap: wrap;">
                        <form method="POST" action="{{ route('cabinet.gallery.albums.update', $album) }}" style="display: contents;">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="title" value="{{ $album->title }}">
                            <input type="hidden" name="description" value="{{ $album->description }}">
                            <input type="hidden" name="is_published" value="{{ $album->is_published ? '0' : '1' }}">
                            <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px;">
                                {{ $album->is_published ? __('site.cabinet.gallery.hide') : __('site.cabinet.gallery.show') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('cabinet.gallery.albums.destroy', $album) }}" onsubmit="return confirm('{{ __('site.cabinet.gallery.delete_confirm') }}');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px; color: #dc2626;">
                                {{ __('site.cabinet.gallery.delete') }}
                            </button>
                        </form>
                    </div>

                    {{-- Edit album --}}
                    <details style="border-top: 1px solid var(--border-color);">
                        <summary style="padding: 8px 16px; cursor: pointer; font-size: 13px; color: var(--primary-color);">
                            {{ __('site.cabinet.gallery.edit') }}
                        </summary>
                        <form method="POST" action="{{ route('cabinet.gallery.albums.update', $album) }}" style="padding: 12px 16px; max-width: 720px; box-sizing: border-box;">
                            @csrf
                            @method('PATCH')

                            <div class="form-group">
                                <label>{{ __('site.cabinet.gallery.name') }}</label>
                                <input type="text" name="title" class="form-control" value="{{ $album->title }}" minlength="3" maxlength="120" required>
                            </div>

                            <div class="form-group">
                                <label>{{ __('site.cabinet.gallery.description_label') }}</label>
                                <textarea name="description" class="form-control" rows="3" maxlength="2000" style="resize: vertical;">{{ $album->description }}</textarea>
                            </div>

                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                    <input type="checkbox" name="is_published" value="1" {{ $album->is_published ? 'checked' : '' }}>
                                    {{ __('site.cabinet.gallery.show_in_profile') }}
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">{{ __('site.cabinet.gallery.save') }}</button>
                        </form>
                    </details>

                    {{-- Upload photos --}}
                    <div style="padding: 12px 16px; border-top: 1px dashed var(--border-color);">
                        <form
                            method="POST"
                            action="{{ route('cabinet.gallery.photos.upload', $album) }}"
                            enctype="multipart/form-data"
                            style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;"
                        >
                            @csrf
                            <input
                                type="file"
                                name="photos[]"
                                accept="image/jpeg,image/png,image/webp,image/gif"
                                multiple
                                required
                                style="font-size: 12px; max-width: 100%;"
                            >
                            <button type="submit" class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">
                                {{ __('site.cabinet.gallery.upload') }}
                            </button>
                            <span style="font-size: 12px; color: var(--text-muted); flex-basis: 100%;">
                                {{ __('site.cabinet.gallery.upload_hint') }}
                            </span>
                        </form>
                    </div>

                    {{-- Photos grid --}}
                    @if ($album->photos->isNotEmpty())
                        <div style="padding: 12px 16px; border-top: 1px solid var(--border-color);">
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px;">
                                @foreach ($album->photos as $photo)
                                    <div style="border: 1px solid var(--border-color); border-radius: 6px; overflow: hidden; background: var(--bg-body);">
                                        <img
                                            src="{{ $photo->urlThumb() }}"
                                            alt="{{ $photo->caption ?? '' }}"
                                            loading="lazy"
                                            style="display: block; width: 100%; aspect-ratio: 1/1; object-fit: cover;"
                                        >

                                        <div style="padding: 6px 8px;">
                                            <form method="POST" action="{{ route('cabinet.gallery.photos.update', $photo) }}" style="display: flex; flex-direction: column; gap: 6px;">
                                                @csrf
                                                @method('PATCH')

                                                <input
                                                    type="text"
                                                    name="caption"
                                                    value="{{ $photo->caption }}"
                                                    maxlength="200"
                                                    placeholder="{{ __('site.cabinet.gallery.caption_placeholder') }}"
                                                    style="font-size: 12px; padding: 4px 6px; border: 1px solid var(--border-color); border-radius: 4px; width: 100%; box-sizing: border-box; background: var(--bg-card); color: var(--text-main);"
                                                >

                                                <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                    <button type="submit" class="btn" style="font-size: 11px; padding: 2px 8px;">{{ __('site.cabinet.gallery.save') }}</button>
                                                    <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: var(--text-muted);">
                                                        <input type="hidden" name="is_published" value="0">
                                                        <input type="checkbox" name="is_published" value="1" {{ $photo->is_published ? 'checked' : '' }}>
                                                        {{ __('site.cabinet.gallery.visible') }}
                                                    </label>
                                                </div>
                                            </form>

                                            <form method="POST" action="{{ route('cabinet.gallery.photos.destroy', $photo) }}" onsubmit="return confirm('{{ __('site.cabinet.gallery.delete_photo_confirm') }}');" style="margin-top: 6px;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn" style="font-size: 11px; padding: 2px 8px; color: #dc2626;">
                                                    {{ __('site.cabinet.gallery.delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </details>
            </article>
        @endforeach
    </div>
@endif
