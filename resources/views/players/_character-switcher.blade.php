{{--
    Активный персонаж аккаунта в боковом меню Players (STAGE-2 §6.1).

    Выбор — нативный select и обычная отправка формы: блок работает без
    JavaScript и не создаёт переполнения карточки при большом числе персонажей.
    Данные готовит CharacterSwitcher и передаёт их из players/_section-nav,
    поэтому сервис вызывается один раз на страницу.
--}}
<div class="players-context" data-players-context>
    <div class="players-context__current">
        <span class="players-context__account">{{ auth()->user()?->name }}</span>
        <span class="players-context__sep" aria-hidden="true">/</span>
        <span>{{ $context['active']?->server?->name ?? __('site.players.context.none') }}</span>
        <span class="players-context__sep" aria-hidden="true">/</span>
        <span class="players-context__character">{{ $context['active']?->name ?? __('site.players.context.not_selected') }}</span>
    </div>

    @if ($context['characters']->isEmpty())
        <p class="players-context__hint">{{ __('site.players.context.no_characters') }}</p>
    @else
        <form method="POST" action="{{ route('players.active-character.store') }}" class="players-context__form">
            @csrf
            <label class="players-context__label" for="players-active-character">
                {{ __('site.players.context.label') }}
            </label>
            <div class="players-context__row">
                <select id="players-active-character" name="character_id" class="players-context__select">
                    @foreach ($context['characters'] as $character)
                        <option value="{{ $character->getKey() }}" @selected($context['active']?->getKey() === $character->getKey())>
                            {{ $character->server?->name }} · {{ $character->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="players-context__submit">
                    {{ __('site.players.context.switch') }}
                </button>
            </div>
        </form>
    @endif

    <a class="players-context__link" href="{{ route('cabinet.characters.index') }}">
        {{ __('site.players.context.manage') }}
    </a>
</div>
