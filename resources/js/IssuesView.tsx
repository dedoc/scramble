import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ErrorIcon, MarkdownIcon, TickIcon, WarningIcon } from './DiagnosticIcons';
import { ProNudgeCard } from './ProNudgeCard';
import type {
    Diagnostic,
    DiagnosticContext,
    DiagnosticSeverity,
    IndexedDiagnostic,
    IssueDatum,
    ProNudge,
} from './types';
import {
    copyText,
    cx,
    diagnosticCounts,
    diagnosticsAsMarkdown,
    groupDiagnostics,
    issueData,
    issueLabel,
} from './utils';
import type { RendererNavigationKind } from './renderers';

interface ClassNameProps {
    className?: string;
}

interface CloseButtonProps extends ClassNameProps {
    onClose: () => void;
}

function CloseButton({ className, onClose }: CloseButtonProps) {
    return (
        <button
            type="button"
            className={cx(
                `flex size-7 items-center justify-center rounded text-neutral-500
                outline-none hover:cursor-pointer hover:text-neutral-800
                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-500
                dark:text-neutral-400 dark:hover:text-neutral-100 dark:focus-visible:outline-neutral-400`,
                className,
            )}
            aria-label="Close issues"
            onClick={onClose}
            autoFocus
        >
            <svg
                viewBox="0 0 20 20"
                className="size-4"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                aria-hidden="true"
            >
                <path d="M4 4l12 12M16 4 4 16" />
            </svg>
        </button>
    );
}

interface IssuesHeaderProps extends CloseButtonProps {
    copied: boolean;
    copyDisabled?: boolean;
    onCopy: () => void;
    showCopy?: boolean;
}

function IssuesHeader({
    className,
    copied,
    copyDisabled,
    onClose,
    onCopy,
    showCopy = true,
}: IssuesHeaderProps) {
    return (
        <header className={cx('flex pt-2 pb-1 items-center justify-between px-4', className)}>
            <div className="flex min-w-0 items-center gap-3">
                <span className="text-sm font-semibold text-neutral-800 dark:text-neutral-100">Issues</span>
                {showCopy && (
                    <button
                        type="button"
                        className={cx(
                            `flex items-center gap-1.5 rounded text-xs text-neutral-500 outline-none
                            hover:cursor-pointer hover:text-neutral-800 disabled:cursor-default disabled:opacity-40
                            focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-500
                            dark:text-neutral-400 dark:hover:text-neutral-100 dark:focus-visible:outline-neutral-400`,
                            copied && 'pointer-events-none',
                        )}
                        disabled={copyDisabled}
                        onClick={onCopy}
                    >
                        {copied ? <TickIcon /> : <MarkdownIcon />}
                        <span aria-live="polite">{copied ? 'Copied!' : 'Copy as markdown'}</span>
                    </button>
                )}
            </div>
            <CloseButton className="-mr-2.5" onClose={onClose} />
        </header>
    );
}

interface IssueTabProps extends ClassNameProps {
    active: boolean;
    children: ReactNode;
    onClick: () => void;
}

function IssueTab({ active, children, className, onClick }: IssueTabProps) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls="scramble-issues-panel"
            className={cx(
                `relative flex items-center gap-1 p-2 text-xs leading-3.75 font-medium outline-none
                focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-neutral-500
                dark:focus-visible:outline-neutral-400`,
                active
                    ? `text-neutral-900 after:absolute after:inset-x-0 after:-bottom-px after:h-px after:bg-neutral-900
                    dark:text-neutral-100 dark:after:bg-neutral-100`
                    : 'text-neutral-600 hover:cursor-pointer hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100',
                className,
            )}
            onClick={onClick}
        >
            {children}
        </button>
    );
}

type IssueFilter = 'all' | DiagnosticSeverity;

interface IssuesTabsProps extends ClassNameProps {
    activeSeverity: IssueFilter;
    errorCount: number;
    onChange: (severity: IssueFilter) => void;
    warningCount: number;
}

