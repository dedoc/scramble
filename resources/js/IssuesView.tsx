import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { ErrorIcon, WarningIcon } from './DiagnosticIcons';
import type { Diagnostic, DiagnosticContext, DiagnosticSeverity } from './types';

function cx(...classes: Array<string | false | null | undefined>) {
    return classes.filter(Boolean).join(' ');
}

function issueLabel(count: number, singular: string) {
    return `${count} ${count === 1 ? singular : `${singular}s`}`;
}

interface IndexedDiagnostic {
    diagnostic: Diagnostic;
    index: number;
}

interface DiagnosticGroup {
    context: DiagnosticContext | null;
    diagnostics: IndexedDiagnostic[];
}

function groupDiagnostics(diagnostics: Diagnostic[]): DiagnosticGroup[] {
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

interface ClassNameProps {
    className?: string;
}

interface CloseButtonProps extends ClassNameProps {
    onClose: () => void;
}

export function CloseButton({ className, onClose }: CloseButtonProps) {
    return (
        <button
            type="button"
            className={cx(
                `flex size-7 items-center justify-center rounded text-gray-500
                outline-none hover:cursor-pointer hover:text-gray-800
                focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500`,
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

export function IssuesHeader({ className, onClose }: CloseButtonProps) {
    return (
        <header className={cx('flex pt-3 pb-1 items-center justify-between px-4', className)}>
            <span className="text-sm font-semibold text-gray-800">Issues</span>
            <CloseButton className="-mr-2.5" onClose={onClose} />
        </header>
    );
}

interface IssueTabProps extends ClassNameProps {
    active: boolean;
    children: ReactNode;
    onClick: () => void;
}

export function IssueTab({ active, children, className, onClick }: IssueTabProps) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls="scramble-issues-panel"
            className={cx(
                `relative flex items-center gap-1 p-2 text-xs leading-3.75 font-medium outline-none
                focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-gray-500`,
                active
                    ? 'text-gray-900 after:absolute after:inset-x-0 after:-bottom-px after:h-px after:bg-gray-900'
                    : 'text-gray-600 hover:cursor-pointer hover:text-gray-900',
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

export function IssuesTabs({
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
            className={cx('flex items-end border-b border-gray-200 px-4', className)}
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

export function IssueItem({ className, diagnostic }: IssueItemProps) {
    const detail = diagnostic.context?.detail ?? diagnostic.details.at(-1)?.[1];
    const Icon = diagnostic.severity === 'error' ? ErrorIcon : WarningIcon;

    return (
        <li className={cx('flex flex-col', className)}>
            <div className="flex items-start gap-2">
                <Icon className="mt-1 size-3 shrink-0" />
                <span className="min-w-0 break-words text-[13px] leading-5 text-gray-800">
                    {diagnostic.message}
                </span>
            </div>

            <div className="break-words pl-5 text-xs leading-5 text-gray-500">
                {diagnostic.code}{detail ? ` · ${detail}` : ''}
            </div>
        </li>
    );
}

interface IssueGroupProps extends ClassNameProps {
    context: DiagnosticContext | null;
    diagnostics: IndexedDiagnostic[];
}

export function IssueGroup({ className, context, diagnostics }: IssueGroupProps) {
    return (
        <section className={cx('flex flex-col gap-3 border-b border-gray-200 px-4 py-3.5 last:border-b-0', className)}>
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2 font-mono text-[13px]">
                    {context?.method && (
                        <span className="shrink-0 text-[#919FB4]">{context.method}</span>
                    )}
                    <span className="truncate font-medium text-gray-800">
                        {context?.label ?? 'General'}
                    </span>
                </div>

                <span className="shrink-0 text-xs text-gray-500">{diagnostics.length}</span>
            </div>

            <ul className="flex flex-col gap-3.5">
                {diagnostics.map(({ diagnostic, index }) => (
                    <IssueItem key={`${diagnostic.key}:${index}`} diagnostic={diagnostic} />
                ))}
            </ul>
        </section>
    );
}

export function EmptyIssues({ className }: ClassNameProps) {
    return (
        <div className={cx('px-4 py-6 text-center text-[13px] text-gray-500', className)}>
            No issues found
        </div>
    );
}

interface IssuesViewProps extends ClassNameProps {
    diagnostics: Diagnostic[];
    onClose: () => void;
}

export function IssuesView({ className, diagnostics, onClose }: IssuesViewProps) {
    const [activeSeverity, setActiveSeverity] = useState<IssueFilter>('all');
    const errorCount = diagnostics.filter(({ severity }) => severity === 'error').length;
    const warningCount = diagnostics.filter(({ severity }) => severity === 'warning').length;
    const visibleDiagnostics = useMemo(
        () => activeSeverity === 'all'
            ? diagnostics
            : diagnostics.filter(({ severity }) => severity === activeSeverity),
        [activeSeverity, diagnostics],
    );
    const groups = useMemo(() => groupDiagnostics(visibleDiagnostics), [visibleDiagnostics]);

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
        <section
            aria-label="Scramble issues"
            className={cx(
                `w-[360px] max-w-[calc(100vw-24px)] overflow-hidden rounded-lg bg-white
                shadow-[0_1px_3px_rgba(0,0,0,0.08),0_2px_10px_rgba(0,0,0,0.08),0_0_2px_rgba(0,0,0,0.05)]`,
                className,
            )}
        >
            <IssuesHeader onClose={onClose} />
            <IssuesTabs
                activeSeverity={activeSeverity}
                errorCount={errorCount}
                warningCount={warningCount}
                onChange={setActiveSeverity}
            />

            <div
                id="scramble-issues-panel"
                role="tabpanel"
                className="max-h-[min(560px,calc(100vh-89px))] overflow-y-auto"
            >
                {groups.length > 0
                    ? groups.map(({ context, diagnostics: groupDiagnostics }) => (
                        <IssueGroup
                            key={context?.key ?? 'general'}
                            context={context}
                            diagnostics={groupDiagnostics}
                        />
                    ))
                    : <EmptyIssues />}
            </div>
        </section>
    );
}
