import { useCallback, useRef, useState } from 'react';
import { ErrorIcon, SparklesIcon, WarningIcon } from './DiagnosticIcons';
import { IssuesView } from './IssuesView';
import type { RendererConfig } from './renderers';
import type { Diagnostic, ProNudge } from './types';
import { diagnosticCounts } from './utils';

interface DevToolsProps {
    diagnostics: Diagnostic[];
    proNudge: ProNudge | null;
    renderer: RendererConfig;
}

export function DevToolsApp({ diagnostics, proNudge, renderer }: DevToolsProps) {
    const [issuesOpen, setIssuesOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const { error: errorCount, warning: warningCount } = diagnosticCounts(diagnostics);
    const closeIssues = useCallback(() => {
        setIssuesOpen(false);
        requestAnimationFrame(() => triggerRef.current?.focus());
    }, []);

    return (
        <aside
            aria-label="Scramble developer tools"
            className="fixed top-3 right-3 z-10 antialiased"
        >
            {issuesOpen ? (
                <IssuesView
                    diagnostics={diagnostics}
                    proNudge={proNudge}
                    onClose={closeIssues}
                    onNavigate={renderer.navigateTo}
                />
            ) : (
                <button
                    ref={triggerRef}
                    type="button"
                    aria-expanded="false"
                    aria-controls="scramble-issues-panel"
                    onClick={() => setIssuesOpen(true)}
                    className="
                        inline-flex h-8 cursor-pointer items-center gap-4 rounded-lg bg-white px-3
                        dev-tools-shadow
                        outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-500
                        dark:bg-neutral-900 dark:shadow-none dark:inset-ring dark:inset-ring-white/10
                        dark:focus-visible:outline-neutral-400
                    "
                >
                    <div className="flex items-center gap-1">
                        <span className="text-[13px] font-normal leading-none text-neutral-500 dark:text-neutral-400">
                            Scramble
                        </span>

                        {proNudge && (
                            <SparklesIcon className="size-4 shrink-0 text-neutral-300 dark:text-neutral-500" />
                        )}
                    </div>

                    {errorCount > 0 && (
                        <div className="flex items-center gap-1">
                            <ErrorIcon />

                            <span className="text-[13px] font-normal leading-none text-neutral-800 dark:text-neutral-100">
                                {errorCount} {errorCount === 1 ? 'error' : 'errors'}
                            </span>
                        </div>
                    )}

                    {warningCount > 0 && (
                        <div className="flex items-center gap-1">
                            <WarningIcon />

                            <span className="text-[13px] font-normal leading-none text-neutral-800 dark:text-neutral-100">
                                {warningCount} {warningCount === 1 ? 'warning' : 'warnings'}
                            </span>
                        </div>
                    )}
                </button>
            )}
        </aside>
    );
}
