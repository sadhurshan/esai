# CompaniesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**createCompanyDocument**](CompaniesApi.md#createcompanydocument) | **POST** /api/companies/{companyId}/documents | Upload company document |
| [**createInvitation**](CompaniesApi.md#createinvitation) | **POST** /api/invitations | Invite user to company |
| [**deleteCompanyDocument**](CompaniesApi.md#deletecompanydocument) | **DELETE** /api/companies/{companyId}/documents/{documentId} | Delete company document |
| [**deleteInvitation**](CompaniesApi.md#deleteinvitation) | **DELETE** /api/invitations/{token} | Revoke invitation by token |
| [**listCompanyDocuments**](CompaniesApi.md#listcompanydocuments) | **GET** /api/companies/{companyId}/documents | List company documents |
| [**listInvitations**](CompaniesApi.md#listinvitations) | **GET** /api/invitations | List pending invitations for current company |
| [**listPlansCatalog**](CompaniesApi.md#listplanscatalog) | **GET** /api/plans | List publicly available subscription plans |
| [**registerCompany**](CompaniesApi.md#registercompanyoperation) | **POST** /api/companies | Register company and request onboarding |
| [**selectCompanyPlan**](CompaniesApi.md#selectcompanyplanoperation) | **POST** /api/company/plan-selection | Select a subscription plan for the authenticated company |
| [**showCompany**](CompaniesApi.md#showcompany) | **GET** /api/companies/{companyId} | Retrieve company profile |
| [**showCurrentCompany**](CompaniesApi.md#showcurrentcompany) | **GET** /api/companies/current | Retrieve profile for authenticated company |
| [**showInvitation**](CompaniesApi.md#showinvitation) | **GET** /api/invitations/{token} | Show invitation by token |
| [**updateCompany**](CompaniesApi.md#updatecompanyoperation) | **PUT** /api/companies/{companyId} | Update company profile |
| [**updateCurrentCompany**](CompaniesApi.md#updatecurrentcompany) | **PATCH** /api/companies/current | Update profile for authenticated company |



## createCompanyDocument

> ApiSuccessResponse createCompanyDocument(companyId, file, label)

Upload company document

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { CreateCompanyDocumentRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // number
    companyId: 56,
    // Blob
    file: BINARY_DATA_HERE,
    // string (optional)
    label: label_example,
  } satisfies CreateCompanyDocumentRequest;

  try {
    const data = await api.createCompanyDocument(body);
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
| **file** | `Blob` |  | [Defaults to `undefined`] |
| **label** | `string` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Company document stored. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createInvitation

> ApiSuccessResponse createInvitation(requestBody)

Invite user to company

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { CreateInvitationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies CreateInvitationRequest;

  try {
    const data = await api.createInvitation(body);
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
| **201** | Invitation created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteCompanyDocument

> ApiSuccessResponse deleteCompanyDocument(companyId, documentId)

Delete company document

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { DeleteCompanyDocumentRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // number
    companyId: 56,
    // number
    documentId: 56,
  } satisfies DeleteCompanyDocumentRequest;

  try {
    const data = await api.deleteCompanyDocument(body);
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
| **documentId** | `number` |  | [Defaults to `undefined`] |

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
| **200** | Company document removed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteInvitation

> ApiSuccessResponse deleteInvitation(token)

Revoke invitation by token

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { DeleteInvitationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // string
    token: token_example,
  } satisfies DeleteInvitationRequest;

  try {
    const data = await api.deleteInvitation(body);
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
| **token** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Invitation revoked. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listCompanyDocuments

> ApiSuccessResponse listCompanyDocuments(companyId)

List company documents

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ListCompanyDocumentsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // number
    companyId: 56,
  } satisfies ListCompanyDocumentsRequest;

  try {
    const data = await api.listCompanyDocuments(body);
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

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Documents associated with the company. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listInvitations

> ApiSuccessResponse listInvitations()

List pending invitations for current company

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ListInvitationsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  try {
    const data = await api.listInvitations();
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
| **200** | Invitations collection. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listPlansCatalog

> ListPlansCatalog200Response listPlansCatalog()

List publicly available subscription plans

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ListPlansCatalogRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  try {
    const data = await api.listPlansCatalog();
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

[**ListPlansCatalog200Response**](ListPlansCatalog200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Catalog of buyer subscription plans. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## registerCompany

> RegisterCompany201Response registerCompany(registerCompanyRequest)

Register company and request onboarding

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { RegisterCompanyOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // RegisterCompanyRequest
    registerCompanyRequest: ...,
  } satisfies RegisterCompanyOperationRequest;

  try {
    const data = await api.registerCompany(body);
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
| **registerCompanyRequest** | [RegisterCompanyRequest](RegisterCompanyRequest.md) |  | |

### Return type

[**RegisterCompany201Response**](RegisterCompany201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | Company onboarding request accepted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## selectCompanyPlan

> SelectCompanyPlan200Response selectCompanyPlan(selectCompanyPlanRequest)

Select a subscription plan for the authenticated company

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { SelectCompanyPlanOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // SelectCompanyPlanRequest
    selectCompanyPlanRequest: ...,
  } satisfies SelectCompanyPlanOperationRequest;

  try {
    const data = await api.selectCompanyPlan(body);
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
| **selectCompanyPlanRequest** | [SelectCompanyPlanRequest](SelectCompanyPlanRequest.md) |  | |

### Return type

[**SelectCompanyPlan200Response**](SelectCompanyPlan200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Plan selection saved for the tenant. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showCompany

> RegisterCompany201Response showCompany(companyId)

Retrieve company profile

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ShowCompanyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // number
    companyId: 56,
  } satisfies ShowCompanyRequest;

  try {
    const data = await api.showCompany(body);
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

[**RegisterCompany201Response**](RegisterCompany201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Company profile visible to the authenticated tenant. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showCurrentCompany

> RegisterCompany201Response showCurrentCompany()

Retrieve profile for authenticated company

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ShowCurrentCompanyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  try {
    const data = await api.showCurrentCompany();
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

[**RegisterCompany201Response**](RegisterCompany201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current tenant company profile. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showInvitation

> ApiSuccessResponse showInvitation(token)

Show invitation by token

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { ShowInvitationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // string
    token: token_example,
  } satisfies ShowInvitationRequest;

  try {
    const data = await api.showInvitation(body);
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
| **token** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Invitation details. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateCompany

> ApiSuccessResponse updateCompany(companyId, updateCompanyRequest)

Update company profile

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { UpdateCompanyOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // number
    companyId: 56,
    // UpdateCompanyRequest
    updateCompanyRequest: ...,
  } satisfies UpdateCompanyOperationRequest;

  try {
    const data = await api.updateCompany(body);
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
| **updateCompanyRequest** | [UpdateCompanyRequest](UpdateCompanyRequest.md) |  | |

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
| **200** | Company profile updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateCurrentCompany

> ApiSuccessResponse updateCurrentCompany(requestBody)

Update profile for authenticated company

### Example

```ts
import {
  Configuration,
  CompaniesApi,
} from '';
import type { UpdateCurrentCompanyRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new CompaniesApi(config);

  const body = {
    // { [key: string]: any; }
    requestBody: Object,
  } satisfies UpdateCurrentCompanyRequest;

  try {
    const data = await api.updateCurrentCompany(body);
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
| **200** | Current company updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

