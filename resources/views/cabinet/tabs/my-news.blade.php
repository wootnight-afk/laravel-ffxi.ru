@php
    /** @var \App\Models\User $user */

    $moderationMode = app(\App\Services\SettingsRepository::class)
        ->string('player_news_moderation', 'post');

    $news = $user->news()
        ->where('scope', \App\Models\News::SCOPE_PLAYER)
        ->orderByDesc('created_at')
        ->get();

    $statusLabels = [
        \App\Models\News::STATUS_DRAFT => 'Черновик',
        \App\Models\News::STATUS_PENDING => 'На модерации',
        \App\Models\News::STATUS_PUBLISHED => 'Опубликовано',
        \App\Models\News::STATUS_REJECTED => 'Отклонено',
        \App\Models\News::STATUS_ARCHIVED => 'В архиве',
    ];

    $statusColors = [
        \App\Models\News::STATUS_DRAFT => '#6b7280',
        \App\Models\News::STATUS_PENDING => '#d97706',
        \App\Models\News::STATUS_PUBLISHED => '#16a34a',
        \App\Models\News::STATUS_REJECTED => '#dc2626',
        \App\Models\News::STATUS_ARCHIVED => '#6b7280',
    ];

    $openId = (int) session('open_news_id', 0);
@endphp

@if (session('status'))
    <div class="form-status" style="margin-bottom: 16px;">{{ session('status') }}</div>
@endif

<h2 class="section-title" style="margin-top: 0;">Мои новости</h2>

<p style="color: var(--text-muted); font-size: 13px; margin-bottom: 16px;">
    Здесь вы пишете свои заметки. Они появляются на вашей странице игрока.
    @if ($moderationMode === 'pre')
        Публикация проходит проверку модератором.
    @endif
</p>

{{-- ================= Create new ================= --}}
<details style="margin-bottom: 24px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card);">
    <summary style="padding: 12px 16px; cursor: pointer; font-weight: 600; font-size: 14px;">
        + Написать новость
    </summary>

    <form
        method="POST"
        action="{{ route('cabinet.news.store') }}"
        style="padding: 16px; border-top: 1px solid var(--border-color); max-width: 720px;"
    >
        @csrf

        <div class="form-group">
            <label for="create_title">Заголовок</label>
            <input
                type="text"
                id="create_title"
                name="title"
                class="form-control"
                value="{{ old('title') }}"
                minlength="3"
                maxlength="150"
                required
            >
            @error('title') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="create_body">Текст (Markdown)</label>
            <textarea
                id="create_body"
                name="body"
                class="form-control"
                rows="10"
                minlength="20"
                maxlength="50000"
                required
                style="resize: vertical; font-family: inherit;"
            >{{ old('body') }}</textarea>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                Поддерживается Markdown: **жирный**, *курсив*, списки, ссылки.
            </div>
            @error('body') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label for="create_excerpt">Анонс (опционально)</label>
            <input
                type="text"
                id="create_excerpt"
                name="excerpt"
                class="form-control"
                value="{{ old('excerpt') }}"
                maxlength="300"
                placeholder="Если пусто — сгенерируется автоматически"
            >
            @error('excerpt') <div class="form-error">{{ $message }}</div> @enderror
        </div>

        <div class="form-group">
            <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                <input type="checkbox" name="comments_enabled" value="1" {{ old('comments_enabled', true) ? 'checked' : '' }}>
                Разрешить комментарии
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Создать черновик</button>
    </form>
</details>

{{-- ================= List ================= --}}
@if ($news->isEmpty())
    <p style="color: var(--text-muted); font-size: 14px;">У вас пока нет новостей.</p>
