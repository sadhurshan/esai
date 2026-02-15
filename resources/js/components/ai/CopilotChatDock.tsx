import { XIcon } from 'lucide-react';
import { useEffect } from 'react';

import { CopilotChatPanel } from '@/components/ai/CopilotChatPanel';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCopilotWidget } from '@/contexts/copilot-widget-context';
import { cn } from '@/lib/utils';

export function CopilotChatDock() {
    const { isOpen, open, close } = useCopilotWidget();

    useEffect(() => {
        if (!isOpen || typeof document === 'undefined') {
            return;
        }

        const { body } = document;
        const previousOverflow = body.style.overflow;
        body.style.overflow = 'hidden';

        return () => {
            body.style.overflow = previousOverflow;
        };
    }, [isOpen]);

    return (
        <Sheet open={isOpen} onOpenChange={(next) => (next ? open() : close())}>
            <SheetContent
                side="right"
                className={cn(
                    'h-[100dvh] max-h-[100dvh] w-full max-w-full overflow-hidden border-l bg-background/95 p-0 text-left shadow-2xl backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:w-[480px] sm:max-w-lg lg:w-[520px]',
                    '[&_[data-slot=sheet-close]]:hidden',
                )}
            >
                <div className="flex h-full flex-col">
                    <SheetHeader className="flex flex-row items-center justify-between border-b px-4 py-3 sm:px-6">
                        <div>
                            <SheetTitle className="text-lg font-semibold">
                                AI Copilot
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                Chat with Copilot to draft, review, and guide procurement workflows.
                            </SheetDescription>
                        </div>
                        <SheetClose asChild>
                            <button
                                type="button"
                                className="inline-flex items-center rounded-full p-2 text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                aria-label="Close Copilot"
                            >
                                <XIcon className="size-4" />
                            </button>
                        </SheetClose>
                    </SheetHeader>
                    <div className="flex-1 overflow-hidden">
                        <div className="h-full overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
                            <CopilotChatPanel className="h-full" />
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
