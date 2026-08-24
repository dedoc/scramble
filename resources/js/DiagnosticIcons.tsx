import { cx } from './utils';

interface DiagnosticIconProps {
    className?: string;
}

export function MarkdownIcon({ className = 'h-3.5 w-[18px] shrink-0' }: DiagnosticIconProps) {
    return (
        <svg
            viewBox="0 0 208 128"
            className={className}
            fill="none"
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
        >
            <rect
                x="5"
                y="5"
                width="198"
                height="118"
                rx="10"
                stroke="currentColor"
                strokeWidth="10"
            />
            <path
                d="M30 98V30H50L70 55L90 30H110V98H90V59L70 84L50 59V98H30Z"
                fill="currentColor"
            />
            <path
                d="M145 30V70H120L158 104L196 70H171V30H145Z"
                fill="currentColor"
            />
        </svg>
    );
}

export function TickIcon({ className = 'size-4 shrink-0 fill-teal-600 dark:fill-teal-400' }: DiagnosticIconProps) {
    return (
        <svg
            width="20"
            height="20"
            viewBox="0 0 20 20"
            className={className}
            fill="none"
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M16.7071 5.29289C17.0976 5.68342 17.0976 6.31658 16.7071 6.70711L8.70711 14.7071C8.31658 15.0976 7.68342 15.0976 7.29289 14.7071L3.29289 10.7071C2.90237 10.3166 2.90237 9.68342 3.29289 9.29289C3.68342 8.90237 4.31658 8.90237 4.70711 9.29289L8 12.5858L15.2929 5.29289C15.6834 4.90237 16.3166 4.90237 16.7071 5.29289Z"
            />
        </svg>
    );
}

export function ErrorIcon({ className = 'size-3' }: DiagnosticIconProps) {
    return <svg className={cx('text-rose-500 dark:text-rose-400', className)} aria-hidden="true" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path fillRule="evenodd" clipRule="evenodd" d="M5.99922 10.8002C8.65019 10.8002 10.7992 8.65116 10.7992 6.0002C10.7992 3.34923 8.65019 1.2002 5.99922 1.2002C3.34825 1.2002 1.19922 3.34923 1.19922 6.0002C1.19922 8.65116 3.34825 10.8002 5.99922 10.8002ZM5.22348 4.37593C4.98917 4.14162 4.60927 4.14162 4.37495 4.37593C4.14064 4.61025 4.14064 4.99015 4.37495 5.22446L5.15069 6.0002L4.37495 6.77593C4.14064 7.01025 4.14064 7.39014 4.37495 7.62446C4.60927 7.85877 4.98917 7.85877 5.22348 7.62446L5.99922 6.84872L6.77496 7.62446C7.00927 7.85877 7.38917 7.85877 7.62348 7.62446C7.8578 7.39015 7.8578 7.01025 7.62348 6.77593L6.84775 6.0002L7.62348 5.22446C7.8578 4.99015 7.8578 4.61025 7.62348 4.37593C7.38917 4.14162 7.00927 4.14162 6.77496 4.37593L5.99922 5.15167L5.22348 4.37593Z" fill="currentColor"/>
    </svg>
}

export function WarningIcon({ className = 'h-[10px] w-3' }: DiagnosticIconProps) {
    return (
        <svg
            viewBox="0 0 12 10"
            className={cx('text-yellow-500 dark:text-yellow-400', className)}
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

export function SparklesIcon({ className = 'size-4 shrink-0' }: DiagnosticIconProps) {
    return (
        <svg
            viewBox="0 0 16 16"
            className={className}
            fill="currentColor"
            aria-hidden="true"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M6.4 1.2a.6.6 0 0 1 1.2 0c0 2.04 1.56 3.6 3.6 3.6a.6.6 0 0 1 0 1.2C9.16 6 7.6 7.56 7.6 9.6a.6.6 0 0 1-1.2 0C6.4 7.56 4.84 6 2.8 6a.6.6 0 0 1 0-1.2c2.04 0 3.6-1.56 3.6-3.6Z" />
            <path d="M12.1 9.4a.5.5 0 0 1 1 0c0 1.08.82 1.9 1.9 1.9a.5.5 0 0 1 0 1c-1.08 0-1.9.82-1.9 1.9a.5.5 0 0 1-1 0c0-1.08-.82-1.9-1.9-1.9a.5.5 0 0 1 0-1c1.08 0 1.9-.82 1.9-1.9Z" />
        </svg>
    );
}

export function InfoIcon({ className = 'size-4 shrink-0' }: DiagnosticIconProps) {
    return (
        <svg
            viewBox="0 0 16 16"
            className={className}
            fill="none"
            strokeWidth="1.5"
            aria-hidden="true"
        >
            <circle cx="8" cy="8" r="6.25" />
            <path strokeLinecap="round" d="M8 7.25v3.5M8 5.25h.01" />
        </svg>
    );
}
