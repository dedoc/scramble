export type Renderer = 'elements' | 'scalar';
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

export interface IndexedDiagnostic {
    diagnostic: Diagnostic;
    index: number;
}

export interface DiagnosticGroup {
    context: DiagnosticContext | null;
    diagnostics: IndexedDiagnostic[];
}

export interface IssueDatum {
    label: string;
    value: string;
}

export interface ProNudge {
    title: string;
    description: string;
}

export interface DevToolsData {
    diagnostics: Diagnostic[];
    proNudge: ProNudge | null;
    renderer: Renderer;
}
