/**
 * RevOps tracking snippet.
 *
 * Embed on a tenant's site as:
 *   <script src="https://your-revops-domain/tracker.js" data-tenant-id="1"></script>
 *
 * Fires a page_view automatically on load. For anything else, use:
 *   window.revops.track('comment', { postId: 42 });
 *   window.revops.identify('visitor@example.com');
 *
 * Deliberately simple for now: no batching, no session concept, one
 * request per call. Never throws into the host page - tracking failures
 * should never break the site it's embedded on.
 */
(function () {
    var currentScript = document.currentScript || (function () {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    var tenantId = currentScript.getAttribute('data-tenant-id');

    if (!tenantId) {
        return;
    }

    var apiBase = currentScript.getAttribute('data-api-base')
        || currentScript.src.replace(/\/tracker\.js.*$/, '');

    var storageKey = 'revops_anon_id_' + tenantId;

    function getAnonId() {
        try {
            return localStorage.getItem(storageKey);
        } catch (e) {
            return null;
        }
    }

    function setAnonId(anonId) {
        try {
            localStorage.setItem(storageKey, anonId);
        } catch (e) {
            // Storage unavailable (private browsing, etc.) - keep tracking
            // for this pageview, just without persistence across pages.
        }
    }

    function post(path, body) {
        return fetch(apiBase + path, {
            method: 'POST',
            credentials: 'omit',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        }).then(function (response) {
            return response.json();
        });
    }

    function touch() {
        return post('/api/identity/touch', {
            tenant_id: tenantId,
            anon_id: getAnonId(),
        }).then(function (data) {
            if (data && data.anon_id) {
                setAnonId(data.anon_id);
            }

            return data;
        });
    }

    function track(eventName, properties) {
        return touch().then(function (data) {
            return post('/api/identity/event', {
                tenant_id: tenantId,
                anon_id: data.anon_id,
                event_name: eventName,
                properties: properties || {},
            });
        }).catch(function () {
            // Swallow errors - a tracking failure should never surface to
            // the host page.
        });
    }

    function identify(email) {
        return touch().then(function (data) {
            return post('/api/identity/resolve', {
                tenant_id: tenantId,
                anon_id: data.anon_id,
                email: email,
            });
        }).catch(function () {
            // Swallow errors - see track() above.
        });
    }

    window.revops = window.revops || {};
    window.revops.track = track;
    window.revops.identify = identify;

    track('page_view', {
        path: window.location.pathname,
        referrer: document.referrer || null,
        title: document.title,
    });
})();
