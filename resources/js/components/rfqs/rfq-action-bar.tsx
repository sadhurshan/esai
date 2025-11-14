import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import type { Rfq } from '@/sdk';
import { ChevronDown, FileDown, FileText, PenLine, UploadCloud } from 'lucide-react';
import { Fragment } from 'react';
import { RfqStatusBadge } from './rfq-status-badge';

export interface RfqActionBarProps {
    rfq: Rfq;
    onEdit?: () => void;
    onInviteSuppliers?: () => void;
    onPublish?: () => void;
    onAmend?: () => void;
    onClose?: () => void;
    className?: string;
}

export function RfqActionBar({
    rfq,
    onEdit,
    onInviteSuppliers,
    onPublish,
    onAmend,
    onClose,
    className,
}: RfqActionBarProps) {
    const isDraft = rfq.status === 'awaiting';
    const isOpen = rfq.status === 'open';
    const isClosed = rfq.status === 'closed' || rfq.status === 'cancelled';
    const isAwarded = rfq.status === 'awarded';

    return (
        <div className={cn('flex flex-col gap-4 rounded-lg border bg-card px-4 py-3 shadow-sm', className)}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="space-y-1">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <span>RFQ #{rfq.number ?? rfq.id}</span>
                        <Separator orientation="vertical" className="h-4" />
                        <RfqStatusBadge status={rfq.status} />
                    </div>
                    <h2 className="text-xl font-semibold text-foreground">{rfq.itemName}</h2>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {isDraft ? (
                        <Button onClick={onPublish} disabled={!onPublish}>
                            <UploadCloud className="mr-2 h-4 w-4" /> Publish
                        </Button>
                    ) : null}

                    {isOpen ? (
                        <Fragment>
                            <Button variant="secondary" onClick={onAmend} disabled={!onAmend}>
                                <PenLine className="mr-2 h-4 w-4" /> Amend
                            </Button>
                            <Button variant="destructive" onClick={onClose} disabled={!onClose}>
                                Close RFQ
                            </Button>
                        </Fragment>
                    ) : null}

                    <Button variant="outline" onClick={onEdit} disabled={!onEdit}>
                        <PenLine className="mr-2 h-4 w-4" /> Edit details
                    </Button>

                    <Button variant="outline" onClick={onInviteSuppliers} disabled={!onInviteSuppliers || isClosed}>
                        <FileText className="mr-2 h-4 w-4" /> Invite suppliers
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm" className="gap-1">
                                <ChevronDown className="h-4 w-4" /> More
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-48">
                            <DropdownMenuItem disabled>
                                <FileDown className="mr-2 h-4 w-4" /> Export PDF
                            </DropdownMenuItem>
                            <DropdownMenuItem disabled={isAwarded || isClosed}>
                                {/* TODO: wire up award flow once quote comparison lands. */}
                                <FileText className="mr-2 h-4 w-4" /> Award from RFQ
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
                <span>
                    Method <strong className="ml-1 font-medium text-foreground">{rfq.method}</strong>
                </span>
                <span>
                    Material <strong className="ml-1 font-medium text-foreground">{rfq.material}</strong>
                </span>
                <span>
                    Quantity <strong className="ml-1 font-medium text-foreground">{rfq.quantity}</strong>
                </span>
                {rfq.deadlineAt ? (
                    <span>
                        Due date{' '}
                        <strong className="ml-1 font-medium text-foreground">
                            {rfq.deadlineAt instanceof Date ? rfq.deadlineAt.toLocaleDateString() : String(rfq.deadlineAt)}
                        </strong>
                    </span>
                ) : null}
            </div>
        </div>
    );
}
