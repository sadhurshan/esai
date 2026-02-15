# NotificationsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                                                 | HTTP request                                     | Description                       |
| -------------------------------------------------------------------------------------- | ------------------------------------------------ | --------------------------------- |
| [**listNotifications**](NotificationsApi.md#listnotifications)                         | **GET** /api/notifications                       | List user notifications           |
| [**markAllNotificationsRead**](NotificationsApi.md#markallnotificationsread)           | **POST** /api/notifications/mark-all-read        | Mark all notifications as read    |
| [**markNotificationRead**](NotificationsApi.md#marknotificationread)                   | **PUT** /api/notifications/{notificationId}/read | Mark notification as read         |
| [**showNotificationPreferences**](NotificationsApi.md#shownotificationpreferences)     | **GET** /api/notification-preferences            | Retrieve notification preferences |
| [**updateNotificationPreferences**](NotificationsApi.md#updatenotificationpreferences) | **PUT** /api/notification-preferences            | Update notification preferences   |

## listNotifications

> ApiSuccessResponse listNotifications()

List user notifications

### Example

```ts
import { Configuration, NotificationsApi } from '';
import type { ListNotificationsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new NotificationsApi(config);

    try {
        const data = await api.listNotifications();
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

| Status code | Description                               | Response headers |
| ----------- | ----------------------------------------- | ---------------- |
| **200**     | Notification feed for authenticated user. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## markAllNotificationsRead

> ApiSuccessResponse markAllNotificationsRead()

Mark all notifications as read

### Example

```ts
import { Configuration, NotificationsApi } from '';
import type { MarkAllNotificationsReadRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new NotificationsApi(config);

    try {
        const data = await api.markAllNotificationsRead();
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

| Status code | Description                       | Response headers |
| ----------- | --------------------------------- | ---------------- |
| **200**     | All notifications marked as read. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## markNotificationRead

> ApiSuccessResponse markNotificationRead(notificationId)

Mark notification as read

### Example

```ts
import { Configuration, NotificationsApi } from '';
import type { MarkNotificationReadRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new NotificationsApi(config);

    const body = {
        // number
        notificationId: 56,
    } satisfies MarkNotificationReadRequest;

    try {
        const data = await api.markNotificationRead(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name               | Type     | Description | Notes                     |
| ------------------ | -------- | ----------- | ------------------------- |
| **notificationId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                  | Response headers |
| ----------- | ---------------------------- | ---------------- |
| **200**     | Notification marked as read. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showNotificationPreferences

> ApiSuccessResponse showNotificationPreferences()

Retrieve notification preferences

### Example

```ts
import { Configuration, NotificationsApi } from '';
import type { ShowNotificationPreferencesRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new NotificationsApi(config);

    try {
        const data = await api.showNotificationPreferences();
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

| Status code | Description                                                | Response headers |
| ----------- | ---------------------------------------------------------- | ---------------- |
| **200**     | Per-channel notification preferences for the current user. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateNotificationPreferences

> ApiSuccessResponse updateNotificationPreferences(requestBody)

Update notification preferences

### Example

```ts
import { Configuration, NotificationsApi } from '';
import type { UpdateNotificationPreferencesRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new NotificationsApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateNotificationPreferencesRequest;

    try {
        const data = await api.updateNotificationPreferences(body);
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

| Status code | Description          | Response headers |
| ----------- | -------------------- | ---------------- |
| **200**     | Preferences updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
