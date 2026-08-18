function cx(...classes: Array<string | undefined>) {
    return classes.filter(Boolean).join(' ');
}

interface DiagnosticIconProps {
    className?: string;
}

export function ErrorIcon({ className = 'size-[10px]' }: DiagnosticIconProps) {
    return (
        <span
            aria-hidden="true"
            className={cx(
                `relative rounded-full
                bg-rose-500
                before:absolute before:left-1/2 before:top-1/2
                before:h-[1px] before:w-[5px]
                before:-translate-x-1/2 before:-translate-y-1/2
                before:rotate-45 before:bg-white
                after:absolute after:left-1/2 after:top-1/2
                after:h-[1px] after:w-[5px]
                after:-translate-x-1/2 after:-translate-y-1/2
                after:-rotate-45 after:bg-white`,
                className,
            )}
        />
    );
}

export function WarningIcon({ className = 'h-[10px] w-3' }: DiagnosticIconProps) {
    return (
        <svg
            viewBox="0 0 12 10"
            className={cx('text-yellow-500', className)}
            fill="currentColor"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                d="M5.128.859a1 1 0 0 1 1.744 0l4.185 7.44A1 1 0 0 1 10.185 9.8h-8.37a1 1 0 0 1-.872-1.5L5.128.858ZM6.5 7.3a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0ZM6 2.5a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 1 0V3a.5.5 0 0 0-.5-.5Z"
                clipRule="evenodd"
            />
        </svg>
    );
}
