/* Snapshot Inspector Drawer — read-only snapshot summary overlay.
   Bound by next.js when the sidebar resource tree receives an unmodified
   primary click on a real dataset link, this drawer reuses the shared
   fetchDatasetsShared() in-memory cache for dataset metadata and fetches
   snapshots from the existing read-only API:
       ../api/snapshots.php?name=<dataset>
   It performs no write actions and keeps the page (topbar, locale, theme,
   tree collapse state) untouched. Script order in footer.php guarantees
   next.js helpers (t, escapeHtml, withLang, fetchData,
   fetchDatasetsShared, formatTimestamp) are defined before this file runs.
   When those helpers are unavailable the drawer renders its own fallbacks
   so it never crashes the host page.

   Independent of snapshots.js: the full snapshot management page (with the
   create/hold/release/delete/rollback timeline) is intentionally NOT loaded
   here. This script is only appended to the shell footer and stays inert on
   pages without the resource tree.
*/
(function() {
    'use strict';

    if (typeof window.ZSSSnapshotInspector !== 'undefined') {
        return;
    }

    var API_URL = '../api/snapshots.php';
    var LAST_FOCUS_KEY = 'zss_inspector_last_focus';
    var lastTrigger = null;
    var requestController = null;
    var requestSeq = 0;
    var overlayEl = null;
    var bodyEl = null;
    var contentEl = null;
    var closeEl = null;
    var bodyKeydownBound = false;
    var overlayClickBound = false;
    var reducedMotionQuery = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;

    function isOpen() {
        return !!overlayEl && overlayEl.classList.contains('is-open');
    }

    function t(key, fallback) {
        if (typeof window.t === 'function') {
            return window.t(key, fallback);
        }
        return fallback;
    }

    function escapeHtml(value) {
        if (typeof window.escapeHtml === 'function') {
            return window.escapeHtml(value);
        }
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function withLang(url) {
        if (typeof window.withLang === 'function') {
            return window.withLang(url);
        }
        return url;
    }

    function formatTimestamp(timestamp) {
        if (typeof window.formatTimestamp === 'function') {
            return window.formatTimestamp(timestamp);
        }
        return timestamp ? String(timestamp) : '-';
    }

    function frequencyLabel(value) {
        if (typeof window.frequencyLabel === 'function') {
            return window.frequencyLabel(value);
        }
        if (value) {
            return t('frequency.' + value, value);
        }
        return '-';
    }

    function snapshotOriginLabel(origin) {
        if (origin === 'autosnap') {
            return t('snapshots.origin_autosnap', 'Auto');
        }
        if (origin === 'plugin_manual') {
            return t('snapshots.origin_plugin_manual', 'Plugin manual');
        }
        return t('snapshots.origin_external', 'External');
    }

    function isSnapshotDetailPage() {
        return !!document.getElementById('snapshots-timeline');
    }

    function isModifiedClick(event) {
        if (event && typeof event.button === 'number' && event.button !== 0) {
            return true;
        }
        return !!(event && (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey));
    }

    function createDrawerDom() {
        if (overlayEl) {
            return overlayEl;
        }

        overlayEl = document.createElement('div');
        overlayEl.id = 'zss-snapshot-inspector';
        overlayEl.className = 'zss-inspector-overlay';
        overlayEl.hidden = true;
        overlayEl.innerHTML =
            '<div class="zss-inspector-scrim" data-zss-inspector-close aria-hidden="true"></div>' +
            '<section class="zss-inspector-card" role="dialog" aria-modal="true" aria-label="' +
                escapeHtml(t('inspector.title', 'Snapshot Inspector')) + '">' +
                '<header class="zss-inspector-header">' +
                    '<div class="zss-inspector-heading">' +
                        '<div class="zss-panel-kicker">' +
                            escapeHtml(t('inspector.kicker', 'Snapshot Inspector')) +
                        '</div>' +
                        '<h2 id="zss-inspector-dataset">' +
                            escapeHtml(t('inspector.empty_dataset', 'No dataset selected')) +
                        '</h2>' +
                    '</div>' +
                    '<button type="button" class="zss-icon-button zss-inspector-close" ' +
                        'data-zss-inspector-close aria-label="' +
                        escapeHtml(t('common.close', 'Close')) + '">×</button>' +
                '</header>' +
                '<div class="zss-inspector-body">' +
                    '<div class="zss-inspector-meta zss-inspector-meta-row">' +
                        '<span class="zss-badge zss-badge-muted" id="zss-inspector-enabled">' +
                            escapeHtml(t('common.loading', 'Loading...')) +
                        '</span>' +
                    '</div>' +
                    '<dl class="zss-inspector-meta zss-inspector-meta-grid">' +
                        '<dt>' + escapeHtml(t('table.snapshot_count', 'Snapshots')) + '</dt>' +
                        '<dd id="zss-inspector-snapshot-count">—</dd>' +
                        '<dt>' + escapeHtml(t('datasets.fields.keep', 'Keep')) + '</dt>' +
                        '<dd id="zss-inspector-keep">—</dd>' +
                        '<dt>' + escapeHtml(t('inspector.retain_days', 'Readonly protection days')) + '</dt>' +
                        '<dd id="zss-inspector-retain-days">—</dd>' +
                        '<dt>' + escapeHtml(t('snapshots.context.protected', 'Protected')) + '</dt>' +
                        '<dd id="zss-inspector-held">—</dd>' +
                    '</dl>' +
                    '<div class="zss-inspector-list-wrap">' +
                        '<div id="zss-inspector-list" role="list" class="zss-inspector-list" aria-live="polite">' +
                            '<p class="zss-inspector-status">' +
                                escapeHtml(t('common.loading', 'Loading...')) +
                            '</p>' +
                        '</div>' +
                    '</div>' +
                    '<div class="zss-inspector-actions">' +
                        '<a id="zss-inspector-open-page" class="zss-btn zss-btn-secondary" ' +
                            'href="' + escapeHtml(withLang('snapshots.php')) + '">' +
                            escapeHtml(t('inspector.open_page', 'Open full snapshot management page')) +
                        '</a>' +
                    '</div>' +
                '</div>' +
            '</section>';

        closeEl = overlayEl.querySelector('[data-zss-inspector-close]');
        contentEl = overlayEl.querySelector('.zss-inspector-card');
        bodyEl = overlayEl.querySelector('.zss-inspector-body');
        document.body.appendChild(overlayEl);

        if (!overlayClickBound) {
            overlayEl.addEventListener('click', function(event) {
                var target = event.target;
                if (target === overlayEl || target.closest('[data-zss-inspector-close]')) {
                    close();
                }
            });
            overlayClickBound = true;
        }

        if (!bodyKeydownBound) {
            document.body.addEventListener('keydown', function(event) {
                if (event.key !== 'Escape' || !isOpen()) {
                    return;
                }
                close();
            });
            bodyKeydownBound = true;
        }

        return overlayEl;
    }

    function rememberTrigger(link) {
        lastTrigger = link || null;
        if (!link) {
            return;
        }
        try {
            window.localStorage.setItem(LAST_FOCUS_KEY, String(link.id || ''));
        } catch (error) {}
    }

    function restoreFocusTo(link) {
        if (!link) {
            return;
        }
        try {
            link.focus();
        } catch (error) {}
        // A freshly rebuilt tree replaces the clicked node; refocus the
        // dataset row by its remembered name instead of a stale reference.
        if (document.activeElement !== link) {
            var name = link.dataset.inspectorName || '';
            if (name) {
                var container = document.getElementById('zss-resource-tree');
                if (container) {
                    var anchor = container.querySelector('.zss-tree-link[data-inspector-name="' + CSS.escape(name) + '"]');
                    if (anchor) {
                        try { anchor.focus(); } catch (error) {}
                    }
                }
            }
        }
    }

    function renderDatasetMeta(dataset) {
        var countEl = document.getElementById('zss-inspector-snapshot-count');
        var enabledEl = document.getElementById('zss-inspector-enabled');
        var keepEl = document.getElementById('zss-inspector-keep');
        var retainEl = document.getElementById('zss-inspector-retain-days');
        var heldEl = document.getElementById('zss-inspector-held');

        var enabled = !!(dataset && dataset.enabled);
        if (enabledEl) {
            enabledEl.className = 'zss-badge ' + (enabled ? 'zss-badge-success' : 'zss-badge-muted');
            enabledEl.textContent = enabled
                ? t('common.enabled', 'Enabled')
                : t('common.disabled', 'Disabled');
        }
        if (countEl) {
            countEl.textContent = (dataset && dataset.snapshot_count !== undefined && dataset.snapshot_count !== null)
                ? String(dataset.snapshot_count)
                : '-';
        }
        if (keepEl) {
            keepEl.textContent = (dataset && dataset.keep !== undefined && dataset.keep !== null)
                ? String(dataset.keep)
                : '-';
        }
        if (retainEl) {
            retainEl.textContent = (dataset && dataset.retain_days !== undefined && dataset.retain_days !== null)
                ? String(dataset.retain_days)
                : '-';
        }
        if (heldEl) {
            heldEl.textContent = (dataset && dataset.held_snapshot_count !== undefined && dataset.held_snapshot_count !== null)
                ? String(dataset.held_snapshot_count)
                : '-';
        }
    }

    function renderSnapshots(snapshots, datasetName) {
        var listEl = document.getElementById('zss-inspector-list');
        if (!listEl) {
            return;
        }
        var list = Array.isArray(snapshots) ? snapshots : [];
        list.sort(function(a, b) {
            var aTime = Number(a && a.created_at) || 0;
            var bTime = Number(b && b.created_at) || 0;
            return bTime - aTime;
        });

        if (list.length === 0) {
            listEl.innerHTML =
                '<p class="zss-inspector-status">' +
                    escapeHtml(t('snapshots.empty', 'No snapshots')) +
                '</p>';
            return;
        }

        var html = list.map(function(snap) {
            var shortName = snap.short_name || snap.name || '-';
            var origin = snap.origin || 'external';
            var created = formatTimestamp(snap.created_at);
            var originLabel = snapshotOriginLabel(origin);
            var held = !!(snap.held);
            var holdTags = Array.isArray(snap.hold_tags) ? snap.hold_tags : [];
            var titleAttr = escapeHtml(snap.name || shortName);

            var row =
                '<div class="zss-inspector-snapshot" role="listitem">' +
                    '<div class="zss-inspector-snap-line">' +
                        '<span class="zss-inspector-snap-name" title="' + titleAttr + '">' +
                            escapeHtml(shortName) +
                        '</span>' +
                        '<span class="zss-origin-tag ' +
                            (origin === 'autosnap' ? 'is-auto' : origin === 'plugin_manual' ? 'is-manual' : '') +
                            '">' + escapeHtml(originLabel) + '</span>' +
                    '</div>' +
                    '<div class="zss-inspector-snap-sub">' +
                        '<span class="zss-inspector-snap-time">' + escapeHtml(created) + '</span>' +
                        '<span class="zss-inspector-snap-hold">' +
                            (held
                                ? escapeHtml(t('common.protected', 'Protected'))
                                : escapeHtml(t('common.normal', 'Normal'))) +
                            (held && holdTags.length > 0
                                ? ': ' + escapeHtml(holdTags.join(', '))
                                : '') +
                        '</span>' +
                    '</div>' +
                '</div>';
            return row;
        }).join('');

        listEl.innerHTML = html;
    }

    function renderError(message) {
        var listEl = document.getElementById('zss-inspector-list');
        if (!listEl) {
            return;
        }
        listEl.innerHTML =
            '<p class="zss-inspector-status zss-inspector-error" role="alert">' +
                escapeHtml(message) +
            '</p>';
    }

    function showContent() {
        if (bodyEl) {
            bodyEl.hidden = false;
        }
    }

    function hideContent() {
        if (bodyEl) {
            bodyEl.hidden = true;
        }
    }

    async function open(link) {
        if (!overlayEl) {
            createDrawerDom();
        }
        if (!overlayEl) {
            return;
        }

        var name = '';
        if (link) {
            name = link.dataset.inspectorName || '';
        }
        if (!name && link && link.href) {
            try {
                var params = new URLSearchParams(new URL(link.href, window.location.href).search);
                name = params.get('dataset') || '';
            } catch (error) {
                name = '';
            }
        }
        if (!name) {
            return;
        }

        // Claim this operation's sequence before any await: a stale open()
        // superseded by a newer open() or invalidated by close() must never
        // resume past an await point to start or paint a request.
        var requestSeqForThis = ++requestSeq;
        if (requestController) {
            try { requestController.abort(); } catch (abortError) {}
        }
        requestController = (typeof AbortController !== 'undefined') ? new AbortController() : null;

        var datasetHeading = document.getElementById('zss-inspector-dataset');
        if (datasetHeading) {
            datasetHeading.textContent = name;
        }

        var pageLink = document.getElementById('zss-inspector-open-page');
        if (pageLink) {
            // Property assignment takes a raw URL; escapeHtml() here would
            // corrupt the query separator (&lang=... -> &amp;lang=...).
            pageLink.href = withLang('snapshots.php?dataset=' + encodeURIComponent(name));
        }

        overlayEl.hidden = false;
        // The card has no entrance animation; apply an is-open class so
        // focus handling, the scrim, and the responsive layout engage.
        overlayEl.classList.add('is-open');
        overlayEl.classList.add('zss-inspector-active');
        rememberTrigger(link);
        showContent();

        var closeButton = overlayEl.querySelector('.zss-inspector-close');
        if (closeButton) {
            try { closeButton.focus(); } catch (error) {}
        }

        // Keep the page (sidebar, topbar, tree collapse state) pristine:
        // dataset metadata comes from the shared fetchDatasetsShared()
        // in-memory cache; only the snapshot summary itself is requested
        // from the read-only API. This operation was claimed above, before
        // any await, so a superseding open() or close() that bumped the
        // sequence while the shared cache resolved invalidates this resume.
        var meta = null;
        if (typeof window.fetchDatasetsShared === 'function') {
            try {
                var metaResult = await window.fetchDatasetsShared();
                if (requestSeq !== requestSeqForThis || !isOpen()) {
                    return; // Superseded or closed while metadata was loading.
                }
                if (metaResult && metaResult.ok) {
                    var datasets = metaResult.data || [];
                    for (var i = 0; i < datasets.length; i++) {
                        if (datasets[i] && datasets[i].name === name) {
                            meta = datasets[i];
                            break;
                        }
                    }
                }
            } catch (metaError) {
                if (requestSeq !== requestSeqForThis || !isOpen()) {
                    return; // Superseded or closed while metadata was loading.
                }
            }
        }

        try {
            var snapResult = await fetchData(
                API_URL + '?name=' + encodeURIComponent(name),
                requestController ? { signal: requestController.signal } : {}
            );
            if (requestSeq !== requestSeqForThis || !isOpen()) {
                return; // A newer open() superseded this one; never paint stale rows.
            }
            renderDatasetMeta(meta);
            if (!snapResult || !snapResult.ok) {
                renderError((snapResult && snapResult.error && snapResult.error.message)
                    ? snapResult.error.message
                    : t('common.load_failed', 'Load failed'));
                return;
            }
            renderSnapshots(snapResult.data || [], name);
        } catch (error) {
            // Aborted switches are routine (fast dataset hopping); swallow
            // AbortError silently instead of painting a fake load failure.
            if (error && error.name === 'AbortError') {
                return;
            }
            if (requestSeq !== requestSeqForThis || !isOpen()) {
                return;
            }
            renderError((error && error.message)
                ? error.message
                : t('common.load_failed', 'Load failed'));
        }
    }

    async function openForDataset(link, datasetName) {
        if (!overlayEl) {
            createDrawerDom();
        }
        if (!overlayEl) {
            return;
        }
        if (!datasetName) {
            return;
        }
        if (link) {
            try {
                link.dataset.inspectorName = datasetName;
            } catch (error) {}
        }
        await open(link);
    }

    function close() {
        if (!overlayEl) {
            return;
        }
        if (requestController) {
            try { requestController.abort(); } catch (error) {}
            requestController = null;
        }
        requestSeq++;
        overlayEl.classList.remove('is-open');
        overlayEl.classList.remove('zss-inspector-active');
        overlayEl.hidden = true;
        // The card has no entrance animation; release the focus back to the
        // sidebar link that opened the drawer.
        restoreFocusTo(lastTrigger);
        lastTrigger = null;
    }

    window.ZSSSnapshotInspector = {
        open: openForDataset,
        close: close,
        isOpen: isOpen,
    };

    /* Tree-click takeover — the inspector binds itself after the API export
       above (this file loads after next.js in the shell footer), so the
       binding never races script order. Only a plain (unmodified,
       primary-button) click on a real dataset link in the sidebar resource
       tree opens the drawer instead of the native Document navigation, on
       every page except the snapshots detail page, where snapshots.js' own
       capture listener keeps the in-place dataset switch for the full
       timeline. The tree's synthetic branch rows are not anchors and are
       never intercepted. Modified clicks (non-primary button,
       meta/ctrl/shift/alt) and clicks on a toggle button intentionally keep
       the native href deep link. Binding is delegated on the stable tree
       container in the capture phase, guarded against double binding, so it
       also covers tree nodes rendered after an in-page refresh; a guard
       avoids repeated bindings when loadResourceTree() re-renders the
       container's innerHTML. */
    var treeContainer = document.getElementById('zss-resource-tree');
    if (treeContainer && !treeContainer.dataset.zssInspectorBound) {
        treeContainer.dataset.zssInspectorBound = '1';
        treeContainer.addEventListener('click', function(event) {
            if (!event.target || typeof event.target.closest !== 'function') {
                return;
            }
            if (isSnapshotDetailPage()) {
                // snapshots.js owns the tree on the detail page (in-place
                // timeline switch for the full management UI).
                return;
            }
            var link = event.target.closest('.zss-tree-link[href]');
            if (!link || !treeContainer.contains(link)) {
                return;
            }
            var dataset = link.dataset.inspectorName || '';
            if (!dataset) {
                try {
                    var parsed = new URL(link.href, window.location.href);
                    dataset = parsed.searchParams.get('dataset') || '';
                } catch (error) {
                    dataset = '';
                }
            }
            if (!dataset) {
                return;
            }
            if (isModifiedClick(event)) {
                return; // preserve the native deep-link navigation
            }
            event.preventDefault();
            window.ZSSSnapshotInspector.open(link, dataset);
        }, true);
    }
})();
