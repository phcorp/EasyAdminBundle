/**
 * Embedded-list widget client.
 *
 * The form widget renders `<div class="field-embedded-list" data-src="URL"></div>`
 * with no server-side content — this module fetches the URL on load, injects
 * the response HTML, and intercepts subsequent navigation (pagination, sort,
 * filter, in-fragment forms) so the parent page never reloads.
 *
 * Every fetch carries `X-EA-Fragment: 1`; the CrudResponseListener uses that
 * header to swap the layout to `layout_embedded.html.twig`, so the response
 * body is just the CRUD `main` block with no page chrome.
 *
 * A per-list AbortController cancels a pending fetch if a second navigation
 * arrives before the first resolves — the fresher intent wins and the older
 * response is discarded silently.
 *
 * Fires `ea.embedded-list.refreshed` on the document at the end of every
 * successful swap; project code can subscribe to it to rebind any
 * fragment-scoped behaviour that doesn't survive an innerHTML swap.
 */
const FRAGMENT_HEADER = { 'X-EA-Fragment': '1' };
const inflight = new WeakMap();

async function loadFragment(list, url) {
    const previous = inflight.get(list);
    if (previous) {
        previous.abort();
    }
    const controller = new AbortController();
    inflight.set(list, controller);
    try {
        const response = await fetch(url, { headers: FRAGMENT_HEADER, signal: controller.signal });
        const html = await response.text();
        if (controller.signal.aborted) {
            return;
        }
        list.innerHTML = html;
        bindNavigation(list);
        document.dispatchEvent(new Event('ea.embedded-list.refreshed'));
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }
        throw error;
    } finally {
        if (inflight.get(list) === controller) {
            inflight.delete(list);
        }
    }
}

function bindNavigation(list) {
    list.querySelectorAll('a:not([data-ea-fragment-bound])').forEach((link) => {
        if (link.closest('.actions')) {
            return;
        }
        link.dataset.eaFragmentBound = 'true';
        link.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            loadFragment(list, link.href);
        });
    });

    list.querySelectorAll('form:not([data-ea-fragment-bound])').forEach((form) => {
        form.dataset.eaFragmentBound = 'true';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const action = form.action || list.dataset.src;
            const method = (form.method || 'get').toLowerCase();
            const params = new URLSearchParams(new FormData(form));
            const url = 'get' === method ? `${action}${action.includes('?') ? '&' : '?'}${params.toString()}` : action;
            loadFragment(list, url);
        });
    });
}

function init() {
    document.querySelectorAll('.field-embedded-list[data-src]:not([data-ea-fragment-initialised])').forEach((list) => {
        list.dataset.eaFragmentInitialised = 'true';
        loadFragment(list, list.dataset.src);
    });
}

window.addEventListener('DOMContentLoaded', init);
document.addEventListener('ea.embedded-list.refreshed', init);
