<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Events\NewsPublishedForActivity;
use App\Exceptions\ImageProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlayerNewsRequest;
use App\Http\Requests\UpdatePlayerNewsRequest;
use App\Models\News;
use App\Services\NewsCoverUploader;
use App\Services\SettingsRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class MyNewsController extends Controller
{
    /** Statuses an author is allowed to freely edit. */
    private const EDITABLE_STATUSES = [
        News::STATUS_DRAFT,
        News::STATUS_PENDING,
        News::STATUS_REJECTED,
    ];

    public function __construct(
        private readonly NewsCoverUploader $coverUploader,
        private readonly SettingsRepository $settings,
    ) {}

    public function store(StorePlayerNewsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $news = News::create([
            'user_id' => $user->id,
            'scope' => News::SCOPE_PLAYER,
            'title' => $data['title'],
            'body' => $data['body'],
            'excerpt' => $data['excerpt'] ?? null,
            'comments_enabled' => (bool) ($data['comments_enabled'] ?? true),
            'status' => News::STATUS_DRAFT,
            'published_at' => null,
        ]);

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', 'Черновик создан.')
            ->with('open_news_id', $news->id);
    }

    public function update(UpdatePlayerNewsRequest $request, News $news): RedirectResponse
    {
        Gate::authorize('update', $news);
        $this->ensureEditable($news);

        $data = $request->validated();
        $wasPublished = $news->status === News::STATUS_PUBLISHED;

        $news->fill([
            'title' => $data['title'],
            'body' => $data['body'],
            'excerpt' => $data['excerpt'] ?? null,
            'comments_enabled' => (bool) ($data['comments_enabled'] ?? $news->comments_enabled),
        ]);

        if (($data['status'] ?? null) === 'published' && $news->status === News::STATUS_DRAFT) {
            $this->applyModeration($news);
        }

        $news->save();

        if (! $wasPublished && $news->status === News::STATUS_PUBLISHED) {
            DB::afterCommit(fn () => event(new NewsPublishedForActivity($news, $request->user())));
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', $this->flashFor($news));
    }

    public function publish(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('update', $news);
        $this->ensureEditable($news);

        if ($news->status !== News::STATUS_DRAFT) {
            return redirect()
                ->route('cabinet.tab', ['tab' => 'news'])
                ->with('status', 'Эту новость нельзя опубликовать повторно.');
        }

        $this->applyModeration($news);
        $news->save();

        if ($news->status === News::STATUS_PUBLISHED) {
            DB::afterCommit(fn () => event(new NewsPublishedForActivity($news, $request->user())));
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', $this->flashFor($news));
    }

    public function destroy(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('delete', $news);
        $this->ensureEditable($news);

        if ($news->cover_path) {
            $this->coverUploader->remove($news);
        }

        $news->delete();

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', 'Новость удалена.');
    }

    public function uploadCover(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('update', $news);
        $this->ensureEditable($news);

        $request->validate([
            'cover' => ['required', 'file', 'max:4096'],
        ]);

        try {
            $this->coverUploader->upload($request->file('cover'), $news);
        } catch (ImageProcessingException $e) {
            return back()->withErrors(['cover' => $e->getMessage()]);
        }

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', 'Обложка обновлена.')
            ->with('open_news_id', $news->id);
    }

    public function deleteCover(Request $request, News $news): RedirectResponse
    {
        Gate::authorize('update', $news);
        $this->ensureEditable($news);

        $this->coverUploader->remove($news);

        return redirect()
            ->route('cabinet.tab', ['tab' => 'news'])
            ->with('status', 'Обложка удалена.')
            ->with('open_news_id', $news->id);
    }

    /**
     * Apply moderation policy to a draft being published.
     */
    private function applyModeration(News $news): void
    {
        $mode = $this->settings->string('player_news_moderation', 'post');

        if ($mode === 'pre') {
            $news->status = News::STATUS_PENDING;
            $news->published_at = null;
            $news->rejection_reason = null;
        } else {
            $news->status = News::STATUS_PUBLISHED;
            $news->published_at = now();
            $news->rejection_reason = null;
        }
    }

    /**
     * @throws HttpResponseException
     */
    private function ensureEditable(News $news): void
    {
        if (! $news->isPlayer() || ! in_array($news->status, self::EDITABLE_STATUSES, true)) {
            abort(403, 'Эту новость нельзя изменить.');
        }
    }

    private function flashFor(News $news): string
    {
        return match ($news->status) {
            News::STATUS_PENDING => 'Новость отправлена на модерацию.',
            News::STATUS_PUBLISHED => 'Новость опубликована.',
            default => 'Новость сохранена.',
        };
    }
}
