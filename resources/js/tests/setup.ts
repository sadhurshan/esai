import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';

vi.mock('@/components/documents/document-number-preview', () => ({
	DocumentNumberPreview: () => null,
}));

class ResizeObserverMock implements ResizeObserver {
	observe(): void {}
	unobserve(): void {}
	disconnect(): void {}
}

if (typeof globalThis.ResizeObserver === 'undefined') {
	globalThis.ResizeObserver = ResizeObserverMock as unknown as typeof ResizeObserver;
}
