# DocumentsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**downloadQuoteAttachments**](DocumentsApi.md#downloadquoteattachments) | **GET** /api/files/attachments/{quoteId} | Download quote attachments archive |
| [**downloadRfqCad**](DocumentsApi.md#downloadrfqcad) | **GET** /api/files/cad/{rfqId} | Download RFQ CAD package |
| [**showDocument**](DocumentsApi.md#showdocument) | **GET** /api/documents/{document} | Show document metadata |
| [**storeDocument**](DocumentsApi.md#storedocument) | **POST** /api/documents | Persist document to entity |
| [**uploadFile**](DocumentsApi.md#uploadfile) | **POST** /api/files/upload | Upload file to temporary storage |



## downloadQuoteAttachments

> Blob downloadQuoteAttachments(quoteId)

Download quote attachments archive

### Example

```ts
import {
  Configuration,
  DocumentsApi,
} from '';
import type { DownloadQuoteAttachmentsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new DocumentsApi(config);

  const body = {
    // number
    quoteId: 56,
  } satisfies DownloadQuoteAttachmentsRequest;

  try {
    const data = await api.downloadQuoteAttachments(body);
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
| **quoteId** | `number` |  | [Defaults to `undefined`] |

### Return type

**Blob**

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/zip`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Consolidated quote attachments. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## downloadRfqCad

> Blob downloadRfqCad(rfqId)

Download RFQ CAD package

### Example

```ts
import {
  Configuration,
  DocumentsApi,
} from '';
import type { DownloadRfqCadRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new DocumentsApi(config);

  const body = {
    // number
    rfqId: 56,
  } satisfies DownloadRfqCadRequest;

  try {
    const data = await api.downloadRfqCad(body);
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
| **rfqId** | `number` |  | [Defaults to `undefined`] |

### Return type

**Blob**

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/zip`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | CAD archive for the RFQ. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showDocument

> StoreDocument201Response showDocument(document)

Show document metadata

### Example

```ts
import {
  Configuration,
  DocumentsApi,
} from '';
import type { ShowDocumentRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new DocumentsApi(config);

  const body = {
    // number
    document: 56,
  } satisfies ShowDocumentRequest;

  try {
    const data = await api.showDocument(body);
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
| **document** | `number` |  | [Defaults to `undefined`] |

### Return type

[**StoreDocument201Response**](StoreDocument201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Document metadata and download link for authorized viewers. |  -  |
| **403** | Caller is not authorized to view the requested document. |  -  |
| **404** | Document not found within the tenant scope. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## storeDocument

> StoreDocument201Response storeDocument(entity, entityId, kind, category, file, visibility, expiresAt, meta, watermark)

Persist document to entity

### Example

```ts
import {
  Configuration,
  DocumentsApi,
} from '';
import type { StoreDocumentRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new DocumentsApi(config);

  const body = {
    // string | Target entity slug to associate with the uploaded document.
    entity: entity_example,
    // number
    entityId: 56,
    // string
    kind: kind_example,
    // string
    category: category_example,
    // Blob
    file: BINARY_DATA_HERE,
    // string | Visibility must match the configured `documents.allowed_visibilities` list (private, company, public). (optional)
    visibility: visibility_example,
    // Date (optional)
    expiresAt: 2013-10-20T19:20:30+01:00,
    // object (optional)
    meta: Object,
    // object (optional)
    watermark: Object,
  } satisfies StoreDocumentRequest;

  try {
    const data = await api.storeDocument(body);
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
| **entity** | `rfq`, `quote`, `po`, `invoice`, `supplier`, `part` | Target entity slug to associate with the uploaded document. | [Defaults to `undefined`] [Enum: rfq, quote, po, invoice, supplier, part] |
| **entityId** | `number` |  | [Defaults to `undefined`] |
| **kind** | `rfq`, `quote`, `po`, `grn_attachment`, `invoice`, `supplier`, `part`, `cad`, `manual`, `certificate`, `esg_pack`, `other` |  | [Defaults to `undefined`] [Enum: rfq, quote, po, grn_attachment, invoice, supplier, part, cad, manual, certificate, esg_pack, other] |
| **category** | `technical`, `commercial`, `qa`, `logistics`, `financial`, `communication`, `esg`, `other` |  | [Defaults to `undefined`] [Enum: technical, commercial, qa, logistics, financial, communication, esg, other] |
| **file** | `Blob` |  | [Defaults to `undefined`] |
| **visibility** | `string` | Visibility must match the configured &#x60;documents.allowed_visibilities&#x60; list (private, company, public). | [Optional] [Defaults to `undefined`] |
| **expiresAt** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **meta** | `object` |  | [Optional] [Defaults to `undefined`] |
| **watermark** | `object` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**StoreDocument201Response**](StoreDocument201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Document stored and linked to record. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## uploadFile

> ApiSuccessResponse uploadFile(file)

Upload file to temporary storage

### Example

```ts
import {
  Configuration,
  DocumentsApi,
} from '';
import type { UploadFileRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new DocumentsApi(config);

  const body = {
    // Blob
    file: BINARY_DATA_HERE,
  } satisfies UploadFileRequest;

  try {
    const data = await api.uploadFile(body);
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
| **file** | `Blob` |  | [Defaults to `undefined`] |

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
| **201** | File uploaded and staged. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

