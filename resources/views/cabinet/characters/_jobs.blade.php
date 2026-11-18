<div class="character-jobs">
    <h2 class="section-title">{{ __('site.cabinet.characters.jobs.title') }}</h2>
    <p class="character-jobs__hint">{{ __('site.cabinet.characters.jobs.hint') }}</p>

    <div class="character-jobs__grid">
        @foreach (\App\Enums\GameJob::cases() as $job)
            @php($code = $job->value)
            <div class="character-jobs__row">
                <label for="job-{{ $code }}">{{ $code }}</label>
                <input
                    id="job-{{ $code }}"
                    type="number"
                    name="jobs[{{ $code }}]"
                    class="form-control"
                    min="1"
                    max="99"
                    inputmode="numeric"
                    value="{{ old('jobs.'.$code, $levels[$code] ?? '') }}"
                >
            </div>
        @endforeach
    </div>

    @error('jobs')
        <div class="form-error">{{ $message }}</div>
    @enderror
    @error('jobs.*')
        <div class="form-error">{{ $message }}</div>
    @enderror
</div>
