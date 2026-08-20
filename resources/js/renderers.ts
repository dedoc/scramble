export type RendererNavigationKind = 'operation' | 'schema';
type RendererTheme = 'light' | 'dark';

interface RendererThemeConfig {
    current: () => RendererTheme;
    subscribe: (onChange: () => void) => () => void;
}

export interface RendererConfig {
    navigateTo?: (kind: RendererNavigationKind, id: string) => void;
    theme: RendererThemeConfig;
}

interface ElementsApiElement extends HTMLElement {
    apiDescriptionDocument?: {
        paths?: Record<string, Record<string, { operationId?: string }>>;
    };
}

function normalizePath(path: string) {
    return path.replace(/\{[^}]+}/g, '{}');
}

function resolveOperationId(target: string) {
    const separator = target.indexOf(' ');
    const method = target.slice(0, separator).toLowerCase();
    const routePath = target.slice(separator + 1);
    const apiDocument = (document.getElementById('docs') as ElementsApiElement | null)
        ?.apiDescriptionDocument;
    const candidates = Object.entries(apiDocument?.paths ?? {}).filter(([path]) => {
        const normalizedPath = normalizePath(path);
        const normalizedRoutePath = normalizePath(routePath);

        return normalizedRoutePath === normalizedPath
            || normalizedRoutePath.endsWith(normalizedPath);
    });

    return candidates
        .map(([, operations]) => operations[method]?.operationId)
        .find((operationId): operationId is string => Boolean(operationId));
}

function observeAttribute(
    element: Element,
    attribute: string,
    onChange: () => void,
) {
    const observer = new MutationObserver(onChange);

    observer.observe(element, { attributes: true, attributeFilter: [attribute] });

    return () => observer.disconnect();
}

const scalarColorModeKey = 'colorMode';

export default {
    elements: {
        theme: {
            current: () => document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light',
            subscribe: (onChange) => observeAttribute(
                document.documentElement,
                'data-theme',
                onChange,
            ),
        },
        navigateTo(kind, id) {
            const target = kind === 'operation' ? resolveOperationId(id) : id;

            if (target) {
                window.location.hash = `#/${kind === 'operation' ? 'operations' : 'schemas'}/${target}`;
            }
        },
    },
    scalar: {
        theme: {
            current: () => window.localStorage.getItem(scalarColorModeKey) === 'dark'
                ? 'dark'
                : 'light',
            subscribe(onChange) {
                const onStorage = (event: StorageEvent) => {
                    if (event.key === scalarColorModeKey) {
                        onChange();
                    }
                };
                const stopObservingBody = observeAttribute(document.body, 'class', onChange);

                window.addEventListener('storage', onStorage);

                return () => {
                    window.removeEventListener('storage', onStorage);
                    stopObservingBody();
                };
            },
        },
    },
} satisfies Record<string, RendererConfig>;
