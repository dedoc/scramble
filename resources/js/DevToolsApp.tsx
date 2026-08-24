import { useCallback, useRef, useState } from 'react';
import { ErrorIcon, InfoIcon, SparklesIcon, TickIcon, WarningIcon } from './DiagnosticIcons';
import { IssuesView } from './IssuesView';
import type { RendererConfig } from './renderers';
import type { Diagnostic, ProNudge } from './types';
import { cx, diagnosticCounts } from './utils';

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
                <div
                    className="
                        relative flex h-8 items-stretch rounded-lg bg-white dev-tools-shadow
                        dark:bg-neutral-900 dark:shadow-none dark:inset-ring dark:inset-ring-white/10
                    "
                >
                    <DevToolsTooltip />

                    <button
                        ref={triggerRef}
                        type="button"
                        aria-expanded="false"
                        aria-controls="scramble-issues-panel"
                        onClick={() => setIssuesOpen(true)}
                        className="
                            inline-flex h-8 cursor-pointer items-center gap-2.5 rounded-r-lg px-3
                            outline-none focus-visible:outline-2 focus-visible:outline-offset-2
                            focus-visible:outline-neutral-500 dark:focus-visible:outline-neutral-400
                        "
                    >
                        <div className="flex items-center gap-1.5">
                            {errorCount === 0 && warningCount === 0 && (
                                <TickIcon className="size-4 shrink-0 fill-emerald-500 dark:fill-emerald-400" />
                            )}

                            <span className="text-[13px] font-normal leading-none text-neutral-500 dark:text-neutral-400">
                                Scramble
                            </span>

                            {proNudge && (
                                <SparklesIcon className="-ml-1 size-4 shrink-0 text-neutral-300 dark:text-neutral-500" />
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
                </div>
            )}
        </aside>
    );
}

interface DevToolsTooltipProps {
    className?: string;
}

function DevToolsTooltip({ className }: DevToolsTooltipProps) {
    return (
        <div className={cx('group flex', className)}>
            <button
                type="button"
                aria-label="About Scramble developer tools"
                aria-describedby="scramble-dev-tools-tooltip"
                className="
                    relative flex size-8 cursor-default items-center justify-center rounded-l-lg border-r
                    border-neutral-950/10 outline-none focus-visible:outline-2 focus-visible:outline-offset-2
                    focus-visible:outline-neutral-500 dark:border-white/10 dark:focus-visible:outline-neutral-400
                "
            >
                <InfoIcon
                    className="
                        size-4 shrink-0 stroke-neutral-400 group-hover:stroke-neutral-700
                        group-focus-within:stroke-neutral-700 dark:stroke-neutral-500
                        dark:group-hover:stroke-neutral-200 dark:group-focus-within:stroke-neutral-200
                    "
                />
                <span
                    aria-hidden="true"
                    className="pointer-fine:hidden absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2"
                />
            </button>

            <div
                id="scramble-dev-tools-tooltip"
                role="tooltip"
                className="
                    invisible absolute top-full right-0 z-20 w-[min(18rem,calc(100vw-1.5rem))]
                    pt-2 opacity-0 group-hover:visible group-hover:opacity-100 group-focus-within:visible
                    group-focus-within:opacity-100
                "
            >
                <div
                    className="
                        cursor-text select-text rounded-lg bg-white p-2.5 px-4 text-[0.8125rem]/5
                        text-pretty text-neutral-600 dev-tools-shadow dark:bg-neutral-800
                        dark:text-neutral-300 dark:shadow-none dark:inset-ring dark:inset-ring-white/10
                    "
                >
                    Scramble Dev Tools are visible by default when{' '}
                    <code className="font-mono text-neutral-800 dark:text-neutral-100">
                        APP_DEBUG
                    </code>{' '}
                    is{' '}
                    <code className="font-mono text-neutral-800 dark:text-neutral-100">
                        true
                    </code>. You can enable or disable them using the{' '}
                    <code className="font-mono text-neutral-800 dark:text-neutral-100">
                        SCRAMBLE_DEV_TOOLS
                    </code>{' '}
                    environment variable, or by publishing Scramble&apos;s config and setting{' '}
                    <code className="font-mono text-neutral-800 dark:text-neutral-100">
                        dev_tools.enabled
                    </code>.
                </div>
            </div>
        </div>
    );
}
