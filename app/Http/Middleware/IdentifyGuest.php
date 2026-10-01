<?php

namespace App\Http\Middleware;

use App\Models\GuestVisitor;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;
use Symfony\Component\HttpFoundation\Response;

class IdentifyGuest
{
    public const COOKIE = 'guest_uid';
    public const COOKIE_MINUTES = 60 * 24 * 30; // 30 days

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        $uuid = $request->cookie(self::COOKIE);
        $visitor = $uuid !== null
            ? GuestVisitor::query()->where('uuid', $uuid)->first()
            : null;

        $setCookie = null;

        if ($visitor === null) {
            $uuid = (string) Str::uuid();

            $visitor = GuestVisitor::create([
                'uuid' => $uuid,
                'display_name' => '',
                'ip_hash' => $this->hashIp($request->ip()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'hits' => 1,
            ]);

            $visitor->display_name = 'guest' . str_pad((string) $visitor->id, 3, '0', STR_PAD_LEFT);
            $visitor->save();

            $setCookie = $uuid;
        } else {
            if ($visitor->last_seen_at === null || $visitor->last_seen_at->diffInSeconds(now()) >= 60) {
                $visitor->forceFill([
                    'last_seen_at' => now(),
                    'hits' => $visitor->hits + 1,
                ])->save();
            }
        }

        $request->attributes->set('guest_visitor', $visitor);

        $response = $next($request);

        if ($setCookie !== null) {
            $response->headers->setCookie(new SymfonyCookie(
                name: self::COOKIE,
                value: $setCookie,
                expire: time() + (self::COOKIE_MINUTES * 60),
                path: '/',
                secure: $request->isSecure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            ));
        }

        return $response;
    }

    protected function hashIp(?string $ip): string
    {
        $key = (string) config('app.key');

        return hash('sha256', ($ip ?? '') . $key);
    }
}
