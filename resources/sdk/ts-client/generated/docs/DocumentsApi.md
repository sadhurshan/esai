# DocumentsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**downloadQuoteAttachments**](DocumentsApi.md#downloadquoteattachments) | **GET** /api/files/attachments/{quoteId} | Download quote attachments archive |
| [**downloadRfqCad**](DocumentsApi.md#downloadrfqcad) | **GET** /api/files/cad/{rfqId} | Download RFQ CAD package |
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


## storeDocument

> ApiSuccessResponse storeDocument(file, documentableType, documentableId, label)

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
    // Blob
    file: BINARY_DATA_HERE,
    // string
    documentableType: documentableType_example,
    // number
    documentableId: 56,
    // string (optional)
    label: label_example,
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
| **file** | `Blob` |  | [Defaults to `undefined`] |
| **documentableType** | `string` |  | [Defaults to `undefined`] |
| **documentableId** | `number` |  | [Defaults to `undefined`] |
| **label** | `string` |  | [Optional] [Defaults to `undefined`] |

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

