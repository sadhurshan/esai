# CopilotApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                           | HTTP request                           | Description                            |
| ---------------------------------------------------------------- | -------------------------------------- | -------------------------------------- |
| [**copilotAnalytics**](CopilotApi.md#copilotanalytics)           | **POST** /api/copilot/analytics        | Generate analytics insight via Copilot |
| [**copilotChatCompletion**](CopilotApi.md#copilotchatcompletion) | **POST** /api/copilot/chat/completions | Stream structured copilot response     |
| [**listCopilotPrompts**](CopilotApi.md#listcopilotprompts)       | **GET** /api/copilot/prompts           | List available copilot prompts         |

## copilotAnalytics

> ApiSuccessResponse copilotAnalytics(requestBody)

Generate analytics insight via Copilot

### Example

```ts
import { Configuration, CopilotApi } from '';
import type { CopilotAnalyticsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new CopilotApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CopilotAnalyticsRequest;

    try {
        const data = await api.copilotAnalytics(body);
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

| Status code | Description                                  | Response headers |
| ----------- | -------------------------------------------- | ---------------- |
| **200**     | Analytics insight with supporting narrative. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## copilotChatCompletion

> ApiSuccessResponse copilotChatCompletion(requestBody)

Stream structured copilot response

### Example

```ts
import { Configuration, CopilotApi } from '';
import type { CopilotChatCompletionRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new CopilotApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CopilotChatCompletionRequest;

    try {
        const data = await api.copilotChatCompletion(body);
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

| Status code | Description                           | Response headers |
| ----------- | ------------------------------------- | ---------------- |
| **200**     | Copilot generated completion payload. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listCopilotPrompts

> ApiSuccessResponse listCopilotPrompts()

List available copilot prompts

### Example

```ts
import { Configuration, CopilotApi } from '';
import type { ListCopilotPromptsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new CopilotApi(config);

    try {
        const data = await api.listCopilotPrompts();
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

| Status code | Description             | Response headers |
| ----------- | ----------------------- | ---------------- |
| **200**     | Copilot prompt catalog. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
