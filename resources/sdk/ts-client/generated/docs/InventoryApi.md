# InventoryApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**createGrn**](InventoryApi.md#creategrnoperation) | **POST** /api/purchase-orders/{purchaseOrderId}/grns | Create goods receipt note |
| [**createRma**](InventoryApi.md#creatermaoperation) | **POST** /api/rmas/purchase-orders/{purchaseOrderId} | Create RMA for purchase order |
| [**deleteGrn**](InventoryApi.md#deletegrn) | **DELETE** /api/purchase-orders/{purchaseOrderId}/grns/{grnId} | Delete goods receipt note |
| [**listGrns**](InventoryApi.md#listgrns) | **GET** /api/purchase-orders/{purchaseOrderId}/grns | List goods receipt notes for purchase order |
| [**listRmas**](InventoryApi.md#listrmas) | **GET** /api/rmas | List RMAs |
| [**reviewRma**](InventoryApi.md#reviewrma) | **POST** /api/rmas/{rmaId}/review | Review RMA |
| [**showGrn**](InventoryApi.md#showgrn) | **GET** /api/purchase-orders/{purchaseOrderId}/grns/{grnId} | Show goods receipt note |
| [**showRma**](InventoryApi.md#showrma) | **GET** /api/rmas/{rmaId} | Retrieve RMA |
| [**updateGrn**](InventoryApi.md#updategrnoperation) | **PUT** /api/purchase-orders/{purchaseOrderId}/grns/{grnId} | Update goods receipt note |



## createGrn

> ApiSuccessResponse createGrn(purchaseOrderId, createGrnRequest)

Create goods receipt note

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { CreateGrnOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // CreateGrnRequest
    createGrnRequest: ...,
  } satisfies CreateGrnOperationRequest;

  try {
    const data = await api.createGrn(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |
| **createGrnRequest** | [CreateGrnRequest](CreateGrnRequest.md) |  | |

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
| **201** | Goods receipt note created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createRma

> ApiSuccessResponse createRma(purchaseOrderId, createRmaRequest)

Create RMA for purchase order

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { CreateRmaOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // CreateRmaRequest
    createRmaRequest: ...,
  } satisfies CreateRmaOperationRequest;

  try {
    const data = await api.createRma(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |
| **createRmaRequest** | [CreateRmaRequest](CreateRmaRequest.md) |  | |

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
| **201** | RMA created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteGrn

> ApiSuccessResponse deleteGrn(purchaseOrderId, grnId)

Delete goods receipt note

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { DeleteGrnRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // number
    grnId: 56,
  } satisfies DeleteGrnRequest;

  try {
    const data = await api.deleteGrn(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |
| **grnId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Goods receipt deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listGrns

> ListGrns200Response listGrns(purchaseOrderId)

List goods receipt notes for purchase order

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { ListGrnsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
  } satisfies ListGrnsRequest;

  try {
    const data = await api.listGrns(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**ListGrns200Response**](ListGrns200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Collection of goods receipt notes. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listRmas

> ListRmas200Response listRmas()

List RMAs

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { ListRmasRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  try {
    const data = await api.listRmas();
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

This endpoint does not need any parameter.

### Return type

[**ListRmas200Response**](ListRmas200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Collection of RMAs. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## reviewRma

> ApiSuccessResponse reviewRma(rmaId, approveCreditNoteRequest)

Review RMA

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { ReviewRmaRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // string
    rmaId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // ApproveCreditNoteRequest
    approveCreditNoteRequest: ...,
  } satisfies ReviewRmaRequest;

  try {
    const data = await api.reviewRma(body);
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
| **rmaId** | `string` |  | [Defaults to `undefined`] |
| **approveCreditNoteRequest** | [ApproveCreditNoteRequest](ApproveCreditNoteRequest.md) |  | |

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
| **200** | Review recorded. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showGrn

> ShowGrn200Response showGrn(purchaseOrderId, grnId)

Show goods receipt note

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { ShowGrnRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // number
    grnId: 56,
  } satisfies ShowGrnRequest;

  try {
    const data = await api.showGrn(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |
| **grnId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**ShowGrn200Response**](ShowGrn200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Goods receipt details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showRma

> ShowRma200Response showRma(rmaId)

Retrieve RMA

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { ShowRmaRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // string
    rmaId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowRmaRequest;

  try {
    const data = await api.showRma(body);
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
| **rmaId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ShowRma200Response**](ShowRma200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | RMA detail. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateGrn

> ApiSuccessResponse updateGrn(purchaseOrderId, grnId, updateGrnRequest)

Update goods receipt note

### Example

```ts
import {
  Configuration,
  InventoryApi,
} from '';
import type { UpdateGrnOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InventoryApi(config);

  const body = {
    // number
    purchaseOrderId: 56,
    // number
    grnId: 56,
    // UpdateGrnRequest
    updateGrnRequest: ...,
  } satisfies UpdateGrnOperationRequest;

  try {
    const data = await api.updateGrn(body);
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
| **purchaseOrderId** | `number` |  | [Defaults to `undefined`] |
| **grnId** | `number` |  | [Defaults to `undefined`] |
| **updateGrnRequest** | [UpdateGrnRequest](UpdateGrnRequest.md) |  | |

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
| **200** | Goods receipt updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

