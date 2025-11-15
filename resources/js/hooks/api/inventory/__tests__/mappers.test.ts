import { describe, expect, it } from 'vitest';

import { mapInventoryItemDetail } from '../mappers';

const basePayload = {
    id: 42,
    sku: 'PART-001',
    name: 'Widget',
    uom: 'EA',
    on_hand: 0,
    stock_by_location: [],
};

describe('mapInventoryItemDetail', () => {
    it('returns attachment download URLs when provided', () => {
        const payload = {
            ...basePayload,
            attachments: [
                {
                    id: 7,
                    filename: 'spec.pdf',
                    size_bytes: 1024,
                    mime: 'application/pdf',
                    download_url: 'https://example.com/spec.pdf',
                },
            ],
        } as Record<string, unknown>;

        const detail = mapInventoryItemDetail(payload);

        expect(detail.attachments).toHaveLength(1);
        expect(detail.attachments[0]).toMatchObject({
            id: 7,
            filename: 'spec.pdf',
            sizeBytes: 1024,
            downloadUrl: 'https://example.com/spec.pdf',
        });
    });
});
