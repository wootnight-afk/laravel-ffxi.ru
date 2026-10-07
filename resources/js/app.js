// Public site scripts.
//
// The cookie banner is rendered on public pages where no Livewire component
// (and therefore no Alpine runtime) is present, so it cannot rely on Alpine
// directives. It is wired here with plain DOM APIs instead.

const COOKIE_BANNER_KEY = 'cookie_banner_ack';
const COOKIE_BANNER_MAX_AGE = 60 * 60 * 24 * 365; // 1 year

function dismissCookieBanner(banner) {
    document.cookie =
        COOKIE_BANNER_KEY + '=1; path=/; max-age=' + COOKIE_BANNER_MAX_AGE + '; samesite=lax';
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
