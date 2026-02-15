# Elements Supply TypeScript SDK

This package is generated from the canonical OpenAPI definition (`php artisan api:spec:build`). Custom helpers in `client.ts` wrap the generated fetch bindings with:

- Base URL configuration and default headers
- Automatic bearer/API key injection
- Exponential back-off for 429 responses (`TooManyRequestsError`)
- Typed `HttpError` instances containing the parsed response body

## Quick Start

```ts
import {
    createConfiguration,
    PurchaseOrdersApi,
    TooManyRequestsError,
} from './ts-client';

const config = createConfiguration({
    baseUrl: process.env.API_BASE_URL,
    bearerToken: async () =>
        sessionStorage.getItem('access_token') ?? undefined,
    apiKey: () => process.env.PUBLIC_API_KEY,
});

const purchaseOrders = new PurchaseOrdersApi(config);

try {
    const result = await purchaseOrders.apiPurchaseOrdersGet();
    console.log(result.data.items);
} catch (error) {
    if (error instanceof TooManyRequestsError) {
        console.warn(`Retry after ${error.retryAfterMs}ms`);
    }
    throw error;
}
```

Regenerate the SDK with:

```bash
php artisan api:spec:build
php artisan api:sdk:typescript
```

The command updates `resources/sdk/ts-client/generated/*` while preserving the hand-written helpers.
