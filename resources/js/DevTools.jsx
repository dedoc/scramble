export function DevTools() {
    return (
        <aside
            aria-label="Scramble developer tools"
            className="fixed top-3 right-3 z-10 antialiased"
        >
            <div
                className="
        inline-flex h-8 items-center
        gap-4 rounded-lg
        bg-white px-3
        shadow-[0_1px_3px_rgba(0,0,0,0.08),0_2px_10px_rgba(0,0,0,0.08),0_0_2px_rgba(0,0,0,0.05)]
    "
            >
    <span className="text-[13px] font-normal leading-none text-gray-500">
        Scramble
    </span>

                <div className="flex items-center gap-2">
        <span
            className="
                relative size-[10px] rounded-full
                bg-rose-500
                before:absolute before:left-1/2 before:top-1/2
                before:h-[1px] before:w-[5px]
                before:-translate-x-1/2 before:-translate-y-1/2
                before:rotate-45 before:bg-white
                after:absolute after:left-1/2 after:top-1/2
                after:h-[1px] after:w-[5px]
                after:-translate-x-1/2 after:-translate-y-1/2
                after:-rotate-45 after:bg-white
            "
        ></span>

                    <span className="text-[13px] font-normal leading-none text-gray-800">
            1 error
        </span>
                </div>

                <div className="flex items-center gap-2">
                    <svg
                        viewBox="0 0 12 10"
                        className="h-[10px] w-3 text-yellow-500"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.128.859a1 1 0 0 1 1.744 0l4.185 7.44A1 1 0 0 1 10.185 9.8h-8.37a1 1 0 0 1-.872-1.5L5.128.858ZM6.5 7.3a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0ZM6 2.5a.5.5 0 0 0-.5.5v2a.5.5 0 0 0 1 0V3a.5.5 0 0 0-.5-.5Z"
                            clip-rule="evenodd"
                        />
                    </svg>

                    <span className="text-[13px] font-normal leading-none text-gray-800">
            2 warnings
        </span>
                </div>
            </div>
        </aside>
    );
}