function IssuesTabs({
    activeSeverity,
    className,
    errorCount,
    onChange,
    warningCount,
}: IssuesTabsProps) {
    return (
        <div
            role="tablist"
            aria-label="Filter issues"
            className={cx('flex items-end border-b border-neutral-200 px-4 dark:border-white/10', className)}
        >
            <IssueTab active={activeSeverity === 'all'} onClick={() => onChange('all')}>
                All
            </IssueTab>

            {errorCount > 0 && (
                <IssueTab active={activeSeverity === 'error'} onClick={() => onChange('error')}>
                    <ErrorIcon className="size-3" />
                    <span>{issueLabel(errorCount, 'error')}</span>
                </IssueTab>
            )}

            {warningCount > 0 && (
                <IssueTab active={activeSeverity === 'warning'} onClick={() => onChange('warning')}>
                    <WarningIcon className="size-3" />
                    <span>{issueLabel(warningCount, 'warning')}</span>
                </IssueTab>
            )}
        </div>
    );
}

interface IssueItemProps extends ClassNameProps {
    diagnostic: Diagnostic;
}

function IssueDatumGrid({ className, data }: ClassNameProps & { data: IssueDatum[] }) {
    if (data.length === 0) {
        return null;
    }

    return (
        <dl
            className={cx(
                'grid grid-cols-[62px_minmax(0,1fr)] gap-x-3 gap-y-1 text-xs leading-4',
                className,
            )}
        >
            {data.map(({ label, value }, index) => (
                <div key={`${label}:${index}`} className="contents">
                    <dt className="w-[62px] text-neutral-400 dark:text-neutral-500">{label}</dt>
                    <dd className="min-w-0 break-words text-neutral-500 dark:text-neutral-400">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

function IssueItem({ className, diagnostic }: IssueItemProps) {
    const Icon = diagnostic.severity === 'error' ? ErrorIcon : WarningIcon;

    return (
        <li className={cx('flex flex-col gap-1', className)}>
            <div className="leading-[16px]">
                <Icon className="size-3 shrink-0 inline-block mr-1 -translate-y-px" />
                <span className="min-w-0 break-words text-[13px] text-neutral-800 dark:text-neutral-100">
                    <span className="text-neutral-500 dark:text-neutral-400">{diagnostic.code}</span> {diagnostic.message}
                </span>
            </div>

            <IssueDatumGrid data={issueData(diagnostic)} />
        </li>
    );
}

interface IssueGroupProps extends ClassNameProps {
    context: DiagnosticContext | null;
    diagnostics: IndexedDiagnostic[];
    onNavigate?: (kind: RendererNavigationKind, id: string) => void;
}

function IssueGroup({ className, context, diagnostics, onNavigate }: IssueGroupProps) {
    const navigationKind = context?.type === 'route' ? 'operation' : 'schema';
    const navigationId = context?.type === 'route'
        ? `${context.method} ${context.label}`
        : context?.label;
    const header = (
        <>
            <div className="flex min-w-0 items-center gap-2 font-mono text-[13px]">
                {context?.method && (
                    <span className="shrink-0 text-neutral-400">{context.method}</span>
                )}
                <span className="truncate font-medium text-neutral-800 dark:text-neutral-100">
                    {context?.label ?? 'General'}
                </span>
            </div>

            <span className="shrink-0 text-xs text-neutral-500 dark:text-neutral-400">{diagnostics.length}</span>
        </>
    );

    return (
        <section className={cx('flex flex-col gap-3 border-b border-neutral-200 px-4 py-3.5 last:border-b-0 dark:border-white/10', className)}>
            {onNavigate && navigationId ? (
                <button
                    type="button"
                    onClick={() => onNavigate(navigationKind, navigationId)}
                    className="-m-1 flex cursor-pointer items-center justify-between gap-3 rounded p-1 text-left outline-none focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-neutral-500 dark:focus-visible:outline-neutral-400"
                >
                    {header}
                </button>
            ) : (
                <div className="flex items-center justify-between gap-3">
                    {header}
                </div>
            )}

            <ul className="flex flex-col gap-3.5">
                {diagnostics.map(({ diagnostic, index }) => (
                    <IssueItem key={`${diagnostic.key}:${index}`} diagnostic={diagnostic} />
                ))}
            </ul>
        </section>
    );
}

function EmptyIssues({ className }: ClassNameProps) {
    return (
        <div className={cx('flex flex-col items-center gap-3 px-6 pt-4 pb-12 text-center', className)}>
            <TickIcon className="size-5 shrink-0 fill-emerald-500 dark:fill-emerald-400" />

            <div className="flex flex-col items-center gap-1">
                <p className="text-base font-medium text-neutral-800 dark:text-neutral-100 sm:text-sm">
                    All clear
                </p>
                <p className="max-w-[32ch] text-pretty text-[0.8125rem] text-neutral-500 dark:text-neutral-400">
                    Nice! Your API documentation was generated without any issues.
                </p>
            </div>
        </div>
    );
}

interface IssuesViewProps extends ClassNameProps {
    diagnostics: Diagnostic[];
    proNudge: ProNudge | null;
    onClose: () => void;
    onNavigate?: (kind: RendererNavigationKind, id: string) => void;
}

export function IssuesView({
    className,
    diagnostics,
    proNudge,
    onClose,
    onNavigate,
}: IssuesViewProps) {
    const [activeSeverity, setActiveSeverity] = useState<IssueFilter>('all');
    const [copied, setCopied] = useState(false);
    const copiedTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const { error: errorCount, warning: warningCount } = diagnosticCounts(diagnostics);
    const visibleDiagnostics = useMemo(
        () => activeSeverity === 'all'
            ? diagnostics
            : diagnostics.filter(({ severity }) => severity === activeSeverity),
        [activeSeverity, diagnostics],
    );
    const groups = useMemo(() => groupDiagnostics(visibleDiagnostics), [visibleDiagnostics]);
    const copyAsMarkdown = async () => {
        if (!await copyText(diagnosticsAsMarkdown(diagnostics))) {
            return;
        }

        setCopied(true);

        if (copiedTimer.current) {
            clearTimeout(copiedTimer.current);
        }

        copiedTimer.current = setTimeout(() => {
            setCopied(false);
            copiedTimer.current = null;
        }, 2000);
    };

    useEffect(() => () => {
        if (copiedTimer.current) {
            clearTimeout(copiedTimer.current);
        }
    }, []);

    useEffect(() => {
        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', closeOnEscape);

        return () => document.removeEventListener('keydown', closeOnEscape);
    }, [onClose]);

    return (
        <div
            className={cx(
                'flex max-h-[calc(100dvh-24px)] w-[360px] max-w-[calc(100vw-24px)] flex-col gap-2',
                className,
            )}
        >
            <section
                aria-label="Scramble issues"
                className="flex min-h-0 flex-col overflow-hidden rounded-lg bg-white
                    dev-tools-shadow
                    dark:bg-neutral-900 dark:shadow-none dark:inset-ring dark:inset-ring-white/10"
            >
                <IssuesHeader
                    copied={copied}
                    copyDisabled={diagnostics.length === 0}
                    onClose={onClose}
                    onCopy={copyAsMarkdown}
                    showCopy={diagnostics.length > 0}
                />
                {diagnostics.length > 0 && (
                    <IssuesTabs
                        activeSeverity={activeSeverity}
                        errorCount={errorCount}
                        warningCount={warningCount}
                        onChange={setActiveSeverity}
                    />
                )}

                <div
                    id="scramble-issues-panel"
                    role="tabpanel"
                    className="min-h-0 overscroll-contain overflow-y-auto"
                >
                    {groups.length > 0
                        ? groups.map(({ context, diagnostics: groupDiagnostics }) => (
                            <IssueGroup
                                key={context?.key ?? 'general'}
                                context={context}
                                diagnostics={groupDiagnostics}
                                onNavigate={onNavigate}
                            />
                        ))
                        : <EmptyIssues />}
                </div>
            </section>

            <ProNudgeCard proNudge={proNudge} />
        </div>
    );
}
