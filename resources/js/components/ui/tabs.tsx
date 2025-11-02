import { cn } from '@/lib/utils';
import {
    createContext,
    type HTMLAttributes,
    type ReactNode,
    useContext,
    useMemo,
    useState,
} from 'react';

interface TabsContextValue {
    value: string;
    setValue: (value: string) => void;
}

const TabsContext = createContext<TabsContextValue | null>(null);

interface TabsProps {
    defaultValue: string;
    value?: string;
    onValueChange?: (value: string) => void;
    children: ReactNode;
    className?: string;
}

function Tabs({
    defaultValue,
    value: controlledValue,
    onValueChange,
    children,
    className,
}: TabsProps) {
    const [uncontrolledValue, setUncontrolledValue] = useState(defaultValue);
    const isControlled = controlledValue !== undefined;
    const value = isControlled ? (controlledValue as string) : uncontrolledValue;

    const setValue = (next: string) => {
        if (!isControlled) {
            setUncontrolledValue(next);
        }
        onValueChange?.(next);
    };

    const contextValue = useMemo<TabsContextValue>(
        () => ({ value, setValue }),
        [value],
    );

    return (
        <TabsContext.Provider value={contextValue}>
            <div className={cn('flex flex-col gap-4', className)}>{children}</div>
        </TabsContext.Provider>
    );
}

const TabsList = ({ className, ...props }: HTMLAttributes<HTMLDivElement>) => (
    <div
        className={cn(
            'inline-flex items-center justify-start gap-2 rounded-lg border border-muted bg-muted/40 p-1 text-muted-foreground',
            className,
        )}
        role="tablist"
        {...props}
    />
);

interface TabsTriggerProps
    extends Omit<HTMLAttributes<HTMLButtonElement>, 'onClick'> {
    value: string;
}

const TabsTrigger = ({ value, className, children, ...props }: TabsTriggerProps) => {
    const context = useTabsContext();
    const isActive = context.value === value;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={isActive}
            data-state={isActive ? 'active' : 'inactive'}
            className={cn(
                'inline-flex min-w-[140px] items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-sm',
                className,
            )}
            onClick={() => context.setValue(value)}
            {...props}
        >
            {children}
        </button>
    );
};

interface TabsContentProps extends HTMLAttributes<HTMLDivElement> {
    value: string;
}

const TabsContent = ({ value, className, children, ...props }: TabsContentProps) => {
    const context = useTabsContext();
    const isActive = context.value === value;

    if (!isActive) {
        return null;
    }

    return (
        <div
            role="tabpanel"
            className={cn(
                'rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/40',
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
};

function useTabsContext() {
    const context = useContext(TabsContext);
    if (!context) {
        throw new Error('Tabs components must be used within <Tabs>.');
    }
    return context;
}

export { Tabs, TabsContent, TabsList, TabsTrigger };
