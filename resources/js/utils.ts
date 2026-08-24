import type {
    Diagnostic,
    DiagnosticContext,
    DiagnosticGroup,
    DiagnosticSeverity,
    IssueDatum,
} from './types';

const hiddenDatums = ['Expression'];

export function cx(...classes: Array<string | false | null | undefined>) {
    return classes.filter(Boolean).join(' ');
}

export function issueLabel(count: number, singular: string) {
    return `${count} ${count === 1 ? singular : `${singular}s`}`;
}

export function diagnosticCounts(diagnostics: Diagnostic[]): Record<DiagnosticSeverity, number> {
    const counts: Record<DiagnosticSeverity, number> = { error: 0, warning: 0 };

    diagnostics.forEach(({ severity }) => {
        counts[severity] += 1;
    });

    return counts;
}

export async function copyText(text: string) {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {
            // Fall back for browsers that expose the API but block it outside a secure context.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.readOnly = true;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
    document.body.append(textarea);
    textarea.select();

    try {
        return document.execCommand('copy');
    } finally {
        textarea.remove();
    }
}

export function groupDiagnostics(diagnostics: Diagnostic[]): DiagnosticGroup[] {
    const groups = new Map<string, DiagnosticGroup>();

    diagnostics.forEach((diagnostic, index) => {
        const key = diagnostic.context?.key ?? 'general';
        const group = groups.get(key) ?? {
            context: diagnostic.context,
            diagnostics: [],
        };

        group.diagnostics.push({ diagnostic, index });
        groups.set(key, group);
    });

    const rank: Record<DiagnosticContext['type'], number> = { route: 0, class: 1 };

    return Array.from(groups.values()).sort((a, b) => (
        (a.context ? rank[a.context.type] : 2) - (b.context ? rank[b.context.type] : 2)
    ));
}

export function issueData(diagnostic: Diagnostic): IssueDatum[] {
    const data: IssueDatum[] = diagnostic.details
        .filter(([label]) => !hiddenDatums.includes(label))
        .map(([label, value]) => ({ label, value }));

    if (diagnostic.tip) {
        data.push({ label: 'Tip', value: diagnostic.tip });
    }

    return data;
}

function escapeMarkdown(value: string) {
    return value
        .replace(/\\/g, '\\\\')
        .replace(/([*_\[\]<>])/g, '\\$1')
        .replace(/\r?\n/g, '<br>');
}

export function diagnosticsAsMarkdown(diagnostics: Diagnostic[]) {
    const sections = groupDiagnostics(diagnostics).map(({ context, diagnostics: group }) => {
        const method = context?.method ? `${escapeMarkdown(context.method)} ` : '';
        const heading = `## ${method}${escapeMarkdown(context?.label ?? 'General')}`;
        const issues = group.map(({ diagnostic }) => {
            const lines = [
                `- **${escapeMarkdown(diagnostic.code)}** (${diagnostic.severity}): ${escapeMarkdown(diagnostic.message)}`,
                ...issueData(diagnostic).map(({ label, value }) => (
                    `  - **${escapeMarkdown(label)}:** ${escapeMarkdown(value)}`
                )),
            ];

            return lines.join('\n');
        });

        return [heading, ...issues].join('\n');
    });

    return ['# Issues', 'Scramble encountered these issues while generating the API documentation.', ...sections].join('\n\n');
}
