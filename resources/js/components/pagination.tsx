import { Button } from '@/components/ui/button';

interface PaginationMeta {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
}

interface PaginationProps {
    meta: PaginationMeta | null;
    onPageChange: (page: number) => void;
    isLoading?: boolean;
}

export function Pagination({
    meta,
    onPageChange,
    isLoading = false,
}: PaginationProps) {
    if (!meta || meta.last_page <= 1) {
        return null;
    }

    const { current_page: currentPage, last_page: lastPage } = meta;

    const handlePrevious = () => {
        if (currentPage > 1) {
            onPageChange(currentPage - 1);
        }
    };

    const handleNext = () => {
        if (currentPage < lastPage) {
            onPageChange(currentPage + 1);
        }
    };

    return (
        <div className="flex items-center justify-between rounded-lg border border-sidebar-border/60 bg-background/60 px-3 py-2 text-sm">
            <span className="text-muted-foreground">
                Page {currentPage} of {lastPage}
            </span>
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handlePrevious}
                    disabled={isLoading || currentPage === 1}
                >
                    Previous
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleNext}
                    disabled={isLoading || currentPage === lastPage}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}
