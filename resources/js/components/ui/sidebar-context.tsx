import * as React from 'react';

import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';

const SIDEBAR_COOKIE_NAME = 'sidebar_state';
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7;
const SIDEBAR_WIDTH = '14.5rem';
const SIDEBAR_WIDTH_ICON = '3rem';
const SIDEBAR_KEYBOARD_SHORTCUT = 'b';

type SidebarContextValue = {
    state: 'expanded' | 'collapsed';
    open: boolean;
    setOpen: (open: boolean) => void;
    openMobile: boolean;
    setOpenMobile: (open: boolean) => void;
    isMobile: boolean;
    toggleSidebar: () => void;
};

const SidebarContext = React.createContext<SidebarContextValue | null>(null);

function useSidebar() {
    const context = React.useContext(SidebarContext);

    if (!context) {
        throw new Error('useSidebar must be used within a SidebarProvider.');
    }

    return context;
}

function SidebarProvider({
    defaultOpen = true,
    open: openProp,
    onOpenChange: setOpenProp,
    className,
    style,
    children,
    ...props
}: React.ComponentProps<'div'> & {
    defaultOpen?: boolean;
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
}) {
    const isMobile = useIsMobile();
    const [openMobile, setOpenMobile] = React.useState(false);
    const [_open, _setOpen] = React.useState(defaultOpen);
    const open = openProp ?? _open;
    const setOpen = React.useCallback(
        (value: boolean | ((current: boolean) => boolean)) => {
            const openState =
                typeof value === 'function' ? value(open) : value;

            if (setOpenProp) {
                setOpenProp(openState);
            } else {
                _setOpen(openState);
            }

            document.cookie = `${SIDEBAR_COOKIE_NAME}=${openState}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;
        },
        [open, setOpenProp],
    );

    const toggleSidebar = React.useCallback(() => {
        return isMobile
            ? setOpenMobile((current) => !current)
            : setOpen((current) => !current);
    }, [isMobile, setOpen]);

    React.useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (
                event.key === SIDEBAR_KEYBOARD_SHORTCUT &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                toggleSidebar();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [toggleSidebar]);

    const state = open ? 'expanded' : 'collapsed';
    const contextValue = React.useMemo<SidebarContextValue>(
        () => ({
            state,
            open,
            setOpen,
            isMobile,
            openMobile,
            setOpenMobile,
            toggleSidebar,
        }),
        [state, open, setOpen, isMobile, openMobile, toggleSidebar],
    );

    return (
        <SidebarContext.Provider value={contextValue}>
            <div
                data-slot="sidebar-wrapper"
                style={
                    {
                        '--sidebar-width': SIDEBAR_WIDTH,
                        '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
                        ...style,
                    } as React.CSSProperties
                }
                className={cn(
                    'group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full',
                    className,
                )}
                {...props}
            >
                {children}
            </div>
        </SidebarContext.Provider>
    );
}

export { SidebarProvider, useSidebar };
