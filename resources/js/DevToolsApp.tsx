import { useCallback, useRef, useState } from 'react';
import { ErrorIcon, WarningIcon } from './DiagnosticIcons';
import { IssuesView } from './IssuesView';
import type { Diagnostic } from './types';

interface DevToolsProps {
    diagnostics: Diagnostic[];
}

export function DevToolsApp({ diagnostics }: DevToolsProps) {
    const [issuesOpen, setIssuesOpen] = useState(false);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const errorCount = diagnostics.filter(({ severity }) => severity === 'error').length;
    const warningCount = diagnostics.filter(({ severity }) => severity === 'warning').length;
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
                <IssuesView diagnostics={diagnostics} onClose={closeIssues} />
            ) : (
                <button
                    ref={triggerRef}
                    type="button"
                    aria-expanded="false"
                    aria-controls="scramble-issues-panel"
                    onClick={() => setIssuesOpen(true)}
                    className="
                        inline-flex h-8 cursor-pointer items-center gap-4 rounded-lg bg-white px-3
                        shadow-[0_1px_3px_rgba(0,0,0,0.08),0_2px_10px_rgba(0,0,0,0.08),0_0_2px_rgba(0,0,0,0.05)]
                        outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500
                    "
                >
                    <span className="text-[13px] font-normal leading-none text-gray-500">
                        Scramble
                    </span>

                    {errorCount > 0 && (
                        <div className="flex items-center gap-2">
                            <ErrorIcon />

                            <span className="text-[13px] font-normal leading-none text-gray-800">
                                {errorCount} {errorCount === 1 ? 'error' : 'errors'}
                            </span>
                        </div>
                    )}

                    {warningCount > 0 && (
                        <div className="flex items-center gap-2">
                            <WarningIcon />

                            <span className="text-[13px] font-normal leading-none text-gray-800">
                                {warningCount} {warningCount === 1 ? 'warning' : 'warnings'}
                            </span>
                        </div>
                    )}
                </button>
            )}
        </aside>
    );
}
