@php($hasExplorer = ! empty($spec['x-tree'] ?? []))
    <!doctype html>
<html lang="en" data-theme="{{ $config->renderer()->get('theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="color-scheme" content="{{ $config->renderer()->get('theme', 'light') }}">
    <title>{{ $config->get('ui.title') ?? config('app.name') . ' - API Docs' }}</title>

    <script src="https://unpkg.com/@stoplight/elements@8.4.2/web-components.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/@stoplight/elements@8.4.2/styles.min.css">

    <script>
        const originalFetch = window.fetch;

        // intercept TryIt requests and add the XSRF-TOKEN header,
        // which is necessary for Sanctum cookie-based authentication to work correctly
        window.fetch = (url, options) => {
            const CSRF_TOKEN_COOKIE_KEY = "XSRF-TOKEN";
            const CSRF_TOKEN_HEADER_KEY = "X-XSRF-TOKEN";
            const getCookieValue = (key) => {
                const cookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith(key));
                return cookie?.split("=")[1];
            };

            const updateFetchHeaders = (
                headers,
                headerKey,
                headerValue,
            ) => {
                if (headers instanceof Headers) {
                    headers.set(headerKey, headerValue);
                } else if (Array.isArray(headers)) {
                    headers.push([headerKey, headerValue]);
                } else if (headers) {
                    headers[headerKey] = headerValue;
                }
            };
            const csrfToken = getCookieValue(CSRF_TOKEN_COOKIE_KEY);
            if (csrfToken) {
                const { headers = new Headers() } = options || {};
                updateFetchHeaders(headers, CSRF_TOKEN_HEADER_KEY, decodeURIComponent(csrfToken));
                return originalFetch(url, {
                    ...options,
                    headers,
                });
            }

            return originalFetch(url, options);
        };
    </script>

    <style>
        html, body { margin:0; height:100%; }
        body { background-color: var(--color-canvas); }
        /* issues about the dark theme of stoplight/mosaic-code-viewer using web component:
         * https://github.com/stoplightio/elements/issues/2188#issuecomment-1485461965
         */
        [data-theme="dark"] .token.property {
            color: rgb(128, 203, 196) !important;
        }
        [data-theme="dark"] .token.operator {
            color: rgb(255, 123, 114) !important;
        }
        [data-theme="dark"] .token.number {
            color: rgb(247, 140, 108) !important;
        }
        [data-theme="dark"] .token.string {
            color: rgb(165, 214, 255) !important;
        }
        [data-theme="dark"] .token.boolean {
            color: rgb(121, 192, 255) !important;
        }
        [data-theme="dark"] .token.punctuation {
            color: #dbdbdb !important;
        }
    </style>

    @if($hasExplorer)
        {{-- Hierarchical documentation explorer (enabled via `scramble.groups.enabled`). --}}
        <style>
            :root {
                --explorer-width: 320px;
                --explorer-bg: #f8fafc;
                --explorer-hover: #e2e8f0;
                --explorer-text: #1e293b;
                --explorer-muted: #64748b;
                --explorer-active: #0284c7;
                --explorer-active-bg: #e0f2fe;
                --explorer-border: #e2e8f0;
                --explorer-focus: 0 0 0 3px rgba(2, 132, 199, 0.25);
            }
            [data-theme="dark"] {
                --explorer-bg: #0f172a;
                --explorer-hover: #1e293b;
                --explorer-text: #e2e8f0;
                --explorer-muted: #94a3b8;
                --explorer-active: #38bdf8;
                --explorer-active-bg: #0c4a6e;
                --explorer-border: #1e293b;
                --explorer-focus: 0 0 0 3px rgba(56, 189, 248, 0.25);
            }

            body.has-explorer { display: flex; overflow: hidden; }

            /* Let Elements own the main pane; we provide our own navigation. */
            body.has-explorer .sl-elements-sidebar,
            body.has-explorer [class*="sl-sidebar"] { display: none !important; }
            body.has-explorer [class*="sl-container"] { max-width: 100% !important; }

            #scramble-explorer {
                width: var(--explorer-width);
                flex-shrink: 0;
                border-right: 1px solid var(--explorer-border);
                background-color: var(--explorer-bg);
                display: flex;
                flex-direction: column;
                height: 100vh;
                color: var(--explorer-text);
                transition: transform 0.3s ease;
                z-index: 100;
            }
            .explorer-header { padding: 1.25rem 1.25rem 0.75rem; border-bottom: 1px solid var(--explorer-border); }
            .explorer-title { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -0.02em; }
            .explorer-subtitle { font-size: 0.75rem; color: var(--explorer-muted); margin: 0.15rem 0 0; }
            .explorer-toolbar {
                display: flex; gap: 0.5rem; padding: 0.6rem 1.25rem;
                border-bottom: 1px solid var(--explorer-border);
            }
            .explorer-btn {
                background: none; border: none; color: var(--explorer-muted);
                font: inherit; font-size: 0.75rem; font-weight: 500; cursor: pointer;
                padding: 0.25rem 0.5rem; border-radius: 0.375rem;
            }
            .explorer-btn:hover { background-color: var(--explorer-hover); color: var(--explorer-text); }
            .explorer-btn:focus-visible { outline: none; box-shadow: var(--explorer-focus); }
            .explorer-search { padding: 0.75rem 1.25rem; position: relative; }
            .explorer-search input {
                width: 100%; padding: 0.5rem 0.75rem; font: inherit; font-size: 0.85rem;
                border-radius: 0.5rem; border: 1px solid var(--explorer-border);
                background-color: var(--color-canvas, #fff); color: var(--explorer-text);
                box-sizing: border-box; outline: none;
            }
            .explorer-search input:focus { border-color: var(--explorer-active); box-shadow: var(--explorer-focus); }
            .explorer-tree { flex: 1 1 auto; overflow-y: auto; padding: 0.5rem; }
            .explorer-tree ul { list-style: none; padding: 0; margin: 0; }
            .explorer-tree .children { padding-left: 0.9rem; display: none; }
            .explorer-tree .children.expanded { display: block; }
            .folder-row {
                display: flex; align-items: center; gap: 0.4rem; width: 100%;
                padding: 0.35rem 0.6rem; font: inherit; font-size: 0.85rem; font-weight: 500;
                color: var(--explorer-text); background: none; border: none; cursor: pointer;
                border-radius: 0.375rem; text-align: left;
            }
            .folder-row:hover { background-color: var(--explorer-hover); }
            .folder-row:focus-visible { outline: none; box-shadow: var(--explorer-focus); }
            .chevron { width: 0.9rem; height: 0.9rem; flex-shrink: 0; color: var(--explorer-muted); transition: transform 0.2s ease; }
            .chevron.expanded { transform: rotate(90deg); }
            .route-link {
                display: flex; align-items: center; gap: 0.5rem;
                padding: 0.35rem 0.6rem; font-size: 0.8rem; color: var(--explorer-muted);
                text-decoration: none; border-radius: 0.375rem; outline: none;
            }
            .route-link:hover { background-color: var(--explorer-hover); color: var(--explorer-text); }
            .route-link:focus-visible { box-shadow: var(--explorer-focus); }
            .route-link.active { background-color: var(--explorer-active-bg); color: var(--explorer-active); font-weight: 600; }
            .method-badge {
                font-size: 0.6rem; font-weight: 700; text-transform: uppercase;
                padding: 0.1rem 0.35rem; border-radius: 0.25rem; min-width: 2.4rem;
                text-align: center; flex-shrink: 0; background: var(--explorer-hover); color: var(--explorer-muted);
            }
            .method-get { background: rgba(16,185,129,0.12); color: #10b981; }
            .method-post { background: rgba(59,130,246,0.12); color: #3b82f6; }
            .method-put, .method-patch { background: rgba(245,158,11,0.12); color: #f59e0b; }
            .method-delete { background: rgba(239,68,68,0.12); color: #ef4444; }
            .route-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .explorer-highlight { background-color: rgba(245,158,11,0.3); border-radius: 0.125rem; }
            .explorer-empty { padding: 1rem 1.25rem; font-size: 0.8rem; color: var(--explorer-muted); }

            #docs-container { flex: 1 1 auto; height: 100vh; overflow-y: auto; }

            #explorer-toggle {
                display: none; position: fixed; bottom: 1.25rem; left: 1.25rem;
                width: 3rem; height: 3rem; border-radius: 50%; border: none;
                background-color: var(--explorer-active); color: #fff; cursor: pointer;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 1000;
                align-items: center; justify-content: center;
            }
            @media (max-width: 768px) {
                #scramble-explorer { position: fixed; left: 0; top: 0; transform: translateX(-100%); }
                #scramble-explorer.open { transform: translateX(0); }
                #explorer-toggle { display: flex; }
            }
        </style>
    @endif
</head>
<body @class(['has-explorer' => $hasExplorer])>

@if($hasExplorer)
    <button id="explorer-toggle" aria-label="Toggle navigation" aria-controls="scramble-explorer" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.4rem;height:1.4rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>
    </button>

    <nav id="scramble-explorer" aria-label="API documentation explorer">
        <div class="explorer-header">
            <h1 class="explorer-title">{{ $config->get('ui.title') ?? config('app.name') }}</h1>
            <p class="explorer-subtitle">API reference</p>
        </div>
        <div class="explorer-toolbar">
            <button type="button" class="explorer-btn" id="explorer-expand-all">Expand all</button>
            <button type="button" class="explorer-btn" id="explorer-collapse-all">Collapse all</button>
        </div>
        <div class="explorer-search">
            <input type="search" id="explorer-search" placeholder="Search endpoints…" aria-label="Search endpoints" autocomplete="off">
        </div>
        <div class="explorer-tree" id="explorer-tree" role="tree"></div>
    </nav>
@endif

<div id="docs-container">
    <elements-api
        id="docs"
        @foreach($config->renderer()->all(except: ['theme']) as $key => $value)
            @continue(! $value)
            {{ $key }}="{{ $value === true ? 'true' : ($value === false ? 'false' : $value) }}"
        @endforeach
    />
</div>

<script>
    (async () => {
        const docs = document.getElementById('docs');
        docs.apiDescriptionDocument = @json($spec);
    })();
</script>

@if($hasExplorer)
    <script>
        (() => {
            const treeData = @json($spec['x-tree'] ?? []);
            const treeRoot = document.getElementById('explorer-tree');
            const searchBox = document.getElementById('explorer-search');
            const explorer = document.getElementById('scramble-explorer');
            const toggle = document.getElementById('explorer-toggle');

            const STORAGE_EXPANDED = 'scramble.explorer.expanded';
            const STORAGE_SCROLL = 'scramble.explorer.scroll';

            let expanded = new Set(JSON.parse(localStorage.getItem(STORAGE_EXPANDED) || '[]'));

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value == null ? '' : String(value);
                return div.innerHTML;
            };

            const highlight = (text, term) => {
                const safe = escapeHtml(text);
                if (! term) return safe;
                const index = safe.toLowerCase().indexOf(term.toLowerCase());
                if (index === -1) return safe;
                return safe.slice(0, index)
                    + '<span class="explorer-highlight">' + safe.slice(index, index + term.length) + '</span>'
                    + safe.slice(index + term.length);
            };

            const routeText = (route) =>
                `${route.name || ''} ${route.path || ''} ${route.method || ''} ${route.controller || ''}`.toLowerCase();

            const routeMatches = (route, term) => ! term || routeText(route).includes(term.toLowerCase());

            const groupMatches = (node, term) => {
                if (! term) return true;
                if (node.name && node.name.toLowerCase().includes(term.toLowerCase())) return true;
                if ((node.routes || []).some((r) => routeMatches(r, term))) return true;
                return (node.children || []).some((c) => groupMatches(c, term));
            };

            const routeId = (route) => route.operationId || route.id;

            const renderRoutes = (routes, term) => (routes || [])
                .filter((route) => routeMatches(route, term))
                .map((route) => {
                    const id = routeId(route);
                    const method = (route.method || 'get').toLowerCase();
                    return `<li role="none">
                        <a role="treeitem" class="route-link" id="explorer-link-${escapeHtml(id)}" href="#/operations/${escapeHtml(id)}">
                            <span class="method-badge method-${escapeHtml(method)}">${escapeHtml(route.method || '')}</span>
                            <span class="route-name" title="${escapeHtml(route.name || route.path)}">${highlight(route.name || route.path, term)}</span>
                        </a>
                    </li>`;
                }).join('');

            const renderNodes = (nodes, term) => {
                const items = (nodes || []).map((node) => {
                    if (node.type !== 'group') return '';
                    if (term && ! groupMatches(node, term)) return '';

                    const open = term ? true : expanded.has(node.id);
                    return `<li role="none">
                        <button type="button" class="folder-row" data-node="${escapeHtml(node.id)}" aria-expanded="${open}">
                            <svg class="chevron ${open ? 'expanded' : ''}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            <span>${highlight(node.name, term)}</span>
                        </button>
                        <ul class="children ${open ? 'expanded' : ''}" role="group" id="explorer-children-${escapeHtml(node.id)}">
                            ${renderNodes(node.children, term)}
                            ${renderRoutes(node.routes, term)}
                        </ul>
                    </li>`;
                }).join('');

                return items ? `<ul role="group">${items}</ul>` : '';
            };

            const render = (term = '') => {
                const html = renderNodes(treeData, term);
                treeRoot.innerHTML = html || '<p class="explorer-empty">No endpoints found.</p>';
                highlightActive();
            };

            const expandToActive = (id, nodes) => (nodes || []).some((node) => {
                if (node.type !== 'group') return false;
                const here = (node.routes || []).some((r) => routeId(r) === id);
                const inChild = expandToActive(id, node.children);
                if (here || inChild) {
                    expanded.add(node.id);
                    return true;
                }
                return false;
            });

            const highlightActive = () => {
                document.querySelectorAll('.route-link.active').forEach((el) => el.classList.remove('active'));

                if (! window.location.hash.startsWith('#/operations/')) return;
                const id = window.location.hash.replace('#/operations/', '');
                const link = document.getElementById(`explorer-link-${id}`);
                if (! link) return;

                link.classList.add('active');
                if (expandToActive(id, treeData)) {
                    persistExpanded();
                    if (! searchBox.value) render();
                }
                link.scrollIntoView({ block: 'nearest' });
            };

            const persistExpanded = () => localStorage.setItem(STORAGE_EXPANDED, JSON.stringify([...expanded]));

            const collectIds = (nodes) => (nodes || []).reduce((ids, node) => {
                if (node.type === 'group') {
                    ids.push(node.id, ...collectIds(node.children));
                }
                return ids;
            }, []);

            // Event delegation keeps a single listener regardless of tree size.
            treeRoot.addEventListener('click', (e) => {
                const row = e.target.closest('.folder-row');
                if (! row) return;
                const id = row.dataset.node;
                expanded.has(id) ? expanded.delete(id) : expanded.add(id);
                persistExpanded();
                if (! searchBox.value) render();
            });

            searchBox.addEventListener('input', (e) => render(e.target.value.trim()));

            document.getElementById('explorer-expand-all').addEventListener('click', () => {
                expanded = new Set(collectIds(treeData));
                persistExpanded();
                render(searchBox.value.trim());
            });
            document.getElementById('explorer-collapse-all').addEventListener('click', () => {
                expanded.clear();
                persistExpanded();
                render(searchBox.value.trim());
            });

            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const open = explorer.classList.toggle('open');
                toggle.setAttribute('aria-expanded', String(open));
            });
            document.addEventListener('click', (e) => {
                if (explorer.classList.contains('open') && ! explorer.contains(e.target) && e.target !== toggle) {
                    explorer.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });

            window.addEventListener('hashchange', highlightActive);

            render();

            const storedScroll = localStorage.getItem(STORAGE_SCROLL);
            if (storedScroll) treeRoot.scrollTop = parseInt(storedScroll, 10) || 0;
            treeRoot.addEventListener('scroll', () => localStorage.setItem(STORAGE_SCROLL, treeRoot.scrollTop));
        })();
    </script>
@endif

@if($config->renderer()->get('theme', 'light') === 'system')
    <script>
        var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        function updateTheme(e) {
            if (e.matches) {
                window.document.documentElement.setAttribute('data-theme', 'dark');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'dark');
            } else {
                window.document.documentElement.setAttribute('data-theme', 'light');
                window.document.getElementsByName('color-scheme')[0].setAttribute('content', 'light');
            }
        }

        mediaQuery.addEventListener('change', updateTheme);
        updateTheme(mediaQuery);
    </script>
@endif
</body>
</html>
