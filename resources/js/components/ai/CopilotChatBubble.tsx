import { Bot } from 'lucide-react';
import type { KeyboardEvent } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCopilotWidget } from '@/contexts/copilot-widget-context';
import { cn } from '@/lib/utils';

interface CopilotChatBubbleProps {
    showIndicator?: boolean;
    className?: string;
}

export function CopilotChatBubble({
    showIndicator = false,
    className,
}: CopilotChatBubbleProps) {
    const { toggle, isOpen, errorCount, toolErrorCount, draftRejectCount } =
        useCopilotWidget();
    const hasErrors = errorCount > 0;
    const errorLabel = hasErrors
        ? `Tool failures: ${toolErrorCount}, Draft rejections: ${draftRejectCount}`
        : 'No recent Copilot errors';

    const handleKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
        if (event.key === ' ') {
            event.preventDefault();
            toggle();
        }
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    aria-label="AI Copilot"
                    aria-describedby={
                        hasErrors ? 'copilot-error-counts' : undefined
                    }
                    aria-expanded={isOpen}
                    aria-haspopup="dialog"
                    data-state={isOpen ? 'open' : 'closed'}
                    className={cn(
                        'fixed right-6 bottom-6 z-[30] flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-2xl transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none',
                        'fixed border border-primary/10 backdrop-blur',
                        className,
                    )}
                    onClick={toggle}
                    onKeyDown={handleKeyDown}
                >
                    <Bot className="size-6" />
                    {errorCount > 0 && !isOpen ? (
                        <span className="absolute -top-0.5 -right-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-semibold text-white shadow-lg">
                            {Math.min(errorCount, 99)}
                        </span>
                    ) : null}
                    {showIndicator && errorCount === 0 && !isOpen ? (
                        <span
                            aria-hidden="true"
                            className="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-destructive shadow-lg"
                        />
                    ) : null}
                </button>
            </TooltipTrigger>
            <TooltipContent side="left">
                <div className="space-y-1 text-xs">
                    <p className="font-semibold">AI Copilot</p>
                    <p
                        id="copilot-error-counts"
                        className="text-muted-foreground"
                    >
                        {errorLabel}
                    </p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}
