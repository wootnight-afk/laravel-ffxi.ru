<?php

namespace App\Http\Controllers;

use App\Services\ServerFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Game-server filter of the Players directory (STAGE-2 §6.1).
 *
 * The filter is a viewing preference, so it is changed with POST only: with the
 * database session driver a GET request would write to the sessions table.
 * The handler never touches the active character — the two states are
 * independent in both directions.
 */
class PlayerServerFilterController extends Controller
{
    public function update(Request $request, ServerFilter $filter): RedirectResponse
    {
        $validated = $request->validate([
            'server' => ['nullable', 'string', 'max:64'],
        ]);

        $slug = trim((string) ($validated['server'] ?? ''));

        if ($slug !== '' && ! $filter->accepts($slug)) {
            throw ValidationException::withMessages([
                'server' => __('site.players.server_filter.invalid'),
            ]);
        }

        $filter->set($slug === '' ? null : $slug);

        return redirect()
            ->back(fallback: route('players.directory'))
            ->with('status', __('site.players.server_filter.flash.applied'));
    }
}
