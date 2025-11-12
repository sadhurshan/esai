# ExportsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**createExportRequest**](ExportsApi.md#createexportrequestoperation) | **POST** /api/exports | Queue export request |
| [**downloadExport**](ExportsApi.md#downloadexport) | **GET** /api/exports/{exportRequestId}/download | Download export artifact |
| [**listExportRequests**](ExportsApi.md#listexportrequests) | **GET** /api/exports | List export requests |
| [**showExportRequest**](ExportsApi.md#showexportrequest) | **GET** /api/exports/{exportRequestId} | Retrieve export request |



## createExportRequest

> CreateExportRequest201Response createExportRequest(createExportRequestRequest)

Queue export request

### Example

```ts
import {
  Configuration,
  ExportsApi,
} from '';
import type { CreateExportRequestOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ExportsApi(config);

  const body = {
    // CreateExportRequestRequest
    createExportRequestRequest: ...,
  } satisfies CreateExportRequestOperationRequest;

  try {
    const data = await api.createExportRequest(body);
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
| **createExportRequestRequest** | [CreateExportRequestRequest](CreateExportRequestRequest.md) |  | |

### Return type

[**CreateExportRequest201Response**](CreateExportRequest201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Export request queued. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## downloadExport

> Blob downloadExport(signature, exportRequestId)

Download export artifact

### Example

```ts
import {
  Configuration,
  ExportsApi,
} from '';
import type { DownloadExportRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ExportsApi(config);

  const body = {
    // string
    signature: signature_example,
    // string
    exportRequestId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies DownloadExportRequest;

  try {
    const data = await api.downloadExport(body);
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
| **signature** | `string` |  | [Defaults to `undefined`] |
| **exportRequestId** | `string` |  | [Defaults to `undefined`] |

### Return type

**Blob**

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/zip`, `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Export zip archive. |  -  |
| **409** | Export not ready. |  -  |
| **410** | Export has expired. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listExportRequests

> ListExportRequests200Response listExportRequests(page, perPage)

List export requests

### Example

```ts
import {
  Configuration,
  ExportsApi,
} from '';
import type { ListExportRequestsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ExportsApi(config);

  const body = {
    // number (optional)
    page: 56,
    // number (optional)
    perPage: 56,
  } satisfies ListExportRequestsRequest;

  try {
    const data = await api.listExportRequests(body);
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

### Return type

[**ListExportRequests200Response**](ListExportRequests200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated export requests for the current company. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showExportRequest

> CreateExportRequest201Response showExportRequest(exportRequestId)

Retrieve export request

### Example

```ts
import {
  Configuration,
  ExportsApi,
} from '';
import type { ShowExportRequestRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ExportsApi(config);

  const body = {
    // string
    exportRequestId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowExportRequestRequest;

  try {
    const data = await api.showExportRequest(body);
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
| **exportRequestId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**CreateExportRequest201Response**](CreateExportRequest201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Export request details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

