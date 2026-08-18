export function DevTools() {
    return (
        <aside
            aria-label="Scramble developer tools"
            className="fixed top-3 right-3 z-10 flex max-w-[calc(100vw-1.5rem)] items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 font-sans text-sm font-medium text-slate-100 shadow-lg"
        >
            <span
                aria-hidden="true"
                className="size-2 shrink-0 rounded-full bg-emerald-400"
            />
            <span className="truncate">Scramble Dev Tooling</span>
        </aside>
    );
}
