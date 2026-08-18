import '@vitejs/plugin-react/preamble';
import devToolsStyles from './devtools.css?inline';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { DevTools } from './DevTools.jsx';
import { PortalTargetProvider } from './Portal.jsx';

document.documentElement.dataset.scrambleDevTools = 'enabled';

document.querySelector('scramble-dev-tools')?.remove();

const host = document.createElement('scramble-dev-tools');
const shadow = host.attachShadow({ mode: 'open' });
const stylesheet = document.createElement('style');
const container = document.createElement('div');
const portalTarget = document.createElement('div');

stylesheet.textContent = devToolsStyles;
container.id = 'scramble-dev-tools-root';
portalTarget.id = 'scramble-dev-tools-portal-root';
shadow.append(stylesheet, container, portalTarget);
document.body.append(host);

const root = createRoot(container);

root.render(
    createElement(
        PortalTargetProvider,
        { target: portalTarget },
        createElement(DevTools),
    ),
);

if (import.meta.hot) {
    import.meta.hot.accept('./devtools.css?inline', (module) => {
        stylesheet.textContent = module.default;
    });

    import.meta.hot.dispose(() => {
        root.unmount();
        host.remove();
        delete document.documentElement.dataset.scrambleDevTools;
    });
}

document.dispatchEvent(new CustomEvent('scramble:dev-tools:ready'));
