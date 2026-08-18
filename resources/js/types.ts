export type DiagnosticSeverity = 'error' | 'warning';

export interface DiagnosticContext {
    key: string;
    type: 'route' | 'class';
    label: string;
    method: string | null;
    detail: string | null;
}

export interface Diagnostic {
    key: string;
    code: string;
    severity: DiagnosticSeverity;
    message: string;
    tip: string | null;
    details: [label: string, value: string][];
    context: DiagnosticContext | null;
}

export interface DevToolsData {
    diagnostics: Diagnostic[];
}
