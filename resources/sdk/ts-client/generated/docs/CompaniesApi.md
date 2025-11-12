# CompaniesApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**registerCompany**](CompaniesApi.md#registercompanyoperation) | **POST** /api/companies | Register company and request onboarding |
| [**showCompany**](CompaniesApi.md#showcompany) | **GET** /api/companies/{companyId} | Retrieve company profile |
| [**updateCompany**](CompaniesApi.md#updatecompanyoperation) | **PUT** /api/companies/{companyId} | Update company profile |



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

