import '@vitejs/plugin-react/preamble';
import devToolsStyles from './devtools.css?inline';
import { createRoot } from 'react-dom/client';
import { DevToolsApp } from './DevToolsApp';
import renderers from './renderers';
import type { RendererConfig } from './renderers';
import type { DevToolsData } from './types';

const data: DevToolsData = JSON.parse(
    document.getElementById('scramble-dev-tools-data')?.textContent
        ?? '{"diagnostics":[],"proNudge":null,"renderer":"elements"}',
);
const renderer: RendererConfig = Object.hasOwn(renderers, data.renderer)
    ? renderers[data.renderer as keyof typeof renderers]
    : renderers.elements;

document.querySelector('scramble-dev-tools')?.remove();

const host = document.createElement('scramble-dev-tools');
const shadow = host.attachShadow({ mode: 'open' });
const stylesheet = document.createElement('style');
const container = document.createElement('div');
const updateTheme = () => {
    const dark = renderer.theme.current() === 'dark';

    container.classList.toggle('dark', dark);
    host.style.colorScheme = dark ? 'dark' : 'light';
};

stylesheet.textContent = devToolsStyles;
updateTheme();
shadow.append(stylesheet, container);
document.body.append(host);

const unsubscribeFromTheme = renderer.theme.subscribe(updateTheme);

const root = createRoot(container);

root.render(
    <DevToolsApp
        diagnostics={data.diagnostics}
        proNudge={data.proNudge ?? null}
        renderer={renderer}
    />,
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
    });
}
