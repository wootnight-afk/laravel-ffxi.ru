// Public site scripts.
//
// Public pages are rendered without Livewire (and therefore without Alpine),
// so interactive widgets are wired here with plain DOM APIs and data-attributes
// instead of Alpine directives.

import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

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
// Browser timezone cookie
// ---------------------------------------------------------------------
// The server reads `tz` on every render (ADR-011 §2.3), so the browser zone is
// stored as a plain cookie (excluded from cookie encryption). No reload is
// performed: the first request of a new visitor is served in UTC, the next one
// already uses the browser timezone.

const TZ_COOKIE = 'tz';

function initTimezoneCookie() {
    try {
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (!timezone) {
            return;
        }

        document.cookie = `${TZ_COOKIE}=${encodeURIComponent(timezone)}; path=/; max-age=31536000; SameSite=Lax`;
    } catch (e) {
        // Intl unavailable — the server keeps the UTC fallback.
    }
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
// PhotoSwipe gallery lightbox
// ---------------------------------------------------------------------
// Thumbnails of an album open the lightbox instead of navigating away.
// PhotoSwipe keeps its own zoom/pan: Ctrl + mouse wheel zooms (built in,
// `wheelToZoom` stays disabled so a plain wheel keeps panning).
// Optional title (above the photo) and short description (under the photo)
// come from data attributes written by the album template only when the
// admin switch is on AND the field is filled; otherwise the block is hidden.
// The "details" button leads to the full photo page (comments live there).

function initGalleryLightbox() {
    const gallery = document.querySelector('.gallery-grid');

    if (!gallery || !gallery.querySelector('a[data-pswp-width]')) {
        return;
    }

    const lightbox = new PhotoSwipeLightbox({
        gallery: '.gallery-grid',
        children: 'a[data-pswp-width]',
        pswpModule: () => import('photoswipe'),
    });

    // Label of the "details" button comes from the template so that the
    // lightbox follows the current interface language (RU/EN).
    const detailsLabel = (gallery.dataset.detailsLabel || '').trim();

    const slideData = (pswp) => pswp.currSlide?.data?.element?.dataset || {};

    const setText = (pswp, selector, value) => {
        const el = pswp.element?.querySelector(selector);

        if (!el) {
            return;
        }

        const text = (value || '').trim();

        el.textContent = text;
        el.hidden = text === '';
    };

    const syncOverlay = (pswp) => {
        const data = slideData(pswp);

        setText(pswp, '.pswp__photo-title', data.pswpTitle);
        setText(pswp, '.pswp__photo-summary', data.pswpSummary);
    };

    lightbox.on('uiRegister', () => {
        const { ui } = lightbox.pswp;

        ui.registerElement({
            name: 'photo-title',
            className: 'pswp__photo-title',
            appendTo: 'root',
            order: 5,
            onInit: (el) => {
                el.hidden = true;
            },
        });

        ui.registerElement({
            name: 'photo-summary',
            className: 'pswp__photo-summary',
            appendTo: 'root',
            order: 6,
            onInit: (el) => {
                el.hidden = true;
            },
        });

        ui.registerElement({
            name: 'photo-details',
            className: 'pswp__details-btn',
            appendTo: 'root',
            order: 7,
            isButton: true,
            tagName: 'a',
            onInit: (el) => {
                el.textContent = detailsLabel;
            },
            onClick: (event, el, pswp) => {
                const url = slideData(pswp).photoUrl;

                if (url) {
                    event.preventDefault();
                    window.location.href = url;
                }
            },
        });
    });

    lightbox.on('change', () => syncOverlay(lightbox.pswp));
    lightbox.on('afterInit', () => syncOverlay(lightbox.pswp));

    lightbox.init();

    // Deep link from the gallery overview: /gallery/{album}?photo={id}.
    // The overview thumbnail links to the album with the id of the photo that
    // was clicked, so the album page opens the lightbox on that exact slide
    // instead of the first one. A plain album visit has no `photo` query and
    // therefore never opens the lightbox by itself.
    const requestedPhotoId = new URLSearchParams(window.location.search).get('photo');

    if (!requestedPhotoId) {
        return;
    }

    const slides = Array.from(gallery.querySelectorAll('a[data-pswp-width]'));
    const index = slides.findIndex((el) => el.dataset.photoId === requestedPhotoId);

    if (index < 0) {
        return;
    }

    // Drop the query parameter so closing the lightbox (or reloading the
    // album afterwards) does not reopen it; the user stays on the album page.
    const cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('photo');
    window.history.replaceState({}, '', cleanUrl.toString());

    lightbox.loadAndOpen(index);
}

// ---------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------

function initPublicScripts() {
    initCookieBanner();
    initTimezoneCookie();
    initThemeToggle();
    initPhotoZoom();
    initGalleryLightbox();
    initNicknameCheck();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicScripts);
} else {
    initPublicScripts();
}
