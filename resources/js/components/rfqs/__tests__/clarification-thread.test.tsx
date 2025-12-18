import { render, fireEvent, screen } from '@testing-library/react';
import { describe, it, vi, expect } from 'vitest';

import { ClarificationThread } from '../clarification-thread';

const noop = vi.fn();

describe('ClarificationThread', () => {
    function renderThread() {
        return render(
            <ClarificationThread
                clarifications={[]}
                onAskQuestion={async () => {}}
                onAnswerQuestion={async () => {}}
            />,
        );
    }

    it('displays question attachments after file selection', () => {
        const { container } = renderThread();
        const fileInput = container.querySelector('input[type="file"]');
        if (!fileInput) {
            throw new Error('file input not found');
        }

        const file = new File(['hello'], 'spec.pdf', { type: 'application/pdf' });
        fireEvent.change(fileInput, { target: { files: [file] } });

        expect(screen.getByText(/spec.pdf/i)).toBeInTheDocument();
    });
});
