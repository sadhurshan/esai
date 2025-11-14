# ApprovalsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**actOnApprovalRequest**](ApprovalsApi.md#actonapprovalrequest) | **POST** /api/approvals/requests/{approvalId}/action | Take action on approval request |
| [**createApprovalDelegation**](ApprovalsApi.md#createapprovaldelegation) | **POST** /api/approvals/delegations | Create approval delegation |
| [**createApprovalRule**](ApprovalsApi.md#createapprovalrule) | **POST** /api/approvals/rules | Create approval rule |
| [**deleteApprovalDelegation**](ApprovalsApi.md#deleteapprovaldelegation) | **DELETE** /api/approvals/delegations/{delegationId} | Delete approval delegation |
| [**deleteApprovalRule**](ApprovalsApi.md#deleteapprovalrule) | **DELETE** /api/approvals/rules/{ruleId} | Delete approval rule |
| [**listApprovalDelegations**](ApprovalsApi.md#listapprovaldelegations) | **GET** /api/approvals/delegations | List approval delegations |
| [**listApprovalRequests**](ApprovalsApi.md#listapprovalrequests) | **GET** /api/approvals/requests | List approval requests |
| [**listApprovalRules**](ApprovalsApi.md#listapprovalrules) | **GET** /api/approvals/rules | List approval rules |
| [**showApprovalRequest**](ApprovalsApi.md#showapprovalrequest) | **GET** /api/approvals/requests/{approvalId} | Show approval request |
| [**showApprovalRule**](ApprovalsApi.md#showapprovalrule) | **GET** /api/approvals/rules/{ruleId} | Show approval rule |
| [**updateApprovalDelegation**](ApprovalsApi.md#updateapprovaldelegation) | **PUT** /api/approvals/delegations/{delegationId} | Update approval delegation |
| [**updateApprovalRule**](ApprovalsApi.md#updateapprovalrule) | **PUT** /api/approvals/rules/{ruleId} | Update approval rule |



## actOnApprovalRequest

> ApiSuccessResponse actOnApprovalRequest(approvalId, requestBody)

Take action on approval request

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ActOnApprovalRequestRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    approvalId: 56,
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies ActOnApprovalRequestRequest;

  try {
    const data = await api.actOnApprovalRequest(body);
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
| **approvalId** | `number` |  | [Defaults to `undefined`] |
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
| **200** | Approval decision recorded. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createApprovalDelegation

> ApiSuccessResponse createApprovalDelegation(requestBody)

Create approval delegation

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { CreateApprovalDelegationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies CreateApprovalDelegationRequest;

  try {
    const data = await api.createApprovalDelegation(body);
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
| **201** | Approval delegation created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createApprovalRule

> ApiSuccessResponse createApprovalRule(requestBody)

Create approval rule

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { CreateApprovalRuleRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies CreateApprovalRuleRequest;

  try {
    const data = await api.createApprovalRule(body);
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
| **201** | Approval rule created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteApprovalDelegation

> ApiSuccessResponse deleteApprovalDelegation(delegationId)

Delete approval delegation

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { DeleteApprovalDelegationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    delegationId: 56,
  } satisfies DeleteApprovalDelegationRequest;

  try {
    const data = await api.deleteApprovalDelegation(body);
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
| **delegationId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Approval delegation removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteApprovalRule

> ApiSuccessResponse deleteApprovalRule(ruleId)

Delete approval rule

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { DeleteApprovalRuleRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    ruleId: 56,
  } satisfies DeleteApprovalRuleRequest;

  try {
    const data = await api.deleteApprovalRule(body);
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
| **ruleId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Approval rule removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listApprovalDelegations

> ApiSuccessResponse listApprovalDelegations()

List approval delegations

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ListApprovalDelegationsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  try {
    const data = await api.listApprovalDelegations();
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
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Active approval delegation rules. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listApprovalRequests

> ApiSuccessResponse listApprovalRequests()

List approval requests

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ListApprovalRequestsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  try {
    const data = await api.listApprovalRequests();
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
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Pending approval requests. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listApprovalRules

> ApiSuccessResponse listApprovalRules()

List approval rules

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ListApprovalRulesRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  try {
    const data = await api.listApprovalRules();
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
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Approval rules configured for the tenant. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showApprovalRequest

> ApiSuccessResponse showApprovalRequest(approvalId)

Show approval request

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ShowApprovalRequestRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    approvalId: 56,
  } satisfies ShowApprovalRequestRequest;

  try {
    const data = await api.showApprovalRequest(body);
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
| **approvalId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Approval request detail. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showApprovalRule

> ApiSuccessResponse showApprovalRule(ruleId)

Show approval rule

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { ShowApprovalRuleRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    ruleId: 56,
  } satisfies ShowApprovalRuleRequest;

  try {
    const data = await api.showApprovalRule(body);
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
| **ruleId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Approval rule detail. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateApprovalDelegation

> ApiSuccessResponse updateApprovalDelegation(delegationId, requestBody)

Update approval delegation

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { UpdateApprovalDelegationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    delegationId: 56,
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies UpdateApprovalDelegationRequest;

  try {
    const data = await api.updateApprovalDelegation(body);
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
| **delegationId** | `number` |  | [Defaults to `undefined`] |
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
| **200** | Approval delegation updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateApprovalRule

> ApiSuccessResponse updateApprovalRule(ruleId, requestBody)

Update approval rule

### Example

```ts
import {
  Configuration,
  ApprovalsApi,
} from '';
import type { UpdateApprovalRuleRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new ApprovalsApi(config);

  const body = {
    // number
    ruleId: 56,
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies UpdateApprovalRuleRequest;

  try {
    const data = await api.updateApprovalRule(body);
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
| **ruleId** | `number` |  | [Defaults to `undefined`] |
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
| **200** | Approval rule updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

