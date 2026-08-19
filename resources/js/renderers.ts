export type RendererNavigationKind = 'operation' | 'schema';

export interface RendererConfig {
    navigateTo?: (kind: RendererNavigationKind, id: string) => void;
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

export default {
    elements: {
        navigateTo(kind, id) {
            const target = kind === 'operation' ? resolveOperationId(id) : id;

            if (target) {
                window.location.hash = `#/${kind === 'operation' ? 'operations' : 'schemas'}/${target}`;
            }
        },
    },
} satisfies Record<string, RendererConfig>;
