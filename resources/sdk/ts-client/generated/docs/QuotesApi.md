# QuotesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**listQuoteRevisions**](QuotesApi.md#listquoterevisions) | **GET** /api/rfqs/{rfqId}/quotes/{quoteId}/revisions | List quote revisions |
| [**listQuotesForRfq**](QuotesApi.md#listquotesforrfq) | **GET** /api/rfqs/{rfqId}/quotes | List quotes for RFQ |
| [**submitQuote**](QuotesApi.md#submitquoteoperation) | **POST** /api/quotes | Submit quote directly |
| [**submitQuoteRevision**](QuotesApi.md#submitquoterevisionoperation) | **POST** /api/rfqs/{rfqId}/quotes/{quoteId}/revisions | Submit quote revision |
| [**withdrawQuote**](QuotesApi.md#withdrawquoteoperation) | **POST** /api/rfqs/{rfqId}/quotes/{quoteId}/withdraw | Withdraw quote |



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

