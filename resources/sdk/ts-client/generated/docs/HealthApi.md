# HealthApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                                  | HTTP request                   | Description                        |
| ----------------------------------------------------------------------- | ------------------------------ | ---------------------------------- |
| [**downloadOpenApi**](HealthApi.md#downloadopenapi)                     | **GET** /api/docs/openapi.json | Download compiled OpenAPI document |
| [**downloadPostmanCollection**](HealthApi.md#downloadpostmancollection) | **GET** /api/docs/postman.json | Download Postman collection        |
| [**getHealth**](HealthApi.md#gethealth)                                 | **GET** /api/health            | API health check                   |

## downloadOpenApi

> object downloadOpenApi()

Download compiled OpenAPI document

Returns the compiled OpenAPI JSON artifact produced by the &#x60;api:spec:build&#x60; command.

### Example

```ts
import { Configuration, HealthApi } from '';
import type { DownloadOpenApiRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const api = new HealthApi();

    try {
        const data = await api.downloadOpenApi();
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

**object**

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                 | Response headers |
| ----------- | --------------------------- | ---------------- |
| **200**     | OpenAPI document.           | -                |
| **404**     | No compiled document found. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## downloadPostmanCollection

> object downloadPostmanCollection()

Download Postman collection

Returns the Postman collection generated from the compiled OpenAPI document.

### Example

```ts
import { Configuration, HealthApi } from '';
import type { DownloadPostmanCollectionRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const api = new HealthApi();

    try {
        const data = await api.downloadPostmanCollection();
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

**object**

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                            | Response headers |
| ----------- | -------------------------------------- | ---------------- |
| **200**     | Postman v2.1 collection file.          | -                |
| **404**     | No generated Postman collection found. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## getHealth

> GetHealth200Response getHealth()

API health check

### Example

```ts
import { Configuration, HealthApi } from '';
import type { GetHealthRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const api = new HealthApi();

    try {
        const data = await api.getHealth();
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

[**GetHealth200Response**](GetHealth200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                     | Response headers       |
| ----------- | ------------------------------- | ---------------------- |
| **200**     | Current API health information. | \* X-Request-Id - <br> |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
