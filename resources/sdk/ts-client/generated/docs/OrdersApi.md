# OrdersApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                    | HTTP request                  | Description                 |
| ----------------------------------------- | ----------------------------- | --------------------------- |
| [**listOrders**](OrdersApi.md#listorders) | **GET** /api/orders           | List public supplier orders |
| [**showOrder**](OrdersApi.md#showorder)   | **GET** /api/orders/{orderId} | Show supplier order detail  |

## listOrders

> ApiSuccessResponse listOrders()

List public supplier orders

### Example

```ts
import { Configuration, OrdersApi } from '';
import type { ListOrdersRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new OrdersApi(config);

    try {
        const data = await api.listOrders();
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

| Status code | Description                                   | Response headers |
| ----------- | --------------------------------------------- | ---------------- |
| **200**     | Orders visible to the authenticated supplier. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showOrder

> ApiSuccessResponse showOrder(orderId)

Show supplier order detail

### Example

```ts
import { Configuration, OrdersApi } from '';
import type { ShowOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new OrdersApi(config);

    const body = {
        // number
        orderId: 56,
    } satisfies ShowOrderRequest;

    try {
        const data = await api.showOrder(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name        | Type     | Description | Notes                     |
| ----------- | -------- | ----------- | ------------------------- |
| **orderId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                      | Response headers |
| ----------- | -------------------------------- | ---------------- |
| **200**     | Order details for supplier view. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
