# PurchaseOrdersApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                                                             | HTTP request                                                                   | Description                                          |
| -------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | ---------------------------------------------------- |
| [**acknowledgePurchaseOrder**](PurchaseOrdersApi.md#acknowledgepurchaseorderoperation)             | **POST** /api/purchase-orders/{purchaseOrderId}/acknowledge                    | Supplier acknowledges purchase order                 |
| [**approvePurchaseOrderChangeOrder**](PurchaseOrdersApi.md#approvepurchaseorderchangeorder)        | **PUT** /api/change-orders/{changeOrderId}/approve                             | Approve change order                                 |
| [**cancelPurchaseOrder**](PurchaseOrdersApi.md#cancelpurchaseorder)                                | **POST** /api/purchase-orders/{purchaseOrderId}/cancel                         | Cancel purchase order                                |
| [**createPurchaseOrderChangeOrder**](PurchaseOrdersApi.md#createpurchaseorderchangeorderoperation) | **POST** /api/purchase-orders/{purchaseOrderId}/change-orders                  | Propose change order                                 |
| [**createPurchaseOrdersFromAwards**](PurchaseOrdersApi.md#createpurchaseordersfromawardsoperation) | **POST** /api/pos/from-awards                                                  | Convert awarded RFQ lines into draft purchase orders |
| [**downloadPurchaseOrderDocument**](PurchaseOrdersApi.md#downloadpurchaseorderdocument)            | **GET** /api/purchase-orders/{purchaseOrderId}/documents/{documentId}/download | Download purchase order PDF                          |
| [**exportPurchaseOrder**](PurchaseOrdersApi.md#exportpurchaseorder)                                | **POST** /api/purchase-orders/{purchaseOrderId}/export                         | Generate PDF for purchase order                      |
| [**listPurchaseOrderChangeOrders**](PurchaseOrdersApi.md#listpurchaseorderchangeorders)            | **GET** /api/purchase-orders/{purchaseOrderId}/change-orders                   | List change orders for purchase order                |
| [**listPurchaseOrderEvents**](PurchaseOrdersApi.md#listpurchaseorderevents)                        | **GET** /api/purchase-orders/{purchaseOrderId}/events                          | List purchase order timeline events                  |
| [**listPurchaseOrderShipments**](PurchaseOrdersApi.md#listpurchaseordershipments)                  | **GET** /api/purchase-orders/{purchaseOrderId}/shipments                       | List shipments linked to purchase order              |
| [**listPurchaseOrders**](PurchaseOrdersApi.md#listpurchaseorders)                                  | **GET** /api/purchase-orders                                                   | List purchase orders                                 |
| [**rejectPurchaseOrderChangeOrder**](PurchaseOrdersApi.md#rejectpurchaseorderchangeorder)          | **PUT** /api/change-orders/{changeOrderId}/reject                              | Reject change order                                  |
| [**sendPurchaseOrder**](PurchaseOrdersApi.md#sendpurchaseorderoperation)                           | **POST** /api/purchase-orders/{purchaseOrderId}/send                           | Issue purchase order to supplier                     |
| [**showPurchaseOrder**](PurchaseOrdersApi.md#showpurchaseorder)                                    | **GET** /api/purchase-orders/{purchaseOrderId}                                 | Retrieve purchase order                              |

## acknowledgePurchaseOrder

> ShowPurchaseOrder200Response acknowledgePurchaseOrder(purchaseOrderId, acknowledgePurchaseOrderRequest)

Supplier acknowledges purchase order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { AcknowledgePurchaseOrderOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new PurchaseOrdersApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // AcknowledgePurchaseOrderRequest
    acknowledgePurchaseOrderRequest: ...,
  } satisfies AcknowledgePurchaseOrderOperationRequest;

  try {
    const data = await api.acknowledgePurchaseOrder(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                                | Type                                                                  | Description | Notes                     |
| ----------------------------------- | --------------------------------------------------------------------- | ----------- | ------------------------- |
| **purchaseOrderId**                 | `number`                                                              |             | [Defaults to `undefined`] |
| **acknowledgePurchaseOrderRequest** | [AcknowledgePurchaseOrderRequest](AcknowledgePurchaseOrderRequest.md) |             |                           |

### Return type

[**ShowPurchaseOrder200Response**](ShowPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                              | Response headers |
| ----------- | ---------------------------------------- | ---------------- |
| **200**     | Purchase order acknowledgement recorded. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## approvePurchaseOrderChangeOrder

> ApiSuccessResponse approvePurchaseOrderChangeOrder(changeOrderId)

Approve change order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ApprovePurchaseOrderChangeOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        changeOrderId: 56,
    } satisfies ApprovePurchaseOrderChangeOrderRequest;

    try {
        const data = await api.approvePurchaseOrderChangeOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name              | Type     | Description | Notes                     |
| ----------------- | -------- | ----------- | ------------------------- |
| **changeOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                        | Response headers |
| ----------- | ---------------------------------- | ---------------- |
| **200**     | Change order approved and applied. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## cancelPurchaseOrder

> ApiSuccessResponse cancelPurchaseOrder(purchaseOrderId)

Cancel purchase order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { CancelPurchaseOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies CancelPurchaseOrderRequest;

    try {
        const data = await api.cancelPurchaseOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                | Response headers       |
| ----------- | -------------------------- | ---------------------- |
| **200**     | Purchase order cancelled.  | -                      |
| **422**     | Payload validation failed. | \* X-Request-Id - <br> |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createPurchaseOrderChangeOrder

> ApiSuccessResponse createPurchaseOrderChangeOrder(purchaseOrderId, createPurchaseOrderChangeOrderRequest)

Propose change order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { CreatePurchaseOrderChangeOrderOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new PurchaseOrdersApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // CreatePurchaseOrderChangeOrderRequest
    createPurchaseOrderChangeOrderRequest: ...,
  } satisfies CreatePurchaseOrderChangeOrderOperationRequest;

  try {
    const data = await api.createPurchaseOrderChangeOrder(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                                      | Type                                                                              | Description | Notes                     |
| ----------------------------------------- | --------------------------------------------------------------------------------- | ----------- | ------------------------- |
| **purchaseOrderId**                       | `number`                                                                          |             | [Defaults to `undefined`] |
| **createPurchaseOrderChangeOrderRequest** | [CreatePurchaseOrderChangeOrderRequest](CreatePurchaseOrderChangeOrderRequest.md) |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description            | Response headers |
| ----------- | ---------------------- | ---------------- |
| **201**     | Change order proposed. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createPurchaseOrdersFromAwards

> CreatePurchaseOrdersFromAwards201Response createPurchaseOrdersFromAwards(createPurchaseOrdersFromAwardsRequest)

Convert awarded RFQ lines into draft purchase orders

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { CreatePurchaseOrdersFromAwardsOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new PurchaseOrdersApi(config);

  const body = {
    // CreatePurchaseOrdersFromAwardsRequest
    createPurchaseOrdersFromAwardsRequest: ...,
  } satisfies CreatePurchaseOrdersFromAwardsOperationRequest;

  try {
    const data = await api.createPurchaseOrdersFromAwards(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                                      | Type                                                                              | Description | Notes |
| ----------------------------------------- | --------------------------------------------------------------------------------- | ----------- | ----- |
| **createPurchaseOrdersFromAwardsRequest** | [CreatePurchaseOrdersFromAwardsRequest](CreatePurchaseOrdersFromAwardsRequest.md) |             |       |

### Return type

[**CreatePurchaseOrdersFromAwards201Response**](CreatePurchaseOrdersFromAwards201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                                       | Response headers       |
| ----------- | ------------------------------------------------- | ---------------------- |
| **201**     | Purchase orders drafted from the provided awards. | -                      |
| **401**     | Missing or invalid credentials.                   | \* X-Request-Id - <br> |
| **402**     | Plan upgrade required to create purchase orders.  | -                      |
| **403**     | Authenticated but lacking required permissions.   | \* X-Request-Id - <br> |
| **422**     | Payload validation failed.                        | \* X-Request-Id - <br> |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## downloadPurchaseOrderDocument

> Blob downloadPurchaseOrderDocument(purchaseOrderId, documentId)

Download purchase order PDF

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { DownloadPurchaseOrderDocumentRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
        // number
        documentId: 56,
    } satisfies DownloadPurchaseOrderDocumentRequest;

    try {
        const data = await api.downloadPurchaseOrderDocument(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |
| **documentId**      | `number` |             | [Defaults to `undefined`] |

### Return type

**Blob**

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/pdf`, `application/json`

### HTTP response details

| Status code | Description                            | Response headers |
| ----------- | -------------------------------------- | ---------------- |
| **200**     | Purchase order PDF stream.             | -                |
| **404**     | Document not found for purchase order. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## exportPurchaseOrder

> ExportPurchaseOrder200Response exportPurchaseOrder(purchaseOrderId)

Generate PDF for purchase order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ExportPurchaseOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies ExportPurchaseOrderRequest;

    try {
        const data = await api.exportPurchaseOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ExportPurchaseOrder200Response**](ExportPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                            | Response headers |
| ----------- | -------------------------------------- | ---------------- |
| **200**     | Purchase order PDF ready for download. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listPurchaseOrderChangeOrders

> ListPurchaseOrderChangeOrders200Response listPurchaseOrderChangeOrders(purchaseOrderId)

List change orders for purchase order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ListPurchaseOrderChangeOrdersRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies ListPurchaseOrderChangeOrdersRequest;

    try {
        const data = await api.listPurchaseOrderChangeOrders(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ListPurchaseOrderChangeOrders200Response**](ListPurchaseOrderChangeOrders200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                       | Response headers |
| ----------- | --------------------------------- | ---------------- |
| **200**     | Current change orders for the PO. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listPurchaseOrderEvents

> ListPurchaseOrderEvents200Response listPurchaseOrderEvents(purchaseOrderId)

List purchase order timeline events

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ListPurchaseOrderEventsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies ListPurchaseOrderEventsRequest;

    try {
        const data = await api.listPurchaseOrderEvents(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ListPurchaseOrderEvents200Response**](ListPurchaseOrderEvents200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                              | Response headers |
| ----------- | ---------------------------------------- | ---------------- |
| **200**     | Timeline entries for the purchase order. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listPurchaseOrderShipments

> ApiSuccessResponse listPurchaseOrderShipments(purchaseOrderId)

List shipments linked to purchase order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ListPurchaseOrderShipmentsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies ListPurchaseOrderShipmentsRequest;

    try {
        const data = await api.listPurchaseOrderShipments(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                               | Response headers |
| ----------- | ----------------------------------------- | ---------------- |
| **200**     | Shipment timeline for the purchase order. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listPurchaseOrders

> ListPurchaseOrders200Response listPurchaseOrders(perPage, page, supplier, status)

List purchase orders

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { ListPurchaseOrdersRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new PurchaseOrdersApi(config);

  const body = {
    // number (optional)
    perPage: 56,
    // number (optional)
    page: 56,
    // boolean (optional)
    supplier: true,
    // ListPurchaseOrdersStatusParameter (optional)
    status: ...,
  } satisfies ListPurchaseOrdersRequest;

  try {
    const data = await api.listPurchaseOrders(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name         | Type      | Description | Notes                                |
| ------------ | --------- | ----------- | ------------------------------------ |
| **perPage**  | `number`  |             | [Optional] [Defaults to `undefined`] |
| **page**     | `number`  |             | [Optional] [Defaults to `undefined`] |
| **supplier** | `boolean` |             | [Optional] [Defaults to `undefined`] |
| **status**   | [](.md)   |             | [Optional] [Defaults to `undefined`] |

### Return type

[**ListPurchaseOrders200Response**](ListPurchaseOrders200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                        | Response headers |
| ----------- | ---------------------------------- | ---------------- |
| **200**     | Paginated list of purchase orders. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## rejectPurchaseOrderChangeOrder

> ApiSuccessResponse rejectPurchaseOrderChangeOrder(changeOrderId)

Reject change order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { RejectPurchaseOrderChangeOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        changeOrderId: 56,
    } satisfies RejectPurchaseOrderChangeOrderRequest;

    try {
        const data = await api.rejectPurchaseOrderChangeOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name              | Type     | Description | Notes                     |
| ----------------- | -------- | ----------- | ------------------------- |
| **changeOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description            | Response headers |
| ----------- | ---------------------- | ---------------- |
| **200**     | Change order rejected. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## sendPurchaseOrder

> SendPurchaseOrder200Response sendPurchaseOrder(purchaseOrderId, sendPurchaseOrderRequest)

Issue purchase order to supplier

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { SendPurchaseOrderOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new PurchaseOrdersApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // SendPurchaseOrderRequest
    sendPurchaseOrderRequest: ...,
  } satisfies SendPurchaseOrderOperationRequest;

  try {
    const data = await api.sendPurchaseOrder(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                         | Type                                                    | Description | Notes                     |
| ---------------------------- | ------------------------------------------------------- | ----------- | ------------------------- |
| **purchaseOrderId**          | `number`                                                |             | [Defaults to `undefined`] |
| **sendPurchaseOrderRequest** | [SendPurchaseOrderRequest](SendPurchaseOrderRequest.md) |             |                           |

### Return type

[**SendPurchaseOrder200Response**](SendPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                | Response headers       |
| ----------- | -------------------------- | ---------------------- |
| **200**     | Purchase order issued.     | -                      |
| **422**     | Payload validation failed. | \* X-Request-Id - <br> |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showPurchaseOrder

> ShowPurchaseOrder200Response showPurchaseOrder(purchaseOrderId)

Retrieve purchase order

### Example

```ts
import { Configuration, PurchaseOrdersApi } from '';
import type { ShowPurchaseOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new PurchaseOrdersApi(config);

    const body = {
        // number
        purchaseOrderId: 56,
    } satisfies ShowPurchaseOrderRequest;

    try {
        const data = await api.showPurchaseOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ShowPurchaseOrder200Response**](ShowPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                 | Response headers |
| ----------- | --------------------------- | ---------------- |
| **200**     | Purchase order detail view. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
