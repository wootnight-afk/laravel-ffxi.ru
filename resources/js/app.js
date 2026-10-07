// Public site scripts.
//
// Public pages are rendered without Livewire (and therefore without Alpine),
// so interactive widgets are wired here with plain DOM APIs and data-attributes
// instead of Alpine directives.

// ---------------------------------------------------------------------
// Cookie banner
// ---------------------------------------------------------------------

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

// ---------------------------------------------------------------------
// Theme toggle
// ---------------------------------------------------------------------
// The initial theme is applied by the inline anti-FOUC script in the layout
// head; here we only toggle and persist it. The icon is switched declaratively
// via [data-theme] in CSS.

const THEME_KEY = 'ffxi-theme';

function currentTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);

    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch (e) {
        // localStorage unavailable — the theme still applies for this page.
    }
}

function initThemeToggle() {
    const button = document.querySelector('[data-theme-toggle]');

    if (!button) {
        return;
    }

    button.addEventListener('click', () => {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
    });
}

// ---------------------------------------------------------------------
// Photo zoom (lightbox)
// ---------------------------------------------------------------------

function initPhotoZoom() {
    const root = document.querySelector('[data-photo-zoom]');

    if (!root) {
        return;
    }

    const openTrigger = root.querySelector('[data-photo-zoom-open]');
    const overlay = root.querySelector('[data-photo-zoom-overlay]');

    if (!openTrigger || !overlay) {
        return;
    }

    openTrigger.addEventListener('click', () => {
        overlay.hidden = false;
    });

    overlay.addEventListener('click', () => {
        overlay.hidden = true;
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !overlay.hidden) {
            overlay.hidden = true;
        }
    });
}

// ---------------------------------------------------------------------
// Registration nickname check
// ---------------------------------------------------------------------

const NICKNAME_DEBOUNCE_MS = 500;

function initNicknameCheck() {
    const form = document.querySelector('[data-nickname-check-url]');
    const input = form && form.querySelector('[data-nickname-check-input]');

    if (!form || !input) {
        return;
    }

    const url = form.getAttribute('data-nickname-check-url');
    const csrf = document.querySelector('meta[name="csrf-token"]');
    const suggestionsBox = form.querySelector('[data-nickname-suggestions]');
    const submit = form.querySelector('[data-register-submit]');

    const statusBlocks = {};
    form.querySelectorAll('[data-nickname-status]').forEach((el) => {
        statusBlocks[el.getAttribute('data-nickname-status')] = el;
    });

    let timer = null;
    let requestId = 0;

    const showStatus = (name) => {
        Object.values(statusBlocks).forEach((el) => {
            el.hidden = true;
        });

        if (name && statusBlocks[name]) {
            statusBlocks[name].hidden = false;
        }
    };

    const setSubmitDisabled = (disabled) => {
        if (submit) {
            submit.disabled = disabled;
        }
    };

    const renderSuggestions = (items) => {
        if (!suggestionsBox) {
            return;
        }

        suggestionsBox.innerHTML = '';

        (items || []).slice(0, 3).forEach((nickname) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn';
            button.style.padding = '4px 10px';
            button.style.fontSize = '13px';
            button.textContent = nickname;

            button.addEventListener('click', () => {
                input.value = nickname;
                showStatus('available');
                renderSuggestions([]);
                setSubmitDisabled(false);
            });

            suggestionsBox.appendChild(button);
        });
    };

    const check = async (value) => {
        if (!value || value.length < 3) {
            showStatus(null);
            renderSuggestions([]);
            setSubmitDisabled(false);

            return;
        }

        const id = ++requestId;
        showStatus('checking');
        setSubmitDisabled(false);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf ? csrf.content : '',
                },
                body: JSON.stringify({ name: value }),
            });

            if (id !== requestId) {
                return;
            }

            if (!response.ok) {
                showStatus(null);
                renderSuggestions([]);
                setSubmitDisabled(false);

                return;
            }

            const data = await response.json();

            if (data.available) {
                showStatus('available');
                renderSuggestions([]);
                setSubmitDisabled(false);
            } else if (data.reason === 'taken') {
                showStatus('taken');
                renderSuggestions(data.suggestions);
                setSubmitDisabled(true);
            } else {
                // invalid / blacklisted
                showStatus('invalid');
                renderSuggestions([]);
                setSubmitDisabled(true);
            }
        } catch (e) {
            if (id !== requestId) {
                return;
            }

            showStatus(null);
            renderSuggestions([]);
            setSubmitDisabled(false);
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => check(input.value), NICKNAME_DEBOUNCE_MS);
    });
}

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------

function initPublicScripts() {
    initCookieBanner();
    initThemeToggle();
    initPhotoZoom();
    initNicknameCheck();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicScripts);
} else {
    initPublicScripts();
}
