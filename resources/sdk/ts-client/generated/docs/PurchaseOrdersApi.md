# PurchaseOrdersApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**acknowledgePurchaseOrder**](PurchaseOrdersApi.md#acknowledgepurchaseorderoperation) | **POST** /api/purchase-orders/{purchaseOrderId}/acknowledge | Supplier acknowledges purchase order |
| [**approvePurchaseOrderChangeOrder**](PurchaseOrdersApi.md#approvepurchaseorderchangeorder) | **PUT** /api/change-orders/{changeOrderId}/approve | Approve change order |
| [**createPurchaseOrderChangeOrder**](PurchaseOrdersApi.md#createpurchaseorderchangeorderoperation) | **POST** /api/purchase-orders/{purchaseOrderId}/change-orders | Propose change order |
| [**listPurchaseOrderChangeOrders**](PurchaseOrdersApi.md#listpurchaseorderchangeorders) | **GET** /api/purchase-orders/{purchaseOrderId}/change-orders | List change orders for purchase order |
| [**listPurchaseOrders**](PurchaseOrdersApi.md#listpurchaseorders) | **GET** /api/purchase-orders | List purchase orders |
| [**rejectPurchaseOrderChangeOrder**](PurchaseOrdersApi.md#rejectpurchaseorderchangeorder) | **PUT** /api/change-orders/{changeOrderId}/reject | Reject change order |
| [**sendPurchaseOrder**](PurchaseOrdersApi.md#sendpurchaseorder) | **POST** /api/purchase-orders/{purchaseOrderId}/send | Issue purchase order to supplier |
| [**showPurchaseOrder**](PurchaseOrdersApi.md#showpurchaseorder) | **GET** /api/purchase-orders/{purchaseOrderId} | Retrieve purchase order |



## acknowledgePurchaseOrder

> ApiSuccessResponse acknowledgePurchaseOrder(purchaseOrderId, acknowledgePurchaseOrderRequest)

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
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **purchaseOrderId** | `string` |  | [Defaults to `undefined`] |
| **acknowledgePurchaseOrderRequest** | [AcknowledgePurchaseOrderRequest](AcknowledgePurchaseOrderRequest.md) |  | |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Purchase order acknowledgement recorded. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## approvePurchaseOrderChangeOrder

> ApiSuccessResponse approvePurchaseOrderChangeOrder(changeOrderId)

Approve change order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { ApprovePurchaseOrderChangeOrderRequest } from '';

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
    // string
    changeOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **changeOrderId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Change order approved and applied. |  -  |

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
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **purchaseOrderId** | `string` |  | [Defaults to `undefined`] |
| **createPurchaseOrderChangeOrderRequest** | [CreatePurchaseOrderChangeOrderRequest](CreatePurchaseOrderChangeOrderRequest.md) |  | |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Change order proposed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listPurchaseOrderChangeOrders

> ListPurchaseOrderChangeOrders200Response listPurchaseOrderChangeOrders(purchaseOrderId)

List change orders for purchase order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { ListPurchaseOrderChangeOrdersRequest } from '';

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
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **purchaseOrderId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ListPurchaseOrderChangeOrders200Response**](ListPurchaseOrderChangeOrders200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current change orders for the PO. |  -  |

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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **supplier** | `boolean` |  | [Optional] [Defaults to `undefined`] |
| **status** | [](.md) |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListPurchaseOrders200Response**](ListPurchaseOrders200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated list of purchase orders. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## rejectPurchaseOrderChangeOrder

> ApiSuccessResponse rejectPurchaseOrderChangeOrder(changeOrderId)

Reject change order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { RejectPurchaseOrderChangeOrderRequest } from '';

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
    // string
    changeOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **changeOrderId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Change order rejected. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## sendPurchaseOrder

> ApiSuccessResponse sendPurchaseOrder(purchaseOrderId)

Issue purchase order to supplier

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { SendPurchaseOrderRequest } from '';

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
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies SendPurchaseOrderRequest;

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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **purchaseOrderId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Purchase order issued. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showPurchaseOrder

> ShowPurchaseOrder200Response showPurchaseOrder(purchaseOrderId)

Retrieve purchase order

### Example

```ts
import {
  Configuration,
  PurchaseOrdersApi,
} from '';
import type { ShowPurchaseOrderRequest } from '';

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
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
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


| Name | Type | Description  | Notes |
|------------- | ------------- | ------------- | -------------|
| **purchaseOrderId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ShowPurchaseOrder200Response**](ShowPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Purchase order detail view. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

