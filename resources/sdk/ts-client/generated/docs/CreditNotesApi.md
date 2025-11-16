# CreditNotesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**approveCreditNote**](CreditNotesApi.md#approvecreditnoteoperation) | **POST** /api/credit-notes/{creditNoteId}/approve | Approve or reject credit note |
| [**attachCreditNoteFile**](CreditNotesApi.md#attachcreditnotefile) | **POST** /api/credit-notes/{creditNoteId}/attachments | Upload attachment for credit note |
| [**createCreditNote**](CreditNotesApi.md#createcreditnote) | **POST** /api/credit-notes/invoices/{invoiceId} | Draft credit note from invoice |
| [**issueCreditNote**](CreditNotesApi.md#issuecreditnote) | **POST** /api/credit-notes/{creditNoteId}/issue | Issue credit note to supplier |
| [**listCreditNotes**](CreditNotesApi.md#listcreditnotes) | **GET** /api/credit-notes | List credit notes for tenant |
| [**showCreditNote**](CreditNotesApi.md#showcreditnote) | **GET** /api/credit-notes/{creditNoteId} | Retrieve credit note |
| [**updateCreditNoteLines**](CreditNotesApi.md#updatecreditnotelinesoperation) | **PUT** /api/credit-notes/{creditNoteId}/lines | Update credit note lines |



## approveCreditNote

> ApiSuccessResponse approveCreditNote(creditNoteId, approveCreditNoteRequest)

Approve or reject credit note

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { ApproveCreditNoteOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // ApproveCreditNoteRequest
    approveCreditNoteRequest: ...,
  } satisfies ApproveCreditNoteOperationRequest;

  try {
    const data = await api.approveCreditNote(body);
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
| **creditNoteId** | `string` |  | [Defaults to `undefined`] |
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
| **200** | Credit note review captured. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## attachCreditNoteFile

> AttachCreditNoteFile200Response attachCreditNoteFile(creditNoteId, file)

Upload attachment for credit note

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { AttachCreditNoteFileRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // Blob
    file: BINARY_DATA_HERE,
  } satisfies AttachCreditNoteFileRequest;

  try {
    const data = await api.attachCreditNoteFile(body);
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
| **creditNoteId** | `string` |  | [Defaults to `undefined`] |
| **file** | `Blob` |  | [Defaults to `undefined`] |

### Return type

[**AttachCreditNoteFile200Response**](AttachCreditNoteFile200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Credit note attachment uploaded. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createCreditNote

> ApiSuccessResponse createCreditNote(invoiceId, reason, amountMinor, attachments)

Draft credit note from invoice

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { CreateCreditNoteRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    invoiceId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string
    reason: reason_example,
    // number
    amountMinor: 56,
    // Array<Blob> (optional)
    attachments: /path/to/file.txt,
  } satisfies CreateCreditNoteRequest;

  try {
    const data = await api.createCreditNote(body);
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
| **reason** | `string` |  | [Defaults to `undefined`] |
| **amountMinor** | `number` |  | [Defaults to `undefined`] |
| **attachments** | `Array<Blob>` |  | [Optional] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Credit note created in draft state. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## issueCreditNote

> ApiSuccessResponse issueCreditNote(creditNoteId)

Issue credit note to supplier

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { IssueCreditNoteRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies IssueCreditNoteRequest;

  try {
    const data = await api.issueCreditNote(body);
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
| **creditNoteId** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Credit note marked as issued. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listCreditNotes

> ListCreditNotes200Response listCreditNotes(page, perPage, status, invoiceId, createdFrom, createdTo)

List credit notes for tenant

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { ListCreditNotesRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // number (optional)
    page: 56,
    // number (optional)
    perPage: 56,
    // 'draft' | 'pending_review' | 'issued' | 'approved' | 'rejected' (optional)
    status: status_example,
    // number (optional)
    invoiceId: 56,
    // Date (optional)
    createdFrom: 2013-10-20,
    // Date (optional)
    createdTo: 2013-10-20,
  } satisfies ListCreditNotesRequest;

  try {
    const data = await api.listCreditNotes(body);
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
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **status** | `draft`, `pending_review`, `issued`, `approved`, `rejected` |  | [Optional] [Defaults to `undefined`] [Enum: draft, pending_review, issued, approved, rejected] |
| **invoiceId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **createdFrom** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **createdTo** | `Date` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListCreditNotes200Response**](ListCreditNotes200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated credit notes scoped to the current company. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showCreditNote

> ShowCreditNote200Response showCreditNote(creditNoteId)

Retrieve credit note

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { ShowCreditNoteRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowCreditNoteRequest;

  try {
    const data = await api.showCreditNote(body);
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
| **creditNoteId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ShowCreditNote200Response**](ShowCreditNote200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Credit note detail with invoice linkage. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateCreditNoteLines

> ShowCreditNote200Response updateCreditNoteLines(creditNoteId, updateCreditNoteLinesRequest)

Update credit note lines

### Example

```ts
import {
  Configuration,
  CreditNotesApi,
} from '';
import type { UpdateCreditNoteLinesOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CreditNotesApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // UpdateCreditNoteLinesRequest
    updateCreditNoteLinesRequest: ...,
  } satisfies UpdateCreditNoteLinesOperationRequest;

  try {
    const data = await api.updateCreditNoteLines(body);
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
| **creditNoteId** | `string` |  | [Defaults to `undefined`] |
| **updateCreditNoteLinesRequest** | [UpdateCreditNoteLinesRequest](UpdateCreditNoteLinesRequest.md) |  | |

### Return type

[**ShowCreditNote200Response**](ShowCreditNote200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Credit note lines updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

