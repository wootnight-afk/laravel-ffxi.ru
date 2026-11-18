{{--
    Фильтр игрового сервера в боковом меню Players (STAGE-2 §6.1).

    Фильтр — предпочтение просмотра каталога, а не свойство аккаунта: он живёт
    в сессии и не читает и не пишет активного персонажа. Расхождение с
    сервером активного персонажа только показывается; состояние при этом не
    меняется ни в одну сторону.
--}}
<div class="players-context__filter" data-players-server-filter>
    <form method="POST" action="{{ route('players.server-filter.update') }}" class="players-context__form">
        @csrf
        <label class="players-context__label" for="players-server-filter">
            {{ __('site.players.server_filter.label') }}
        </label>
        <div class="players-context__row">
            <select id="players-server-filter" name="server" class="players-context__select">
                <option value="">{{ __('site.players.server_filter.all') }}</option>
                @foreach ($context['servers'] as $server)
                    <option value="{{ $server->slug }}" @selected($context['filter']?->getKey() === $server->getKey())>
                        {{ $server->name }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="players-context__submit">
                {{ __('site.players.server_filter.apply') }}
            </button>
        </div>
    </form>

    @if ($context['mismatch'])
        <p class="players-context__note">{{ __('site.players.server_filter.mismatch') }}</p>
        <form method="POST" action="{{ route('players.server-filter.update') }}">
            @csrf
            <input type="hidden" name="server" value="">
            <button type="submit" class="players-context__reset">
                {{ __('site.players.server_filter.reset') }}
            </button>
        </form>
    @endif
</div>
