import { createContext, useContext, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

const PortalTargetContext = createContext<HTMLElement | null>(null);

interface PortalTargetProviderProps {
    children: ReactNode;
    target: HTMLElement;
}

export function PortalTargetProvider({ children, target }: PortalTargetProviderProps) {
    return (
        <PortalTargetContext.Provider value={target}>
            {children}
        </PortalTargetContext.Provider>
    );
}

interface PortalProps {
    children: ReactNode;
}

export function Portal({ children }: PortalProps) {
    const target = useContext(PortalTargetContext);

    if (!target) {
        throw new Error('Portal must be rendered inside DevTools.');
    }

    return createPortal(children, target);
}
