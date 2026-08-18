import { createContext, useContext } from 'react';
import { createPortal } from 'react-dom';

const PortalTargetContext = createContext(null);

export function PortalTargetProvider({ children, target }) {
    return (
        <PortalTargetContext.Provider value={target}>
            {children}
        </PortalTargetContext.Provider>
    );
}

export function Portal({ children }) {
    const target = useContext(PortalTargetContext);

    if (!target) {
        throw new Error('Portal must be rendered inside DevTools.');
    }

    return createPortal(children, target);
}
