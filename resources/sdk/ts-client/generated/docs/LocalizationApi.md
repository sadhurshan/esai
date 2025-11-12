# LocalizationApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**convertQuantity**](LocalizationApi.md#convertquantityoperation) | **POST** /api/localization/uom/convert | Convert quantity between units |
| [**convertQuantityForPart**](LocalizationApi.md#convertquantityforpart) | **GET** /api/localization/parts/{partId}/convert | Convert quantity using part base unit |
| [**createUom**](LocalizationApi.md#createuom) | **POST** /api/localization/uoms | Create unit of measure |
| [**deleteUom**](LocalizationApi.md#deleteuom) | **DELETE** /api/localization/uoms/{uomCode} | Delete unit of measure |
| [**listUomConversions**](LocalizationApi.md#listuomconversions) | **GET** /api/localization/uoms/conversions | List unit conversions |
| [**listUoms**](LocalizationApi.md#listuoms) | **GET** /api/localization/uoms | List units of measure |
| [**showLocaleSettings**](LocalizationApi.md#showlocalesettings) | **GET** /api/localization/settings | Retrieve locale settings |
| [**updateLocaleSettings**](LocalizationApi.md#updatelocalesettings) | **PUT** /api/localization/settings | Update locale settings |
| [**updateUom**](LocalizationApi.md#updateuom) | **PUT** /api/localization/uoms/{uomCode} | Update unit of measure |
| [**upsertUomConversion**](LocalizationApi.md#upsertuomconversionoperation) | **POST** /api/localization/uoms/conversions | Upsert unit conversion |



## convertQuantity

> ConvertQuantity200Response convertQuantity(convertQuantityRequest)

Convert quantity between units

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { ConvertQuantityOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // ConvertQuantityRequest
    convertQuantityRequest: ...,
  } satisfies ConvertQuantityOperationRequest;

  try {
    const data = await api.convertQuantity(body);
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
| **convertQuantityRequest** | [ConvertQuantityRequest](ConvertQuantityRequest.md) |  | |

### Return type

[**ConvertQuantity200Response**](ConvertQuantity200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Converted quantity. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## convertQuantityForPart

> ConvertQuantityForPart200Response convertQuantityForPart(partId, qty, from, to)

Convert quantity using part base unit

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { ConvertQuantityForPartRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // string
    partId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // number
    qty: 8.14,
    // string
    from: from_example,
    // string
    to: to_example,
  } satisfies ConvertQuantityForPartRequest;

  try {
    const data = await api.convertQuantityForPart(body);
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
| **partId** | `string` |  | [Defaults to `undefined`] |
| **qty** | `number` |  | [Defaults to `undefined`] |
| **from** | `string` |  | [Defaults to `undefined`] |
| **to** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ConvertQuantityForPart200Response**](ConvertQuantityForPart200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Conversion result for part. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createUom

> ApiSuccessResponse createUom(uom)

Create unit of measure

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { CreateUomRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // Uom
    uom: ...,
  } satisfies CreateUomRequest;

  try {
    const data = await api.createUom(body);
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
| **uom** | [Uom](Uom.md) |  | |

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
| **200** | Unit created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteUom

> ApiSuccessResponse deleteUom(uomCode)

Delete unit of measure

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { DeleteUomRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // string
    uomCode: uomCode_example,
  } satisfies DeleteUomRequest;

  try {
    const data = await api.deleteUom(body);
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
| **uomCode** | `string` |  | [Defaults to `undefined`] |

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
| **200** | Unit deleted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listUomConversions

> ListUomConversions200Response listUomConversions(fromCode, toCode, dimension)

List unit conversions

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { ListUomConversionsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // string (optional)
    fromCode: fromCode_example,
    // string (optional)
    toCode: toCode_example,
    // string (optional)
    dimension: dimension_example,
  } satisfies ListUomConversionsRequest;

  try {
    const data = await api.listUomConversions(body);
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
| **fromCode** | `string` |  | [Optional] [Defaults to `undefined`] |
| **toCode** | `string` |  | [Optional] [Defaults to `undefined`] |
| **dimension** | `string` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListUomConversions200Response**](ListUomConversions200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Mapping of conversions. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listUoms

> ListUoms200Response listUoms(dimension)

List units of measure

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { ListUomsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // string (optional)
    dimension: dimension_example,
  } satisfies ListUomsRequest;

  try {
    const data = await api.listUoms(body);
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
| **dimension** | `string` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**ListUoms200Response**](ListUoms200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Available units of measure. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showLocaleSettings

> ShowLocaleSettings200Response showLocaleSettings()

Retrieve locale settings

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { ShowLocaleSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  try {
    const data = await api.showLocaleSettings();
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

[**ShowLocaleSettings200Response**](ShowLocaleSettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Current locale configuration for the company. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateLocaleSettings

> ApiSuccessResponse updateLocaleSettings(localeSetting)

Update locale settings

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { UpdateLocaleSettingsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // LocaleSetting
    localeSetting: ...,
  } satisfies UpdateLocaleSettingsRequest;

  try {
    const data = await api.updateLocaleSettings(body);
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
| **localeSetting** | [LocaleSetting](LocaleSetting.md) |  | |

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
| **200** | Locale settings updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateUom

> ApiSuccessResponse updateUom(uomCode, uom)

Update unit of measure

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { UpdateUomRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // string
    uomCode: uomCode_example,
    // Uom
    uom: ...,
  } satisfies UpdateUomRequest;

  try {
    const data = await api.updateUom(body);
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
| **uomCode** | `string` |  | [Defaults to `undefined`] |
| **uom** | [Uom](Uom.md) |  | |

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
| **200** | Unit updated. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## upsertUomConversion

> ApiSuccessResponse upsertUomConversion(upsertUomConversionRequest)

Upsert unit conversion

### Example

```ts
import {
  Configuration,
  LocalizationApi,
} from '';
import type { UpsertUomConversionOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new LocalizationApi(config);

  const body = {
    // UpsertUomConversionRequest
    upsertUomConversionRequest: ...,
  } satisfies UpsertUomConversionOperationRequest;

  try {
    const data = await api.upsertUomConversion(body);
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
| **upsertUomConversionRequest** | [UpsertUomConversionRequest](UpsertUomConversionRequest.md) |  | |

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
| **200** | Conversion saved. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

