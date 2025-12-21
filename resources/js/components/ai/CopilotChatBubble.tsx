import type { KeyboardEvent } from 'react';
import { MessageCircle } from 'lucide-react';

import { useCopilotWidget } from '@/contexts/copilot-widget-context';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface CopilotChatBubbleProps {
    showIndicator?: boolean;
    className?: string;
}

export function CopilotChatBubble({ showIndicator = false, className }: CopilotChatBubbleProps) {
    const { toggle, isOpen } = useCopilotWidget();

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
                    aria-expanded={isOpen}
                    aria-haspopup="dialog"
                    data-state={isOpen ? 'open' : 'closed'}
                    className={cn(
                        'fixed bottom-6 right-6 z-[30] flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-2xl transition hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
                        'fixed border border-primary/10 backdrop-blur',
                        className,
                    )}
                    onClick={toggle}
                    onKeyDown={handleKeyDown}
                >
                    <MessageCircle className="size-6" />
                    {showIndicator && !isOpen ? (
                        <span
                            aria-hidden="true"
                            className="absolute -top-0.5 -right-0.5 h-3 w-3 rounded-full bg-destructive shadow-lg"
                        />
                    ) : null}
                </button>
            </TooltipTrigger>
            <TooltipContent side="left">AI Copilot</TooltipContent>
        </Tooltip>
    );
}
