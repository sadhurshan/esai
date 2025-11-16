# InvoicesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**attachInvoiceFile**](InvoicesApi.md#attachinvoicefile) | **POST** /api/invoices/{invoiceId}/attachments | Upload supporting file for invoice |
| [**createInvoiceForPurchaseOrder**](InvoicesApi.md#createinvoiceforpurchaseorder) | **POST** /api/purchase-orders/{purchaseOrderId}/invoices | Create invoice for purchase order |
| [**createInvoiceFromPo**](InvoicesApi.md#createinvoicefrompo) | **POST** /api/invoices/from-po | Create invoice from purchase order reference |
| [**deleteInvoice**](InvoicesApi.md#deleteinvoice) | **DELETE** /api/invoices/{invoiceId} | Delete invoice |
| [**listInvoices**](InvoicesApi.md#listinvoices) | **GET** /api/invoices | List invoices for company |
| [**listInvoicesForPurchaseOrder**](InvoicesApi.md#listinvoicesforpurchaseorder) | **GET** /api/purchase-orders/{purchaseOrderId}/invoices | List invoices for a purchase order |
| [**recalculateInvoice**](InvoicesApi.md#recalculateinvoice) | **POST** /api/invoices/{invoiceId}/recalculate | Recalculate invoice totals |
| [**showInvoice**](InvoicesApi.md#showinvoice) | **GET** /api/invoices/{invoiceId} | Retrieve invoice |
| [**updateInvoice**](InvoicesApi.md#updateinvoiceoperation) | **PUT** /api/invoices/{invoiceId} | Update invoice metadata |



## attachInvoiceFile

> AttachInvoiceFile200Response attachInvoiceFile(invoiceId, file)

Upload supporting file for invoice

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { AttachInvoiceFileRequest } from '';

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
    // Blob
    file: BINARY_DATA_HERE,
  } satisfies AttachInvoiceFileRequest;

  try {
    const data = await api.attachInvoiceFile(body);
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
| **file** | `Blob` |  | [Defaults to `undefined`] |

### Return type

[**AttachInvoiceFile200Response**](AttachInvoiceFile200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Invoice attachment uploaded. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createInvoiceForPurchaseOrder

> CreateInvoiceFromPo200Response createInvoiceForPurchaseOrder(purchaseOrderId, lines, perPage, page, status, supplierId, from, to, invoiceNumber, invoiceDate, currency, document)

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
    // Array<InvoiceLineInput>
    lines: ...,
    // number (optional)
    perPage: 56,
    // number (optional)
    page: 56,
    // 'pending' | 'paid' | 'overdue' | 'disputed' (optional)
    status: status_example,
    // number (optional)
    supplierId: 56,
    // Date (optional)
    from: 2013-10-20,
    // Date (optional)
    to: 2013-10-20,
    // string (optional)
    invoiceNumber: invoiceNumber_example,
    // Date (optional)
    invoiceDate: 2013-10-20,
    // string (optional)
    currency: currency_example,
    // Blob (optional)
    document: BINARY_DATA_HERE,
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
| **lines** | `Array<InvoiceLineInput>` |  | |
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **status** | `pending`, `paid`, `overdue`, `disputed` |  | [Optional] [Defaults to `undefined`] [Enum: pending, paid, overdue, disputed] |
| **supplierId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **from** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **to** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **invoiceNumber** | `string` |  | [Optional] [Defaults to `undefined`] |
| **invoiceDate** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **currency** | `string` |  | [Optional] [Defaults to `undefined`] |
| **document** | `Blob` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**CreateInvoiceFromPo200Response**](CreateInvoiceFromPo200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Invoice captured. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createInvoiceFromPo

> CreateInvoiceFromPo200Response createInvoiceFromPo(poId, lines, supplierId, invoiceNumber, invoiceDate, currency, document)

Create invoice from purchase order reference

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { CreateInvoiceFromPoRequest } from '';

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
    // number
    poId: 56,
    // Array<CreateInvoiceFromPoRequestLinesInner>
    lines: ...,
    // number (optional)
    supplierId: 56,
    // string (optional)
    invoiceNumber: invoiceNumber_example,
    // Date (optional)
    invoiceDate: 2013-10-20,
    // string (optional)
    currency: currency_example,
    // Blob (optional)
    document: BINARY_DATA_HERE,
  } satisfies CreateInvoiceFromPoRequest;

  try {
    const data = await api.createInvoiceFromPo(body);
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
| **poId** | `number` |  | [Defaults to `undefined`] |
| **lines** | `Array<CreateInvoiceFromPoRequestLinesInner>` |  | |
| **supplierId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **invoiceNumber** | `string` |  | [Optional] [Defaults to `undefined`] |
| **invoiceDate** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **currency** | `string` |  | [Optional] [Defaults to `undefined`] |
| **document** | `Blob` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**CreateInvoiceFromPo200Response**](CreateInvoiceFromPo200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Invoice created from purchase order lines. |  -  |

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


## listInvoices

> ListInvoices200Response listInvoices(perPage, page, status, supplierId, from, to)

List invoices for company

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { ListInvoicesRequest } from '';

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
    // number (optional)
    perPage: 56,
    // number (optional)
    page: 56,
    // 'pending' | 'paid' | 'overdue' | 'disputed' (optional)
    status: status_example,
    // number (optional)
    supplierId: 56,
    // Date (optional)
    from: 2013-10-20,
    // Date (optional)
    to: 2013-10-20,
  } satisfies ListInvoicesRequest;

  try {
    const data = await api.listInvoices(body);
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
| **status** | `pending`, `paid`, `overdue`, `disputed` |  | [Optional] [Defaults to `undefined`] [Enum: pending, paid, overdue, disputed] |
| **supplierId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **from** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **to** | `Date` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListInvoices200Response**](ListInvoices200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated invoices for the active company. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listInvoicesForPurchaseOrder

> ListInvoices200Response listInvoicesForPurchaseOrder(purchaseOrderId, perPage, page, status, supplierId, from, to)

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
    // number (optional)
    perPage: 56,
    // number (optional)
    page: 56,
    // 'pending' | 'paid' | 'overdue' | 'disputed' (optional)
    status: status_example,
    // number (optional)
    supplierId: 56,
    // Date (optional)
    from: 2013-10-20,
    // Date (optional)
    to: 2013-10-20,
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
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **status** | `pending`, `paid`, `overdue`, `disputed` |  | [Optional] [Defaults to `undefined`] [Enum: pending, paid, overdue, disputed] |
| **supplierId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **from** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **to** | `Date` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListInvoices200Response**](ListInvoices200Response.md)

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


## recalculateInvoice

> CreateInvoiceFromPo200Response recalculateInvoice(invoiceId)

Recalculate invoice totals

### Example

```ts
import {
  Configuration,
  InvoicesApi,
} from '';
import type { RecalculateInvoiceRequest } from '';

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
  } satisfies RecalculateInvoiceRequest;

  try {
    const data = await api.recalculateInvoice(body);
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

[**CreateInvoiceFromPo200Response**](CreateInvoiceFromPo200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Recalculated invoice summary. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showInvoice

> CreateInvoiceFromPo200Response showInvoice(invoiceId)

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

[**CreateInvoiceFromPo200Response**](CreateInvoiceFromPo200Response.md)

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

> CreateInvoiceFromPo200Response updateInvoice(invoiceId, updateInvoiceRequest)

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

[**CreateInvoiceFromPo200Response**](CreateInvoiceFromPo200Response.md)

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

