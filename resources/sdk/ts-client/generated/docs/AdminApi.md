# AdminApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**adminAssignPlan**](AdminApi.md#adminassignplanoperation) | **POST** /api/admin/companies/{companyId}/assign-plan | Assign plan to company |
| [**adminCreateApiKey**](AdminApi.md#admincreateapikeyoperation) | **POST** /api/admin/api-keys | Issue API key for company |
| [**adminCreateFeatureFlag**](AdminApi.md#admincreatefeatureflagoperation) | **POST** /api/admin/companies/{companyId}/feature-flags | Create feature flag override |
| [**adminCreateRateLimit**](AdminApi.md#admincreateratelimitoperation) | **POST** /api/admin/rate-limits | Create rate limit rule |
| [**adminDeleteApiKey**](AdminApi.md#admindeleteapikey) | **DELETE** /api/admin/api-keys/{keyId} | Delete API key |
| [**adminDeleteFeatureFlag**](AdminApi.md#admindeletefeatureflag) | **DELETE** /api/admin/companies/{companyId}/feature-flags/{flagId} | Remove feature flag override |
| [**adminDeleteRateLimit**](AdminApi.md#admindeleteratelimit) | **DELETE** /api/admin/rate-limits/{rateLimitId} | Delete rate limit rule |
| [**adminHealthSummary**](AdminApi.md#adminhealthsummary) | **GET** /api/admin/health | Platform health summary |
| [**adminListApiKeys**](AdminApi.md#adminlistapikeys) | **GET** /api/admin/api-keys | List API keys |
| [**adminListFeatureFlags**](AdminApi.md#adminlistfeatureflags) | **GET** /api/admin/companies/{companyId}/feature-flags | List company feature flags |
| [**adminListRateLimits**](AdminApi.md#adminlistratelimits) | **GET** /api/admin/rate-limits | List rate limit rules |
| [**adminPlansDestroy**](AdminApi.md#adminplansdestroy) | **DELETE** /api/admin/plans/{planId} | Archive subscription plan |
| [**adminPlansIndex**](AdminApi.md#adminplansindex) | **GET** /api/admin/plans | List subscription plans |
| [**adminPlansShow**](AdminApi.md#adminplansshow) | **GET** /api/admin/plans/{planId} | Retrieve subscription plan |
| [**adminPlansStore**](AdminApi.md#adminplansstoreoperation) | **POST** /api/admin/plans | Create subscription plan |
| [**adminPlansUpdate**](AdminApi.md#adminplansupdateoperation) | **PUT** /api/admin/plans/{planId} | Update subscription plan |
| [**adminRotateApiKey**](AdminApi.md#adminrotateapikey) | **POST** /api/admin/api-keys/{keyId}/rotate | Rotate API key secret |
| [**adminShowRateLimit**](AdminApi.md#adminshowratelimit) | **GET** /api/admin/rate-limits/{rateLimitId} | Show rate limit rule |
| [**adminToggleApiKey**](AdminApi.md#admintoggleapikey) | **POST** /api/admin/api-keys/{keyId}/toggle | Toggle API key activation |
| [**adminUpdateCompanyStatus**](AdminApi.md#adminupdatecompanystatusoperation) | **PUT** /api/admin/companies/{companyId}/status | Update company lifecycle status |
| [**adminUpdateFeatureFlag**](AdminApi.md#adminupdatefeatureflagoperation) | **PUT** /api/admin/companies/{companyId}/feature-flags/{flagId} | Update feature flag override |
| [**adminUpdateRateLimit**](AdminApi.md#adminupdateratelimitoperation) | **PUT** /api/admin/rate-limits/{rateLimitId} | Update rate limit rule |



## adminAssignPlan

> ApiSuccessResponse adminAssignPlan(companyId, adminAssignPlanRequest)

Assign plan to company

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminAssignPlanOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
    // AdminAssignPlanRequest
    adminAssignPlanRequest: ...,
  } satisfies AdminAssignPlanOperationRequest;

  try {
    const data = await api.adminAssignPlan(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |
| **adminAssignPlanRequest** | [AdminAssignPlanRequest](AdminAssignPlanRequest.md) |  | |

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
| **202** | Plan assignment queued. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminCreateApiKey

> AdminCreateApiKey201Response adminCreateApiKey(adminCreateApiKeyRequest)

Issue API key for company

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminCreateApiKeyOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // AdminCreateApiKeyRequest
    adminCreateApiKeyRequest: ...,
  } satisfies AdminCreateApiKeyOperationRequest;

  try {
    const data = await api.adminCreateApiKey(body);
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
| **adminCreateApiKeyRequest** | [AdminCreateApiKeyRequest](AdminCreateApiKeyRequest.md) |  | |

### Return type

[**AdminCreateApiKey201Response**](AdminCreateApiKey201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | API key created. Plaintext secret returned once. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminCreateFeatureFlag

> ApiSuccessResponse adminCreateFeatureFlag(companyId, adminCreateFeatureFlagRequest)

Create feature flag override

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminCreateFeatureFlagOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
    // AdminCreateFeatureFlagRequest
    adminCreateFeatureFlagRequest: ...,
  } satisfies AdminCreateFeatureFlagOperationRequest;

  try {
    const data = await api.adminCreateFeatureFlag(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |
| **adminCreateFeatureFlagRequest** | [AdminCreateFeatureFlagRequest](AdminCreateFeatureFlagRequest.md) |  | |

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
| **201** | Feature flag created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminCreateRateLimit

> ApiSuccessResponse adminCreateRateLimit(adminCreateRateLimitRequest)

Create rate limit rule

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminCreateRateLimitOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // AdminCreateRateLimitRequest
    adminCreateRateLimitRequest: ...,
  } satisfies AdminCreateRateLimitOperationRequest;

  try {
    const data = await api.adminCreateRateLimit(body);
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
| **adminCreateRateLimitRequest** | [AdminCreateRateLimitRequest](AdminCreateRateLimitRequest.md) |  | |

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
| **201** | Rate limit created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminDeleteApiKey

> ApiSuccessResponse adminDeleteApiKey(keyId)

Delete API key

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminDeleteApiKeyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    keyId: 56,
  } satisfies AdminDeleteApiKeyRequest;

  try {
    const data = await api.adminDeleteApiKey(body);
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
| **keyId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | API key deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminDeleteFeatureFlag

> ApiSuccessResponse adminDeleteFeatureFlag(companyId, flagId)

Remove feature flag override

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminDeleteFeatureFlagRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
    // number
    flagId: 56,
  } satisfies AdminDeleteFeatureFlagRequest;

  try {
    const data = await api.adminDeleteFeatureFlag(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |
| **flagId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Feature flag removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminDeleteRateLimit

> ApiSuccessResponse adminDeleteRateLimit(rateLimitId)

Delete rate limit rule

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminDeleteRateLimitRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    rateLimitId: 56,
  } satisfies AdminDeleteRateLimitRequest;

  try {
    const data = await api.adminDeleteRateLimit(body);
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
| **rateLimitId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Rate limit removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminHealthSummary

> AdminHealthSummary200Response adminHealthSummary()

Platform health summary

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminHealthSummaryRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  try {
    const data = await api.adminHealthSummary();
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

[**AdminHealthSummary200Response**](AdminHealthSummary200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Platform health metrics. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminListApiKeys

> AdminListApiKeys200Response adminListApiKeys(companyId)

List API keys

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminListApiKeysRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number (optional)
    companyId: 56,
  } satisfies AdminListApiKeysRequest;

  try {
    const data = await api.adminListApiKeys(body);
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
| **companyId** | `number` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**AdminListApiKeys200Response**](AdminListApiKeys200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated API keys filtered by company when provided. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminListFeatureFlags

> AdminListFeatureFlags200Response adminListFeatureFlags(companyId)

List company feature flags

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminListFeatureFlagsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
  } satisfies AdminListFeatureFlagsRequest;

  try {
    const data = await api.adminListFeatureFlags(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**AdminListFeatureFlags200Response**](AdminListFeatureFlags200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Collection of company-scoped feature toggles. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminListRateLimits

> AdminListRateLimits200Response adminListRateLimits(companyId, page, perPage)

List rate limit rules

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminListRateLimitsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number (optional)
    companyId: 56,
    // number (optional)
    page: 56,
    // number (optional)
    perPage: 56,
  } satisfies AdminListRateLimitsRequest;

  try {
    const data = await api.adminListRateLimits(body);
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
| **companyId** | `number` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**AdminListRateLimits200Response**](AdminListRateLimits200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated rate limit definitions. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminPlansDestroy

> adminPlansDestroy(planId)

Archive subscription plan

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminPlansDestroyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    planId: 56,
  } satisfies AdminPlansDestroyRequest;

  try {
    const data = await api.adminPlansDestroy(body);
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
| **planId** | `number` |  | [Defaults to `undefined`] |

### Return type

`void` (Empty response body)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: Not defined


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **204** | Plan archived. Response body omitted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminPlansIndex

> AdminPlansIndex200Response adminPlansIndex()

List subscription plans

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminPlansIndexRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  try {
    const data = await api.adminPlansIndex();
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

[**AdminPlansIndex200Response**](AdminPlansIndex200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated plans configured in the platform. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminPlansShow

> AdminPlansShow200Response adminPlansShow(planId)

Retrieve subscription plan

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminPlansShowRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    planId: 56,
  } satisfies AdminPlansShowRequest;

  try {
    const data = await api.adminPlansShow(body);
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
| **planId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**AdminPlansShow200Response**](AdminPlansShow200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Subscription plan details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminPlansStore

> ApiSuccessResponse adminPlansStore(adminPlansStoreRequest)

Create subscription plan

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminPlansStoreOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // AdminPlansStoreRequest
    adminPlansStoreRequest: ...,
  } satisfies AdminPlansStoreOperationRequest;

  try {
    const data = await api.adminPlansStore(body);
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
| **adminPlansStoreRequest** | [AdminPlansStoreRequest](AdminPlansStoreRequest.md) |  | |

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
| **201** | Plan created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminPlansUpdate

> ApiSuccessResponse adminPlansUpdate(planId, adminPlansUpdateRequest)

Update subscription plan

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminPlansUpdateOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    planId: 56,
    // AdminPlansUpdateRequest
    adminPlansUpdateRequest: ...,
  } satisfies AdminPlansUpdateOperationRequest;

  try {
    const data = await api.adminPlansUpdate(body);
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
| **planId** | `number` |  | [Defaults to `undefined`] |
| **adminPlansUpdateRequest** | [AdminPlansUpdateRequest](AdminPlansUpdateRequest.md) |  | |

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
| **200** | Plan updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminRotateApiKey

> AdminRotateApiKey201Response adminRotateApiKey(keyId)

Rotate API key secret

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminRotateApiKeyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    keyId: 56,
  } satisfies AdminRotateApiKeyRequest;

  try {
    const data = await api.adminRotateApiKey(body);
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
| **keyId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**AdminRotateApiKey201Response**](AdminRotateApiKey201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | API key secret rotated. Returns new token. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminShowRateLimit

> AdminShowRateLimit200Response adminShowRateLimit(rateLimitId)

Show rate limit rule

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminShowRateLimitRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    rateLimitId: 56,
  } satisfies AdminShowRateLimitRequest;

  try {
    const data = await api.adminShowRateLimit(body);
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
| **rateLimitId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**AdminShowRateLimit200Response**](AdminShowRateLimit200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Rate limit rule detail. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminToggleApiKey

> ApiSuccessResponse adminToggleApiKey(keyId)

Toggle API key activation

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminToggleApiKeyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    keyId: 56,
  } satisfies AdminToggleApiKeyRequest;

  try {
    const data = await api.adminToggleApiKey(body);
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
| **keyId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | API key status toggled. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminUpdateCompanyStatus

> ApiSuccessResponse adminUpdateCompanyStatus(companyId, adminUpdateCompanyStatusRequest)

Update company lifecycle status

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminUpdateCompanyStatusOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
    // AdminUpdateCompanyStatusRequest
    adminUpdateCompanyStatusRequest: ...,
  } satisfies AdminUpdateCompanyStatusOperationRequest;

  try {
    const data = await api.adminUpdateCompanyStatus(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |
| **adminUpdateCompanyStatusRequest** | [AdminUpdateCompanyStatusRequest](AdminUpdateCompanyStatusRequest.md) |  | |

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
| **200** | Company status updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminUpdateFeatureFlag

> ApiSuccessResponse adminUpdateFeatureFlag(companyId, flagId, adminUpdateFeatureFlagRequest)

Update feature flag override

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminUpdateFeatureFlagOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    companyId: 56,
    // number
    flagId: 56,
    // AdminUpdateFeatureFlagRequest
    adminUpdateFeatureFlagRequest: ...,
  } satisfies AdminUpdateFeatureFlagOperationRequest;

  try {
    const data = await api.adminUpdateFeatureFlag(body);
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
| **companyId** | `number` |  | [Defaults to `undefined`] |
| **flagId** | `number` |  | [Defaults to `undefined`] |
| **adminUpdateFeatureFlagRequest** | [AdminUpdateFeatureFlagRequest](AdminUpdateFeatureFlagRequest.md) |  | |

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
| **200** | Feature flag updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## adminUpdateRateLimit

> ApiSuccessResponse adminUpdateRateLimit(rateLimitId, adminUpdateRateLimitRequest)

Update rate limit rule

### Example

```ts
import {
  Configuration,
  AdminApi,
} from '';
import type { AdminUpdateRateLimitOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AdminApi(config);

  const body = {
    // number
    rateLimitId: 56,
    // AdminUpdateRateLimitRequest
    adminUpdateRateLimitRequest: ...,
  } satisfies AdminUpdateRateLimitOperationRequest;

  try {
    const data = await api.adminUpdateRateLimit(body);
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
| **rateLimitId** | `number` |  | [Defaults to `undefined`] |
| **adminUpdateRateLimitRequest** | [AdminUpdateRateLimitRequest](AdminUpdateRateLimitRequest.md) |  | |

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
| **200** | Rate limit updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

