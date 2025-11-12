# InvoicesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**createInvoiceForPurchaseOrder**](InvoicesApi.md#createinvoiceforpurchaseorder) | **POST** /api/purchase-orders/{purchaseOrderId}/invoices | Create invoice for purchase order |
| [**deleteInvoice**](InvoicesApi.md#deleteinvoice) | **DELETE** /api/invoices/{invoiceId} | Delete invoice |
| [**listInvoicesForPurchaseOrder**](InvoicesApi.md#listinvoicesforpurchaseorder) | **GET** /api/purchase-orders/{purchaseOrderId}/invoices | List invoices for a purchase order |
| [**showInvoice**](InvoicesApi.md#showinvoice) | **GET** /api/invoices/{invoiceId} | Retrieve invoice |
| [**updateInvoice**](InvoicesApi.md#updateinvoiceoperation) | **PUT** /api/invoices/{invoiceId} | Update invoice metadata |



## createInvoiceForPurchaseOrder

> ShowInvoice200Response createInvoiceForPurchaseOrder(purchaseOrderId, invoiceNumber, currency, total, file, subtotal, taxAmount)

Create invoice for purchase order

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { CreateInvoiceForPurchaseOrderRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InvoicesApi(config);

  const body = {
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    invoiceNumber: invoiceNumber_example,
    // string
    currency: currency_example,
    // number
    total: 3.4,
    // Blob
    file: BINARY_DATA_HERE,
    // number (optional)
    subtotal: 3.4,
    // number (optional)
    taxAmount: 3.4,
  } satisfies CreateInvoiceForPurchaseOrderRequest;

  try {
    const data = await api.createInvoiceForPurchaseOrder(body);
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
| **invoiceNumber** | `string` |  | [Defaults to `undefined`] |
| **currency** | `string` |  | [Defaults to `undefined`] |
| **total** | `number` |  | [Defaults to `undefined`] |
| **file** | `Blob` |  | [Defaults to `undefined`] |
| **subtotal** | `number` |  | [Optional] [Defaults to `undefined`] |
| **taxAmount** | `number` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ShowInvoice200Response**](ShowInvoice200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Invoice captured. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteInvoice

> ApiSuccessResponse deleteInvoice(invoiceId)

Delete invoice

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { DeleteInvoiceRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InvoicesApi(config);

  const body = {
    // string
    invoiceId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies DeleteInvoiceRequest;

  try {
    const data = await api.deleteInvoice(body);
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
| **invoiceId** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Invoice deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listInvoicesForPurchaseOrder

> ListInvoicesForPurchaseOrder200Response listInvoicesForPurchaseOrder(purchaseOrderId)

List invoices for a purchase order

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { ListInvoicesForPurchaseOrderRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InvoicesApi(config);

  const body = {
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ListInvoicesForPurchaseOrderRequest;

  try {
    const data = await api.listInvoicesForPurchaseOrder(body);
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

[**ListInvoicesForPurchaseOrder200Response**](ListInvoicesForPurchaseOrder200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated invoices linked to the purchase order. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showInvoice

> ShowInvoice200Response showInvoice(invoiceId)

Retrieve invoice

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { ShowInvoiceRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InvoicesApi(config);

  const body = {
    // string
    invoiceId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowInvoiceRequest;

  try {
    const data = await api.showInvoice(body);
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
| **invoiceId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ShowInvoice200Response**](ShowInvoice200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Invoice details. |  -  |
| **404** | Resource not found. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateInvoice

> ApiSuccessResponse updateInvoice(invoiceId, updateInvoiceRequest)

Update invoice metadata

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { UpdateInvoiceOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new InvoicesApi(config);

  const body = {
    // string
    invoiceId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // UpdateInvoiceRequest
    updateInvoiceRequest: ...,
  } satisfies UpdateInvoiceOperationRequest;

  try {
    const data = await api.updateInvoice(body);
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
| **invoiceId** | `string` |  | [Defaults to `undefined`] |
| **updateInvoiceRequest** | [UpdateInvoiceRequest](UpdateInvoiceRequest.md) |  | |

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
| **200** | Invoice updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

