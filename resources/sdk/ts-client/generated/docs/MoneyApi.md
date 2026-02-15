# MoneyApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                                 | HTTP request                                                | Description                       |
| ---------------------------------------------------------------------- | ----------------------------------------------------------- | --------------------------------- |
| [**createTaxCode**](MoneyApi.md#createtaxcodeoperation)                | **POST** /api/money/tax-codes                               | Create tax code                   |
| [**deleteTaxCode**](MoneyApi.md#deletetaxcode)                         | **DELETE** /api/money/tax-codes/{taxCodeId}                 | Delete tax code                   |
| [**listFxRates**](MoneyApi.md#listfxrates)                             | **GET** /api/money/fx                                       | List FX rates                     |
| [**listTaxCodes**](MoneyApi.md#listtaxcodes)                           | **GET** /api/money/tax-codes                                | List tax codes                    |
| [**patchTaxCode**](MoneyApi.md#patchtaxcode)                           | **PATCH** /api/money/tax-codes/{taxCodeId}                  | Partially update tax code         |
| [**recalcCreditNoteTotals**](MoneyApi.md#recalccreditnotetotals)       | **POST** /api/credit-notes/{creditNoteId}/recalculate       | Recalculate credit note totals    |
| [**recalcPurchaseOrderTotals**](MoneyApi.md#recalcpurchaseordertotals) | **POST** /api/purchase-orders/{purchaseOrderId}/recalculate | Recalculate purchase order totals |
| [**recalcQuoteTotals**](MoneyApi.md#recalcquotetotals)                 | **POST** /api/quotes/{quoteId}/recalculate                  | Recalculate quote totals          |
| [**showMoneySettings**](MoneyApi.md#showmoneysettings)                 | **GET** /api/money/settings                                 | Retrieve money settings           |
| [**showTaxCode**](MoneyApi.md#showtaxcode)                             | **GET** /api/money/tax-codes/{taxCodeId}                    | Retrieve tax code                 |
| [**updateMoneySettings**](MoneyApi.md#updatemoneysettingsoperation)    | **PUT** /api/money/settings                                 | Update money settings             |
| [**updateTaxCode**](MoneyApi.md#updatetaxcodeoperation)                | **PUT** /api/money/tax-codes/{taxCodeId}                    | Update tax code                   |
| [**upsertFxRates**](MoneyApi.md#upsertfxratesoperation)                | **POST** /api/money/fx                                      | Upsert FX rates                   |

## createTaxCode

> ApiSuccessResponse createTaxCode(createTaxCodeRequest)

Create tax code

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { CreateTaxCodeOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // CreateTaxCodeRequest
    createTaxCodeRequest: ...,
  } satisfies CreateTaxCodeOperationRequest;

  try {
    const data = await api.createTaxCode(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                     | Type                                            | Description | Notes |
| ------------------------ | ----------------------------------------------- | ----------- | ----- |
| **createTaxCodeRequest** | [CreateTaxCodeRequest](CreateTaxCodeRequest.md) |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **201**     | Tax code created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteTaxCode

> ApiSuccessResponse deleteTaxCode(taxCodeId)

Delete tax code

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { DeleteTaxCodeRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    const body = {
        // number
        taxCodeId: 56,
    } satisfies DeleteTaxCodeRequest;

    try {
        const data = await api.deleteTaxCode(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name          | Type     | Description | Notes                     |
| ------------- | -------- | ----------- | ------------------------- |
| **taxCodeId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **200**     | Tax code removed. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listFxRates

> ListFxRates200Response listFxRates(baseCode, quoteCode, asOf)

List FX rates

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { ListFxRatesRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    const body = {
        // string (optional)
        baseCode: baseCode_example,
        // string (optional)
        quoteCode: quoteCode_example,
        // Date (optional)
        asOf: 2013 - 10 - 20,
    } satisfies ListFxRatesRequest;

    try {
        const data = await api.listFxRates(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name          | Type     | Description | Notes                                |
| ------------- | -------- | ----------- | ------------------------------------ |
| **baseCode**  | `string` |             | [Optional] [Defaults to `undefined`] |
| **quoteCode** | `string` |             | [Optional] [Defaults to `undefined`] |
| **asOf**      | `Date`   |             | [Optional] [Defaults to `undefined`] |

### Return type

[**ListFxRates200Response**](ListFxRates200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description         | Response headers |
| ----------- | ------------------- | ---------------- |
| **200**     | Paginated FX rates. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listTaxCodes

> ListTaxCodes200Response listTaxCodes(cursor, search, active, type)

List tax codes

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { ListTaxCodesRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    const body = {
        // string | Cursor token for pagination. (optional)
        cursor: cursor_example,
        // string (optional)
        search: search_example,
        // boolean (optional)
        active: true,
        // string (optional)
        type: type_example,
    } satisfies ListTaxCodesRequest;

    try {
        const data = await api.listTaxCodes(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name       | Type      | Description                  | Notes                                |
| ---------- | --------- | ---------------------------- | ------------------------------------ |
| **cursor** | `string`  | Cursor token for pagination. | [Optional] [Defaults to `undefined`] |
| **search** | `string`  |                              | [Optional] [Defaults to `undefined`] |
| **active** | `boolean` |                              | [Optional] [Defaults to `undefined`] |
| **type**   | `string`  |                              | [Optional] [Defaults to `undefined`] |

### Return type

[**ListTaxCodes200Response**](ListTaxCodes200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                 | Response headers |
| ----------- | --------------------------- | ---------------- |
| **200**     | Cursor paginated tax codes. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## patchTaxCode

> ApiSuccessResponse patchTaxCode(taxCodeId, requestBody)

Partially update tax code

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { PatchTaxCodeRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    const body = {
        // number
        taxCodeId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies PatchTaxCodeRequest;

    try {
        const data = await api.patchTaxCode(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes                     |
| --------------- | ------------------------- | ----------- | ------------------------- |
| **taxCodeId**   | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **200**     | Tax code updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## recalcCreditNoteTotals

> ApiSuccessResponse recalcCreditNoteTotals(creditNoteId)

Recalculate credit note totals

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { RecalcCreditNoteTotalsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // string
    creditNoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies RecalcCreditNoteTotalsRequest;

  try {
    const data = await api.recalcCreditNoteTotals(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name             | Type     | Description | Notes                     |
| ---------------- | -------- | ----------- | ------------------------- |
| **creditNoteId** | `string` |             | [Defaults to `undefined`] |

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
| **200**     | Credit note totals recalculated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## recalcPurchaseOrderTotals

> ApiSuccessResponse recalcPurchaseOrderTotals(purchaseOrderId)

Recalculate purchase order totals

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { RecalcPurchaseOrderTotalsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // string
    purchaseOrderId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies RecalcPurchaseOrderTotalsRequest;

  try {
    const data = await api.recalcPurchaseOrderTotals(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                | Type     | Description | Notes                     |
| ------------------- | -------- | ----------- | ------------------------- |
| **purchaseOrderId** | `string` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                         | Response headers |
| ----------- | ----------------------------------- | ---------------- |
| **200**     | Purchase order totals recalculated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## recalcQuoteTotals

> ApiSuccessResponse recalcQuoteTotals(quoteId)

Recalculate quote totals

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { RecalcQuoteTotalsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // string
    quoteId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies RecalcQuoteTotalsRequest;

  try {
    const data = await api.recalcQuoteTotals(body);
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
| **quoteId** | `string` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                | Response headers |
| ----------- | -------------------------- | ---------------- |
| **200**     | Quote totals recalculated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showMoneySettings

> ShowMoneySettings200Response showMoneySettings()

Retrieve money settings

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { ShowMoneySettingsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    try {
        const data = await api.showMoneySettings();
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

[**ShowMoneySettings200Response**](ShowMoneySettings200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                     | Response headers |
| ----------- | ------------------------------- | ---------------- |
| **200**     | Money settings for the company. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showTaxCode

> ShowTaxCode200Response showTaxCode(taxCodeId)

Retrieve tax code

### Example

```ts
import { Configuration, MoneyApi } from '';
import type { ShowTaxCodeRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new MoneyApi(config);

    const body = {
        // number
        taxCodeId: 56,
    } satisfies ShowTaxCodeRequest;

    try {
        const data = await api.showTaxCode(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name          | Type     | Description | Notes                     |
| ------------- | -------- | ----------- | ------------------------- |
| **taxCodeId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ShowTaxCode200Response**](ShowTaxCode200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **200**     | Tax code details. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateMoneySettings

> ApiSuccessResponse updateMoneySettings(updateMoneySettingsRequest)

Update money settings

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { UpdateMoneySettingsOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // UpdateMoneySettingsRequest
    updateMoneySettingsRequest: ...,
  } satisfies UpdateMoneySettingsOperationRequest;

  try {
    const data = await api.updateMoneySettings(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                           | Type                                                        | Description | Notes |
| ------------------------------ | ----------------------------------------------------------- | ----------- | ----- |
| **updateMoneySettingsRequest** | [UpdateMoneySettingsRequest](UpdateMoneySettingsRequest.md) |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description             | Response headers |
| ----------- | ----------------------- | ---------------- |
| **200**     | Money settings updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateTaxCode

> ApiSuccessResponse updateTaxCode(taxCodeId, updateTaxCodeRequest)

Update tax code

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { UpdateTaxCodeOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // number
    taxCodeId: 56,
    // UpdateTaxCodeRequest
    updateTaxCodeRequest: ...,
  } satisfies UpdateTaxCodeOperationRequest;

  try {
    const data = await api.updateTaxCode(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                     | Type                                            | Description | Notes                     |
| ------------------------ | ----------------------------------------------- | ----------- | ------------------------- |
| **taxCodeId**            | `number`                                        |             | [Defaults to `undefined`] |
| **updateTaxCodeRequest** | [UpdateTaxCodeRequest](UpdateTaxCodeRequest.md) |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **200**     | Tax code updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## upsertFxRates

> ApiSuccessResponse upsertFxRates(upsertFxRatesRequest)

Upsert FX rates

### Example

```ts
import {
  Configuration,
  MoneyApi,
} from '';
import type { UpsertFxRatesOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new MoneyApi(config);

  const body = {
    // UpsertFxRatesRequest
    upsertFxRatesRequest: ...,
  } satisfies UpsertFxRatesOperationRequest;

  try {
    const data = await api.upsertFxRates(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                     | Type                                            | Description | Notes |
| ------------------------ | ----------------------------------------------- | ----------- | ----- |
| **upsertFxRatesRequest** | [UpsertFxRatesRequest](UpsertFxRatesRequest.md) |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description       | Response headers |
| ----------- | ----------------- | ---------------- |
| **200**     | FX rates updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
