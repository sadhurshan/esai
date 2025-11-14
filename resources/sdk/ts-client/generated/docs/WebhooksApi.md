# WebhooksApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**adminCreateWebhookSubscription**](WebhooksApi.md#admincreatewebhooksubscriptionoperation) | **POST** /api/admin/webhook-subscriptions | Create webhook subscription |
| [**adminDeleteWebhookSubscription**](WebhooksApi.md#admindeletewebhooksubscription) | **DELETE** /api/admin/webhook-subscriptions/{subscriptionId} | Remove webhook subscription |
| [**adminListWebhookDeliveries**](WebhooksApi.md#adminlistwebhookdeliveries) | **GET** /api/admin/webhook-deliveries | List webhook deliveries |
| [**adminListWebhookSubscriptions**](WebhooksApi.md#adminlistwebhooksubscriptions) | **GET** /api/admin/webhook-subscriptions | List webhook subscriptions |
| [**adminRetryWebhookDelivery**](WebhooksApi.md#adminretrywebhookdelivery) | **POST** /api/admin/webhook-deliveries/{deliveryId}/retry | Retry failed webhook delivery |
| [**adminShowWebhookSubscription**](WebhooksApi.md#adminshowwebhooksubscription) | **GET** /api/admin/webhook-subscriptions/{subscriptionId} | Retrieve webhook subscription |
| [**adminUpdateWebhookSubscription**](WebhooksApi.md#adminupdatewebhooksubscriptionoperation) | **PUT** /api/admin/webhook-subscriptions/{subscriptionId} | Update webhook subscription |
| [**stripeInvoicePaymentFailed**](WebhooksApi.md#stripeinvoicepaymentfailed) | **POST** /api/webhooks/stripe/invoice/payment-failed | Stripe invoice payment failed event hook |
| [**stripeInvoicePaymentSucceeded**](WebhooksApi.md#stripeinvoicepaymentsucceeded) | **POST** /api/webhooks/stripe/invoice/payment-succeeded | Stripe invoice payment succeeded event hook |
| [**stripeSubscriptionUpdated**](WebhooksApi.md#stripesubscriptionupdated) | **POST** /api/webhooks/stripe/customer/subscription-updated | Stripe subscription updated webhook |



## adminCreateWebhookSubscription

> ApiSuccessResponse adminCreateWebhookSubscription(adminCreateWebhookSubscriptionRequest)

Create webhook subscription

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminCreateWebhookSubscriptionOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // AdminCreateWebhookSubscriptionRequest
    adminCreateWebhookSubscriptionRequest: ...,
  } satisfies AdminCreateWebhookSubscriptionOperationRequest;

  try {
    const data = await api.adminCreateWebhookSubscription(body);
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
| **adminCreateWebhookSubscriptionRequest** | [AdminCreateWebhookSubscriptionRequest](AdminCreateWebhookSubscriptionRequest.md) |  | |

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
| **201** | Webhook subscription created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminDeleteWebhookSubscription

> ApiSuccessResponse adminDeleteWebhookSubscription(subscriptionId)

Remove webhook subscription

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminDeleteWebhookSubscriptionRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // string
    subscriptionId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies AdminDeleteWebhookSubscriptionRequest;

  try {
    const data = await api.adminDeleteWebhookSubscription(body);
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
| **subscriptionId** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Webhook subscription removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminListWebhookDeliveries

> AdminListWebhookDeliveries200Response adminListWebhookDeliveries(subscriptionId, page, perPage)

List webhook deliveries

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminListWebhookDeliveriesRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // string (optional)
    subscriptionId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // number (optional)
    page: 56,
    // number (optional)
    perPage: 56,
  } satisfies AdminListWebhookDeliveriesRequest;

  try {
    const data = await api.adminListWebhookDeliveries(body);
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
| **subscriptionId** | `string` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**AdminListWebhookDeliveries200Response**](AdminListWebhookDeliveries200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated history of webhook delivery attempts. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminListWebhookSubscriptions

> AdminListWebhookSubscriptions200Response adminListWebhookSubscriptions()

List webhook subscriptions

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminListWebhookSubscriptionsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  try {
    const data = await api.adminListWebhookSubscriptions();
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

[**AdminListWebhookSubscriptions200Response**](AdminListWebhookSubscriptions200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated webhook subscriptions. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminRetryWebhookDelivery

> ApiSuccessResponse adminRetryWebhookDelivery(deliveryId)

Retry failed webhook delivery

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminRetryWebhookDeliveryRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // string
    deliveryId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies AdminRetryWebhookDeliveryRequest;

  try {
    const data = await api.adminRetryWebhookDelivery(body);
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
| **deliveryId** | `string` |  | [Defaults to `undefined`] |

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
| **202** | Delivery queued for retry. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminShowWebhookSubscription

> AdminShowWebhookSubscription200Response adminShowWebhookSubscription(subscriptionId)

Retrieve webhook subscription

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminShowWebhookSubscriptionRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // string
    subscriptionId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies AdminShowWebhookSubscriptionRequest;

  try {
    const data = await api.adminShowWebhookSubscription(body);
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
| **subscriptionId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**AdminShowWebhookSubscription200Response**](AdminShowWebhookSubscription200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Webhook subscription detail. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminUpdateWebhookSubscription

> ApiSuccessResponse adminUpdateWebhookSubscription(subscriptionId, adminUpdateWebhookSubscriptionRequest)

Update webhook subscription

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { AdminUpdateWebhookSubscriptionOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // string
    subscriptionId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // AdminUpdateWebhookSubscriptionRequest
    adminUpdateWebhookSubscriptionRequest: ...,
  } satisfies AdminUpdateWebhookSubscriptionOperationRequest;

  try {
    const data = await api.adminUpdateWebhookSubscription(body);
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
| **subscriptionId** | `string` |  | [Defaults to `undefined`] |
| **adminUpdateWebhookSubscriptionRequest** | [AdminUpdateWebhookSubscriptionRequest](AdminUpdateWebhookSubscriptionRequest.md) |  | |

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
| **200** | Webhook subscription updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## stripeInvoicePaymentFailed

> ApiSuccessResponse stripeInvoicePaymentFailed(requestBody)

Stripe invoice payment failed event hook

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { StripeInvoicePaymentFailedRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies StripeInvoicePaymentFailedRequest;

  try {
    const data = await api.stripeInvoicePaymentFailed(body);
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
| **requestBody** | `{ [key: string]: any; }` |  | |

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
| **200** | Webhook acknowledged. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## stripeInvoicePaymentSucceeded

> ApiSuccessResponse stripeInvoicePaymentSucceeded(requestBody)

Stripe invoice payment succeeded event hook

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { StripeInvoicePaymentSucceededRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies StripeInvoicePaymentSucceededRequest;

  try {
    const data = await api.stripeInvoicePaymentSucceeded(body);
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
| **requestBody** | `{ [key: string]: any; }` |  | |

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
| **200** | Webhook acknowledged. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## stripeSubscriptionUpdated

> ApiSuccessResponse stripeSubscriptionUpdated(requestBody)

Stripe subscription updated webhook

### Example

```ts
import {
  Configuration,
  WebhooksApi,
} from '';
import type { StripeSubscriptionUpdatedRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new WebhooksApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies StripeSubscriptionUpdatedRequest;

  try {
    const data = await api.stripeSubscriptionUpdated(body);
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
| **requestBody** | `{ [key: string]: any; }` |  | |

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
| **200** | Webhook acknowledged. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

