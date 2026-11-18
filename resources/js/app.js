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
// Header offset
// ---------------------------------------------------------------------
// The header is fixed to the top and the page reserves its height through
// --header-height. A hard-coded value does not hold: below 768px the navigation
// stacks inside the header, and in English the labels change width, so the real
// height differs from the written one and the reserved space shows as a blank
// strip under the menu. The measured height keeps the reservation exact.

function initHeaderOffset() {
    const header = document.querySelector('body > header');

    if (!header) {
        return;
    }

    const root = document.documentElement;
    let lastHeight = -1;

    const sync = () => {
        const height = Math.ceil(header.getBoundingClientRect().height);

        if (height === 0 || height === lastHeight) {
            return;
        }

        lastHeight = height;
        root.style.setProperty('--header-height', `${height}px`);
    };

    sync();

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(sync).observe(header);

        return;
    }

    window.addEventListener('resize', sync);
}

// ---------------------------------------------------------------------
// Footer offset
// ---------------------------------------------------------------------
// The footer is fixed to the viewport bottom, but its height follows the CMS
// navigation content (ADR-011 §2.23) and changes when the links wrap on a
// narrower viewport. The page reserves exactly that height through
// --footer-offset, so the last content is never hidden behind the footer.

function initFooterOffset() {
    const footer = document.querySelector('body > footer');

    if (!footer) {
        return;
    }

    const root = document.documentElement;
    let lastHeight = -1;

    const sync = () => {
        const height = Math.ceil(footer.getBoundingClientRect().height);

        if (height === lastHeight) {
            return;
        }

        lastHeight = height;
        root.style.setProperty('--footer-offset', `${height}px`);
    };

    sync();

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(sync).observe(footer);

        return;
    }

    window.addEventListener('resize', sync);
}

// ---------------------------------------------------------------------
// Players left menu
// ---------------------------------------------------------------------
// The menu panel is fixed to the viewport (see .players-shell__nav-inner).
// Its vertical bounds come from CSS: top is the real header bottom and bottom
// is the footer height measured by initFooterOffset(). This keeps the
// horizontal box in sync with the grid column that reserves the space, so the
// panel follows window resizing and browser zoom without duplicating the
// footer measurement.

function initPlayersNavOffset() {
    const shell = document.querySelector('.players-shell');

    if (!shell) {
        return;
    }

    const column = shell.querySelector('.players-shell__nav');
    const panel = shell.querySelector('.players-shell__nav-inner');
    const header = document.querySelector('body > header');

    if (!column || !panel || !header) {
        return;
    }

    const sync = () => {
        const columnBox = column.getBoundingClientRect();
        const headerBox = header.getBoundingClientRect();

        panel.style.setProperty('--players-nav-top', `${Math.round(headerBox.bottom)}px`);
        panel.style.setProperty('--players-nav-left', `${Math.round(columnBox.left)}px`);
        panel.style.setProperty('--players-nav-width', `${Math.round(columnBox.width)}px`);
    };

    sync();

    if (typeof ResizeObserver === 'function') {
        const observer = new ResizeObserver(sync);

        observer.observe(header);
        observer.observe(column);
    }

    window.addEventListener('resize', sync);
}

// ---------------------------------------------------------------------
// Localized file picker (STAGE-1 §18.2)
// ---------------------------------------------------------------------
// A native file input renders "Choose file / No file chosen" in the BROWSER
// language, which cannot be changed from the page. The input therefore stays
// intact (required, focusable, standard multipart submission) but is made
// transparent over a localized button; the selected file name is printed next
// to it. Without JavaScript the native control keeps working as before.

function initFileFields() {
    document.querySelectorAll('[data-file-field]').forEach((field) => {
        const input = field.querySelector('[data-file-input]');
        const name = field.querySelector('[data-file-name]');

        if (!input || !name) {
            return;
        }

        const emptyLabel = name.dataset.emptyLabel || name.textContent;

        const sync = () => {
            name.textContent = input.files && input.files.length > 0
                ? input.files[0].name
                : emptyLabel;
        };

        input.addEventListener('change', sync);
        // A page restored from the bfcache keeps the input state but not the
        // rendered name, so the label is recomputed on every restore.
        window.addEventListener('pageshow', sync);
        sync();
    });
}

// ---------------------------------------------------------------------
// Single submit
// ---------------------------------------------------------------------
// An upload must not be sent twice. The submit button is disabled on the
// first submit and re-enabled when the page comes back from the bfcache;
// the form itself is never blocked, so native validation still runs first
// (the submit event fires only after the browser accepted the form).

function initSubmitOnce() {
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        const submit = form.querySelector('button[type="submit"]');

        if (!submit) {
            return;
        }

        form.addEventListener('submit', () => {
            submit.disabled = true;
        });

        window.addEventListener('pageshow', () => {
            submit.disabled = false;
        });
    });
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
    initHeaderOffset();
    initFooterOffset();
    initPlayersNavOffset();
    initFileFields();
    initSubmitOnce();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicScripts);
} else {
    initPublicScripts();
}
