import '@testing-library/jest-dom/vitest';

class ResizeObserverMock implements ResizeObserver {
	observe(): void {}
	unobserve(): void {}
	disconnect(): void {}
}

if (typeof globalThis.ResizeObserver === 'undefined') {
	globalThis.ResizeObserver = ResizeObserverMock as unknown as typeof ResizeObserver;
}
