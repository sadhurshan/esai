# CRMApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                         | HTTP request                     | Description                              |
| -------------------------------------------------------------- | -------------------------------- | ---------------------------------------- |
| [**crmExportPreview**](CRMApi.md#crmexportpreview)             | **GET** /api/crm/export-preview  | Preview CRM export payload               |
| [**crmExportPreviewFilter**](CRMApi.md#crmexportpreviewfilter) | **POST** /api/crm/export-preview | Generate CRM export preview with filters |

## crmExportPreview

> ApiSuccessResponse crmExportPreview()

Preview CRM export payload

### Example

```ts
import { Configuration, CRMApi } from '';
import type { CrmExportPreviewRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new CRMApi(config);

    try {
        const data = await api.crmExportPreview();
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

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                         | Response headers |
| ----------- | ----------------------------------- | ---------------- |
| **200**     | Preview of CRM export in JSON form. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## crmExportPreviewFilter

> ApiSuccessResponse crmExportPreviewFilter(requestBody)

Generate CRM export preview with filters

### Example

```ts
import { Configuration, CRMApi } from '';
import type { CrmExportPreviewFilterRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new CRMApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CrmExportPreviewFilterRequest;

    try {
        const data = await api.crmExportPreviewFilter(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes |
| --------------- | ------------------------- | ----------- | ----- |
| **requestBody** | `{ [key: string]: any; }` |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                  | Response headers |
| ----------- | ---------------------------- | ---------------- |
| **200**     | Filtered CRM export preview. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
