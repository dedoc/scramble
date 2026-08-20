import '@vitejs/plugin-react/preamble';
import devToolsStyles from './devtools.css?inline';
import { createRoot } from 'react-dom/client';
import { DevToolsApp } from './DevToolsApp';
import { PortalTargetProvider } from './Portal';
import renderers from './renderers';
import type { RendererConfig } from './renderers';
import type { DevToolsData } from './types';

const data: DevToolsData = JSON.parse(
    document.getElementById('scramble-dev-tools-data')?.textContent
        ?? '{"diagnostics":[],"renderer":"elements"}',
);
const renderer: RendererConfig = Object.hasOwn(renderers, data.renderer)
    ? renderers[data.renderer as keyof typeof renderers]
    : renderers.elements;

document.documentElement.dataset.scrambleDevTools = 'enabled';

document.querySelector('scramble-dev-tools')?.remove();

const host = document.createElement('scramble-dev-tools');
const shadow = host.attachShadow({ mode: 'open' });
const stylesheet = document.createElement('style');
const container = document.createElement('div');
const portalTarget = document.createElement('div');
const updateTheme = () => {
    const dark = renderer.theme.current() === 'dark';

    container.classList.toggle('dark', dark);
    portalTarget.classList.toggle('dark', dark);
    host.style.colorScheme = dark ? 'dark' : 'light';
};

stylesheet.textContent = devToolsStyles;
container.id = 'scramble-dev-tools-root';
portalTarget.id = 'scramble-dev-tools-portal-root';
updateTheme();
shadow.append(stylesheet, container, portalTarget);
document.body.append(host);

const unsubscribeFromTheme = renderer.theme.subscribe(updateTheme);

const root = createRoot(container);

root.render(
    <PortalTargetProvider target={portalTarget}>
        <DevToolsApp diagnostics={data.diagnostics} renderer={renderer} />
    </PortalTargetProvider>,
);

if (import.meta.hot) {
    import.meta.hot.accept('./devtools.css?inline', (module) => {
        if (module) {
            stylesheet.textContent = module.default;
        }
    });

    import.meta.hot.dispose(() => {
        unsubscribeFromTheme();
        root.unmount();
        host.remove();
        delete document.documentElement.dataset.scrambleDevTools;
    });
}

document.dispatchEvent(new CustomEvent('scramble:dev-tools:ready'));
