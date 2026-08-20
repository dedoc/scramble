import { SparklesIcon } from './DiagnosticIcons';
import type { ProNudges } from './types';
import { cx, formatConjunction, proNudgePitch } from './utils';

interface ProNudgeCardProps {
    className?: string;
    proNudges: ProNudges;
}

export function ProNudgeCard({ className, proNudges }: ProNudgeCardProps) {
    const nudges = Object.values(proNudges);

    if (nudges.length === 0) {
        return null;
    }

    const summary = formatConjunction(nudges);

    return (
        <div
            className={cx(
                `shrink-0 rounded-lg bg-white bg-[radial-gradient(circle_at_100%_90%,color-mix(in_oklab,var(--color-yellow-100)_50%,white),white_100%)]
                px-4 py-3 dev-tools-shadow inset-ring inset-ring-white/60
                dark:bg-neutral-800 dark:bg-none dark:shadow-none dark:inset-ring-white/10`,
                className,
            )}
        >
            <p className="text-[13px] leading-[18px] font-medium text-gray-800 dark:text-neutral-100">
                <SparklesIcon className="mr-1 inline-block size-4 -translate-y-px text-orange-400" />
                {summary}
            </p>

            <p className="mt-2 text-[13px] leading-[18px] text-gray-600 dark:text-neutral-300">
                {proNudgePitch(proNudges)}
            </p>

            <a
                href="https://scramble.dedoc.co/pro?utm_source=devtools"
                target="_blank"
                rel="noopener noreferrer"
                className="mt-2 inline-flex items-center gap-1 border-b border-black/30 text-[13px] leading-[18px] font-medium text-gray-800 outline-none
                    focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-500
                    dark:border-white/30 dark:text-neutral-100 dark:focus-visible:outline-neutral-400"
            >
                Learn more
                <span aria-hidden="true">→</span>
            </a>
        </div>
    );
}
