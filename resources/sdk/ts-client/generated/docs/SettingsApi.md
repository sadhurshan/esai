# SettingsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**showCompanySettings**](SettingsApi.md#showcompanysettings) | **GET** /api/settings/company | Retrieve company profile settings |
| [**showLocalizationSettings**](SettingsApi.md#showlocalizationsettings) | **GET** /api/settings/localization | Retrieve localization settings |
| [**showNumberingSettings**](SettingsApi.md#shownumberingsettings) | **GET** /api/settings/numbering | Retrieve document numbering rules |
| [**updateCompanySettings**](SettingsApi.md#updatecompanysettings) | **PATCH** /api/settings/company | Update company profile settings |
| [**updateLocalizationSettings**](SettingsApi.md#updatelocalizationsettings) | **PATCH** /api/settings/localization | Update localization settings |
| [**updateNumberingSettings**](SettingsApi.md#updatenumberingsettings) | **PATCH** /api/settings/numbering | Update document numbering rules |



## showCompanySettings

> ShowCompanySettings200Response showCompanySettings()

Retrieve company profile settings

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { ShowCompanySettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  try {
    const data = await api.showCompanySettings();
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

[**ShowCompanySettings200Response**](ShowCompanySettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current company profile settings. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showLocalizationSettings

> ShowLocalizationSettings200Response showLocalizationSettings()

Retrieve localization settings

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { ShowLocalizationSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  try {
    const data = await api.showLocalizationSettings();
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

[**ShowLocalizationSettings200Response**](ShowLocalizationSettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current localization preferences for the tenant. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showNumberingSettings

> ShowNumberingSettings200Response showNumberingSettings()

Retrieve document numbering rules

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { ShowNumberingSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  try {
    const data = await api.showNumberingSettings();
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

[**ShowNumberingSettings200Response**](ShowNumberingSettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current numbering configuration for all document types. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateCompanySettings

> ShowCompanySettings200Response updateCompanySettings(companySettings)

Update company profile settings

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { UpdateCompanySettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  const body = {
    // CompanySettings
    companySettings: ...,
  } satisfies UpdateCompanySettingsRequest;

  try {
    const data = await api.updateCompanySettings(body);
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
| **companySettings** | [CompanySettings](CompanySettings.md) |  | |

### Return type

[**ShowCompanySettings200Response**](ShowCompanySettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Updated company profile settings. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateLocalizationSettings

> ShowLocalizationSettings200Response updateLocalizationSettings(localizationSettings)

Update localization settings

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { UpdateLocalizationSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  const body = {
    // LocalizationSettings
    localizationSettings: ...,
  } satisfies UpdateLocalizationSettingsRequest;

  try {
    const data = await api.updateLocalizationSettings(body);
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
| **localizationSettings** | [LocalizationSettings](LocalizationSettings.md) |  | |

### Return type

[**ShowLocalizationSettings200Response**](ShowLocalizationSettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Updated localization settings. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateNumberingSettings

> ShowNumberingSettings200Response updateNumberingSettings(numberingSettings)

Update document numbering rules

### Example

```ts
import {
  Configuration,
  SettingsApi,
} from '';
import type { UpdateNumberingSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SettingsApi(config);

  const body = {
    // NumberingSettings
    numberingSettings: ...,
  } satisfies UpdateNumberingSettingsRequest;

  try {
    const data = await api.updateNumberingSettings(body);
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
| **numberingSettings** | [NumberingSettings](NumberingSettings.md) |  | |

### Return type

[**ShowNumberingSettings200Response**](ShowNumberingSettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Updated numbering configuration. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

