import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
    type ReactNode,
} from 'react';

const STORAGE_KEY = 'esai.copilotWidget.open.v1';

interface StoredWidgetState {
    isOpen: boolean;
    lastActionType?: string | null;
    activeTab?: string | null;
}

interface CopilotWidgetContextValue {
    isOpen: boolean;
    lastActionType: string | null;
    activeTab: string | null;
    errorCount: number;
    toolErrorCount: number;
    draftRejectCount: number;
    open: () => void;
    close: () => void;
    toggle: () => void;
    setLastActionType: (value: string | null) => void;
    setActiveTab: (value: string | null) => void;
    incrementToolErrors: () => void;
    incrementDraftRejects: () => void;
    resetErrors: () => void;
}

const CopilotWidgetContext = createContext<
    CopilotWidgetContextValue | undefined
>(undefined);

const DEFAULT_STATE: StoredWidgetState = {
    isOpen: false,
    lastActionType: null,
    activeTab: null,
};

function readStoredState(): StoredWidgetState {
    if (typeof window === 'undefined') {
        return DEFAULT_STATE;
    }

    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return DEFAULT_STATE;
        }

        const parsed = JSON.parse(raw) as StoredWidgetState;
        return {
            isOpen: Boolean(parsed.isOpen),
            lastActionType: parsed.lastActionType ?? null,
            activeTab: parsed.activeTab ?? null,
        };
    } catch (error) {
        console.error('Failed to parse Copilot widget state', error);
        return DEFAULT_STATE;
    }
}

function writeStoredState(state: StoredWidgetState) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (error) {
        console.error('Failed to persist Copilot widget state', error);
    }
}

interface CopilotWidgetProviderProps {
    children: ReactNode;
}

export function CopilotWidgetProvider({
    children,
}: CopilotWidgetProviderProps) {
    const [isOpen, setIsOpen] = useState<boolean>(
        () => readStoredState().isOpen,
    );
    const [lastActionType, setLastActionType] = useState<string | null>(
        () => readStoredState().lastActionType ?? null,
    );
    const [activeTab, setActiveTab] = useState<string | null>(
        () => readStoredState().activeTab ?? null,
    );
    const [toolErrorCount, setToolErrorCount] = useState(0);
    const [draftRejectCount, setDraftRejectCount] = useState(0);

    const errorCount = toolErrorCount + draftRejectCount;

    useEffect(() => {
        writeStoredState({ isOpen, lastActionType, activeTab });
    }, [isOpen, lastActionType, activeTab]);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleStorage = (event: StorageEvent) => {
            if (
                event.key !== STORAGE_KEY ||
                event.newValue === event.oldValue ||
                !event.newValue
            ) {
                return;
            }

            try {
                const payload = JSON.parse(event.newValue) as StoredWidgetState;
                setIsOpen(Boolean(payload.isOpen));
                setLastActionType(payload.lastActionType ?? null);
                setActiveTab(payload.activeTab ?? null);
            } catch (error) {
                console.error('Failed to sync Copilot widget state', error);
            }
        };

        window.addEventListener('storage', handleStorage);

        return () => {
            window.removeEventListener('storage', handleStorage);
        };
    }, []);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const handleToggleShortcut = (event: KeyboardEvent) => {
            if (event.defaultPrevented) {
                return;
            }

            const isModifier = event.metaKey || event.ctrlKey;
            if (!isModifier || event.key.toLowerCase() !== 'k') {
                return;
            }

            const target = event.target as HTMLElement | null;
            if (target) {
                const tagName = target.tagName;
                const isInputField =
                    tagName === 'INPUT' ||
                    tagName === 'TEXTAREA' ||
                    tagName === 'SELECT' ||
                    target.isContentEditable;

                if (isInputField) {
                    return;
                }
            }

            event.preventDefault();
            setIsOpen((prev) => !prev);
        };

        window.addEventListener('keydown', handleToggleShortcut, {
            passive: false,
        });

        return () => {
            window.removeEventListener('keydown', handleToggleShortcut);
        };
    }, []);

    const open = useCallback(() => setIsOpen(true), []);
    const close = useCallback(() => setIsOpen(false), []);
    const toggle = useCallback(() => setIsOpen((prev) => !prev), []);
    const incrementToolErrors = useCallback(
        () => setToolErrorCount((value) => value + 1),
        [],
    );
    const incrementDraftRejects = useCallback(
        () => setDraftRejectCount((value) => value + 1),
        [],
    );
    const resetErrors = useCallback(() => {
        setToolErrorCount(0);
        setDraftRejectCount(0);
    }, []);

    const value = useMemo<CopilotWidgetContextValue>(() => {
        return {
            isOpen,
            lastActionType,
            activeTab,
            errorCount,
            toolErrorCount,
            draftRejectCount,
            open,
            close,
            toggle,
            setLastActionType,
            setActiveTab,
            incrementToolErrors,
            incrementDraftRejects,
            resetErrors,
        };
    }, [
        isOpen,
        lastActionType,
        activeTab,
        errorCount,
        toolErrorCount,
        draftRejectCount,
        open,
        close,
        toggle,
        incrementToolErrors,
        incrementDraftRejects,
        resetErrors,
    ]);

    return (
        <CopilotWidgetContext.Provider value={value}>
            {children}
        </CopilotWidgetContext.Provider>
    );
}

export function useCopilotWidget(): CopilotWidgetContextValue {
    const context = useContext(CopilotWidgetContext);

    if (!context) {
        throw new Error(
            'useCopilotWidget must be used within a CopilotWidgetProvider',
        );
    }

    return context;
}
