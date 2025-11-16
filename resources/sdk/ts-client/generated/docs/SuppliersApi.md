# SuppliersApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**createSupplierApplication**](SuppliersApi.md#createsupplierapplication) | **POST** /api/supplier-applications | Submit supplier application |
| [**createSupplierEsg**](SuppliersApi.md#createsupplieresg) | **POST** /api/suppliers/{supplierId}/esg | Store ESG record for supplier |
| [**deleteSupplierApplication**](SuppliersApi.md#deletesupplierapplication) | **DELETE** /api/supplier-applications/{applicationId} | Delete supplier application |
| [**deleteSupplierEsg**](SuppliersApi.md#deletesupplieresg) | **DELETE** /api/suppliers/{supplierId}/esg/{recordId} | Delete ESG record |
| [**exportSupplierEsg**](SuppliersApi.md#exportsupplieresg) | **POST** /api/suppliers/{supplierId}/esg/export | Queue ESG export for supplier |
| [**listSupplierApplications**](SuppliersApi.md#listsupplierapplications) | **GET** /api/supplier-applications | List supplier applications |
| [**listSupplierEsg**](SuppliersApi.md#listsupplieresg) | **GET** /api/suppliers/{supplierId}/esg | List ESG records for supplier |
| [**listSuppliers**](SuppliersApi.md#listsuppliers) | **GET** /api/suppliers | List suppliers for current company |
| [**selfApplySupplierApplication**](SuppliersApi.md#selfapplysupplierapplication) | **POST** /api/me/apply-supplier | Submit a supplier application for the authenticated company |
| [**showSelfServiceSupplierApplicationStatus**](SuppliersApi.md#showselfservicesupplierapplicationstatus) | **GET** /api/me/supplier-application/status | Get supplier self-service application status |
| [**showSupplier**](SuppliersApi.md#showsupplier) | **GET** /api/suppliers/{supplierId} | Show supplier |
| [**showSupplierApplication**](SuppliersApi.md#showsupplierapplication) | **GET** /api/supplier-applications/{applicationId} | Show supplier application |
| [**updateSupplierEsg**](SuppliersApi.md#updatesupplieresg) | **PUT** /api/suppliers/{supplierId}/esg/{recordId} | Update ESG record |
| [**updateSupplierVisibility**](SuppliersApi.md#updatesuppliervisibility) | **PUT** /api/me/supplier/visibility | Update supplier directory visibility |



## createSupplierApplication

> SelfApplySupplierApplication200Response createSupplierApplication(supplierApplicationPayload)

Submit supplier application

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { CreateSupplierApplicationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // SupplierApplicationPayload
    supplierApplicationPayload: ...,
  } satisfies CreateSupplierApplicationRequest;

  try {
    const data = await api.createSupplierApplication(body);
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
| **supplierApplicationPayload** | [SupplierApplicationPayload](SupplierApplicationPayload.md) |  | |

### Return type

[**SelfApplySupplierApplication200Response**](SelfApplySupplierApplication200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Supplier application submitted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createSupplierEsg

> ApiSuccessResponse createSupplierEsg(supplierId, requestBody)

Store ESG record for supplier

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { CreateSupplierEsgRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies CreateSupplierEsgRequest;

  try {
    const data = await api.createSupplierEsg(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |
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
| **201** | ESG record created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteSupplierApplication

> ApiSuccessResponse deleteSupplierApplication(applicationId)

Delete supplier application

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { DeleteSupplierApplicationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    applicationId: 56,
  } satisfies DeleteSupplierApplicationRequest;

  try {
    const data = await api.deleteSupplierApplication(body);
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
| **applicationId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Supplier application deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteSupplierEsg

> ApiSuccessResponse deleteSupplierEsg(supplierId, recordId)

Delete ESG record

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { DeleteSupplierEsgRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
    // number
    recordId: 56,
  } satisfies DeleteSupplierEsgRequest;

  try {
    const data = await api.deleteSupplierEsg(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |
| **recordId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | ESG record deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## exportSupplierEsg

> ApiSuccessResponse exportSupplierEsg(supplierId)

Queue ESG export for supplier

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ExportSupplierEsgRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
  } satisfies ExportSupplierEsgRequest;

  try {
    const data = await api.exportSupplierEsg(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |

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
| **202** | ESG export queued. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listSupplierApplications

> ListSupplierApplications200Response listSupplierApplications()

List supplier applications

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ListSupplierApplicationsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  try {
    const data = await api.listSupplierApplications();
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

[**ListSupplierApplications200Response**](ListSupplierApplications200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated supplier applications. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listSupplierEsg

> ApiSuccessResponse listSupplierEsg(supplierId)

List ESG records for supplier

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ListSupplierEsgRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
  } satisfies ListSupplierEsgRequest;

  try {
    const data = await api.listSupplierEsg(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Environmental, social, and governance records for the supplier. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listSuppliers

> ListSuppliers200Response listSuppliers()

List suppliers for current company

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ListSuppliersRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  try {
    const data = await api.listSuppliers();
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

[**ListSuppliers200Response**](ListSuppliers200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Paginated suppliers. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## selfApplySupplierApplication

> SelfApplySupplierApplication200Response selfApplySupplierApplication(supplierApplicationPayload)

Submit a supplier application for the authenticated company

Allows the current company owner to self-apply to the supplier directory using the same payload as internal submissions.

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { SelfApplySupplierApplicationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // SupplierApplicationPayload
    supplierApplicationPayload: ...,
  } satisfies SelfApplySupplierApplicationRequest;

  try {
    const data = await api.selfApplySupplierApplication(body);
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
| **supplierApplicationPayload** | [SupplierApplicationPayload](SupplierApplicationPayload.md) |  | |

### Return type

[**SelfApplySupplierApplication200Response**](SelfApplySupplierApplication200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Supplier application submitted for review. |  -  |
| **403** | Missing permissions or company context. |  -  |
| **422** | Payload failed validation or a pending application already exists. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showSelfServiceSupplierApplicationStatus

> ApiSuccessResponse showSelfServiceSupplierApplicationStatus()

Get supplier self-service application status

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ShowSelfServiceSupplierApplicationStatusRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  try {
    const data = await api.showSelfServiceSupplierApplicationStatus();
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
| **200** | Current status for the authenticated user\&#39;s company application. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showSupplier

> ShowSupplier200Response showSupplier(supplierId)

Show supplier

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ShowSupplierRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
  } satisfies ShowSupplierRequest;

  try {
    const data = await api.showSupplier(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**ShowSupplier200Response**](ShowSupplier200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Supplier details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showSupplierApplication

> SelfApplySupplierApplication200Response showSupplierApplication(applicationId)

Show supplier application

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { ShowSupplierApplicationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    applicationId: 56,
  } satisfies ShowSupplierApplicationRequest;

  try {
    const data = await api.showSupplierApplication(body);
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
| **applicationId** | `number` |  | [Defaults to `undefined`] |

### Return type

[**SelfApplySupplierApplication200Response**](SelfApplySupplierApplication200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Supplier application details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateSupplierEsg

> ApiSuccessResponse updateSupplierEsg(supplierId, recordId, requestBody)

Update ESG record

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { UpdateSupplierEsgRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // number
    supplierId: 56,
    // number
    recordId: 56,
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies UpdateSupplierEsgRequest;

  try {
    const data = await api.updateSupplierEsg(body);
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
| **supplierId** | `number` |  | [Defaults to `undefined`] |
| **recordId** | `number` |  | [Defaults to `undefined`] |
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
| **200** | ESG record updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateSupplierVisibility

> ApiSuccessResponse updateSupplierVisibility(requestBody)

Update supplier directory visibility

### Example

```ts
import {
  Configuration,
  SuppliersApi,
} from '';
import type { UpdateSupplierVisibilityRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SuppliersApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies UpdateSupplierVisibilityRequest;

  try {
    const data = await api.updateSupplierVisibility(body);
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
| **200** | Visibility updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

