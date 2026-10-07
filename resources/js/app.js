// Public site scripts.
//
// The cookie banner is rendered on public pages where no Livewire component
// (and therefore no Alpine runtime) is present, so it cannot rely on Alpine
// directives. It is wired here with plain DOM APIs instead.

function dismissCookieBanner(banner) {
    // Persist first, hide second: a DOM error must never skip the cookie write.
    // This cookie is set by JavaScript as a plain value and is excluded from
    // Laravel's cookie encryption (see bootstrap/app.php).
    document.cookie = 'cookie_banner_ack=1; path=/; max-age=31536000; SameSite=Lax';
    banner.remove();
}

function initCookieBanner() {
    const banner = document.querySelector('[data-cookie-banner]');
    const button = banner && banner.querySelector('[data-cookie-banner-dismiss]');

    if (!banner || !button) {
        return;
    }

    button.addEventListener('click', () => dismissCookieBanner(banner));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCookieBanner);
} else {
    initCookieBanner();
}
