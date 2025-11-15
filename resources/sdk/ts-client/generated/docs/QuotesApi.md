# QuotesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**addQuoteLine**](QuotesApi.md#addquoteline) | **POST** /api/quotes/{quoteId}/lines | Add line to draft quote |
| [**deleteQuoteLine**](QuotesApi.md#deletequoteline) | **DELETE** /api/quotes/{quoteId}/lines/{quoteItemId} | Remove a quote line |
| [**listQuoteRevisions**](QuotesApi.md#listquoterevisions) | **GET** /api/rfqs/{rfqId}/quotes/{quoteId}/revisions | List quote revisions |
| [**listQuotesForRfq**](QuotesApi.md#listquotesforrfq) | **GET** /api/rfqs/{rfqId}/quotes | List quotes for RFQ |
| [**listSupplierQuotes**](QuotesApi.md#listsupplierquotes) | **GET** /api/supplier/quotes | List quotes for the authenticated supplier company |
| [**showQuote**](QuotesApi.md#showquote) | **GET** /api/quotes/{quoteId} | Fetch a single quote |
| [**submitDraftQuote**](QuotesApi.md#submitdraftquote) | **POST** /api/quotes/{quoteId}/submit | Submit a draft quote |
| [**submitQuote**](QuotesApi.md#submitquoteoperation) | **POST** /api/quotes | Submit quote directly |
| [**submitQuoteRevision**](QuotesApi.md#submitquoterevisionoperation) | **POST** /api/rfqs/{rfqId}/quotes/{quoteId}/revisions | Submit quote revision |
| [**updateQuoteLine**](QuotesApi.md#updatequoteline) | **PUT** /api/quotes/{quoteId}/lines/{quoteItemId} | Update an existing quote line |
| [**withdrawQuote**](QuotesApi.md#withdrawquoteoperation) | **POST** /api/rfqs/{rfqId}/quotes/{quoteId}/withdraw | Withdraw quote |



## addQuoteLine

> SubmitQuote201Response addQuoteLine(quoteId, quoteLineRequest)

Add line to draft quote

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { AddQuoteLineRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // QuoteLineRequest
    quoteLineRequest: ...,
  } satisfies AddQuoteLineRequest;

  try {
    const data = await api.addQuoteLine(body);
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
| **quoteId** | `string` |  | [Defaults to `undefined`] |
| **quoteLineRequest** | [QuoteLineRequest](QuoteLineRequest.md) |  | |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Line added and totals recalculated. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteQuoteLine

> SubmitQuote201Response deleteQuoteLine(quoteId, quoteItemId)

Remove a quote line

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { DeleteQuoteLineRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    quoteItemId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies DeleteQuoteLineRequest;

  try {
    const data = await api.deleteQuoteLine(body);
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
| **quoteId** | `string` |  | [Defaults to `undefined`] |
| **quoteItemId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Updated quote after removal. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listQuoteRevisions

> ListQuoteRevisions200Response listQuoteRevisions(rfqId, quoteId)

List quote revisions

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { ListQuoteRevisionsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ListQuoteRevisionsRequest;

  try {
    const data = await api.listQuoteRevisions(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **quoteId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ListQuoteRevisions200Response**](ListQuoteRevisions200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Quote revision history. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listQuotesForRfq

> ListQuotesForRfq200Response listQuotesForRfq(rfqId)

List quotes for RFQ

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { ListQuotesForRfqRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ListQuotesForRfqRequest;

  try {
    const data = await api.listQuotesForRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ListQuotesForRfq200Response**](ListQuotesForRfq200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Quotes submitted for the RFQ. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listSupplierQuotes

> ListSupplierQuotes200Response listSupplierQuotes(rfqId, status, rfqNumber, page, perPage, sort)

List quotes for the authenticated supplier company

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { ListSupplierQuotesRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string (optional)
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string (optional)
    status: status_example,
    // string (optional)
    rfqNumber: rfqNumber_example,
    // number (optional)
    page: 56,
    // number (optional)
    perPage: 56,
    // 'created_at' | 'submitted_at' | 'total_minor' (optional)
    sort: sort_example,
  } satisfies ListSupplierQuotesRequest;

  try {
    const data = await api.listSupplierQuotes(body);
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
| **rfqId** | `string` |  | [Optional] [Defaults to `undefined`] |
| **status** | `string` |  | [Optional] [Defaults to `undefined`] |
| **rfqNumber** | `string` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **sort** | `created_at`, `submitted_at`, `total_minor` |  | [Optional] [Defaults to `undefined`] [Enum: created_at, submitted_at, total_minor] |

### Return type

[**ListSupplierQuotes200Response**](ListSupplierQuotes200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Supplier quotes list. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showQuote

> SubmitQuote201Response showQuote(quoteId)

Fetch a single quote

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { ShowQuoteRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowQuoteRequest;

  try {
    const data = await api.showQuote(body);
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
| **quoteId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Quote details including lines, attachments, and revisions. |  -  |
| **404** | Resource not found. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## submitDraftQuote

> SubmitQuote201Response submitDraftQuote(quoteId)

Submit a draft quote

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { SubmitDraftQuoteRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies SubmitDraftQuoteRequest;

  try {
    const data = await api.submitDraftQuote(body);
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
| **quoteId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Quote submitted successfully. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## submitQuote

> SubmitQuote201Response submitQuote(submitQuoteRequest)

Submit quote directly

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { SubmitQuoteOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // SubmitQuoteRequest
    submitQuoteRequest: ...,
  } satisfies SubmitQuoteOperationRequest;

  try {
    const data = await api.submitQuote(body);
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
| **submitQuoteRequest** | [SubmitQuoteRequest](SubmitQuoteRequest.md) |  | |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Quote submitted. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## submitQuoteRevision

> ApiSuccessResponse submitQuoteRevision(rfqId, quoteId, submitQuoteRevisionRequest)

Submit quote revision

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { SubmitQuoteRevisionOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // SubmitQuoteRevisionRequest
    submitQuoteRevisionRequest: ...,
  } satisfies SubmitQuoteRevisionOperationRequest;

  try {
    const data = await api.submitQuoteRevision(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **quoteId** | `string` |  | [Defaults to `undefined`] |
| **submitQuoteRevisionRequest** | [SubmitQuoteRevisionRequest](SubmitQuoteRevisionRequest.md) |  | |

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
| **201** | Revision accepted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateQuoteLine

> SubmitQuote201Response updateQuoteLine(quoteId, quoteItemId, quoteLineUpdateRequest)

Update an existing quote line

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { UpdateQuoteLineRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    quoteItemId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // QuoteLineUpdateRequest
    quoteLineUpdateRequest: ...,
  } satisfies UpdateQuoteLineRequest;

  try {
    const data = await api.updateQuoteLine(body);
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
| **quoteId** | `string` |  | [Defaults to `undefined`] |
| **quoteItemId** | `string` |  | [Defaults to `undefined`] |
| **quoteLineUpdateRequest** | [QuoteLineUpdateRequest](QuoteLineUpdateRequest.md) |  | |

### Return type

[**SubmitQuote201Response**](SubmitQuote201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Line updated and totals recalculated. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## withdrawQuote

> ApiSuccessResponse withdrawQuote(rfqId, quoteId, withdrawQuoteRequest)

Withdraw quote

### Example

```ts
import {
  Configuration,
  QuotesApi,
} from '';
import type { WithdrawQuoteOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new QuotesApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // WithdrawQuoteRequest
    withdrawQuoteRequest: ...,
  } satisfies WithdrawQuoteOperationRequest;

  try {
    const data = await api.withdrawQuote(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **quoteId** | `string` |  | [Defaults to `undefined`] |
| **withdrawQuoteRequest** | [WithdrawQuoteRequest](WithdrawQuoteRequest.md) |  | |

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
| **200** | Quote withdrawn. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