@else
    <div style="display: flex; flex-direction: column; gap: 12px;">
        @foreach ($news as $item)
            @php
                $canEdit = in_array($item->status, [
                    \App\Models\News::STATUS_DRAFT,
                    \App\Models\News::STATUS_PENDING,
                    \App\Models\News::STATUS_REJECTED,
                ], true);
            @endphp

            <article style="border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-card); overflow: hidden;">
                <div style="padding: 12px 16px; display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap;">
                    <span style="flex-shrink: 0; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: #fff; background: {{ $statusColors[$item->status] ?? '#6b7280' }};">
                        {{ $statusLabels[$item->status] ?? $item->status }}
                    </span>

                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-size: 14px; font-weight: 600;">{{ $item->title }}</div>
                        @if ($item->excerpt)
                            <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">{{ $item->excerpt }}</div>
                        @endif
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                            {{ $item->created_at->format('d.m.Y H:i') }}
                            @if ($item->status === \App\Models\News::STATUS_PUBLISHED && $item->published_at)
                                · опубликовано {{ $item->published_at->format('d.m.Y H:i') }}
                                · просмотров: {{ $item->views }}
                            @endif
                        </div>
                        @if ($item->status === \App\Models\News::STATUS_REJECTED && $item->rejection_reason)
                            <div style="font-size: 12px; color: #dc2626; margin-top: 6px;">
                                Причина: {{ $item->rejection_reason }}
                            </div>
                        @endif
                    </div>

                    <div style="display: flex; gap: 6px; flex-wrap: wrap; flex-shrink: 0;">
                        @if ($canEdit)
                            @if ($item->status === \App\Models\News::STATUS_DRAFT)
                                <form method="POST" action="{{ route('cabinet.news.publish', $item) }}">
                                    @csrf
                                    <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px; background: #16a34a; color: #fff; border-color: #16a34a;">
                                        Опубликовать
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('cabinet.news.destroy', $item) }}" onsubmit="return confirm('Удалить новость?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px; color: #dc2626;">
                                    Удалить
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($canEdit)
                    <details @if ($openId === $item->id) open @endif style="border-top: 1px solid var(--border-color);">
                        <summary style="padding: 8px 16px; cursor: pointer; font-size: 13px; color: var(--primary-color);">
                            Редактировать
                        </summary>

                        <form
                            method="POST"
                            action="{{ route('cabinet.news.update', $item) }}"
                            style="padding: 12px 16px; max-width: 720px;"
                        >
                            @csrf
                            @method('PATCH')

                            <div class="form-group">
                                <label>Заголовок</label>
                                <input type="text" name="title" class="form-control" value="{{ $item->title }}" minlength="3" maxlength="150" required>
                            </div>

                            <div class="form-group">
                                <label>Текст (Markdown)</label>
                                <textarea name="body" class="form-control" rows="10" minlength="20" maxlength="50000" required style="resize: vertical; font-family: inherit;">{{ $item->body }}</textarea>
                            </div>

                            <div class="form-group">
                                <label>Анонс</label>
                                <input type="text" name="excerpt" class="form-control" value="{{ $item->excerpt }}" maxlength="300">
                            </div>

                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                    <input type="checkbox" name="comments_enabled" value="1" {{ $item->comments_enabled ? 'checked' : '' }}>
                                    Разрешить комментарии
                                </label>
                            </div>

                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <button type="submit" name="status" value="draft" class="btn">Сохранить черновик</button>
                                @if ($item->status === \App\Models\News::STATUS_DRAFT)
                                    <button type="submit" name="status" value="published" class="btn btn-primary">
                                        {{ $moderationMode === 'pre' ? 'Отправить на модерацию' : 'Опубликовать' }}
                                    </button>
                                @endif
                            </div>
                        </form>

                        {{-- Cover --}}
                        <div style="padding: 12px 16px; border-top: 1px dashed var(--border-color); max-width: 720px;">
                            <div style="font-size: 13px; font-weight: 600; margin-bottom: 8px;">Обложка</div>

                            @if ($item->cover_path)
                                <div style="margin-bottom: 8px;">
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item->cover_path) }}"
                                        alt=""
                                        style="width: 100%; max-width: 360px; aspect-ratio: 16/9; object-fit: cover; border-radius: 6px; border: 1px solid var(--border-color);"
                                    >
                                </div>
                                <form method="POST" action="{{ route('cabinet.news.cover.delete', $item) }}" style="margin-bottom: 8px;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px; color: #dc2626;" onclick="return confirm('Удалить обложку?');">
                                        Удалить обложку
                                    </button>
                                </form>
                            @endif

                            <form method="POST" action="{{ route('cabinet.news.cover', $item) }}" enctype="multipart/form-data" style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
                                @csrf
                                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" required style="font-size: 12px;">
                                <button type="submit" class="btn" style="font-size: 12px; padding: 4px 10px;">Загрузить</button>
                            </form>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                JPEG/PNG/WebP, до 4 МБ. Будет обрезана до 1280×720.
                            </div>
                        </div>
                    </details>
                @endif
            </article>
        @endforeach
    </div>
@endif
