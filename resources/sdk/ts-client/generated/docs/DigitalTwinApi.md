# DigitalTwinApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                                             | HTTP request                                                                  | Description                              |
| ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- | ---------------------------------------- |
| [**completeDigitalTwinProcedure**](DigitalTwinApi.md#completedigitaltwinprocedure) | **POST** /api/digital-twin/assets/{assetId}/procedures/{procedureId}/complete | Complete maintenance procedure for asset |
| [**createDigitalTwinAsset**](DigitalTwinApi.md#createdigitaltwinasset)             | **POST** /api/digital-twin/assets                                             | Create digital twin asset                |
| [**createDigitalTwinLocation**](DigitalTwinApi.md#createdigitaltwinlocation)       | **POST** /api/digital-twin/locations                                          | Create digital twin location             |
| [**createDigitalTwinProcedure**](DigitalTwinApi.md#createdigitaltwinprocedure)     | **POST** /api/digital-twin/procedures                                         | Create maintenance procedure             |
| [**createDigitalTwinSystem**](DigitalTwinApi.md#createdigitaltwinsystem)           | **POST** /api/digital-twin/systems                                            | Create digital twin system               |
| [**createDigitalTwinWorkOrder**](DigitalTwinApi.md#createdigitaltwinworkorder)     | **POST** /api/digital-twin/assets/{assetId}/work-orders                       | Create work order for asset              |
| [**deleteDigitalTwinAsset**](DigitalTwinApi.md#deletedigitaltwinasset)             | **DELETE** /api/digital-twin/assets/{assetId}                                 | Delete digital twin asset                |
| [**deleteDigitalTwinLocation**](DigitalTwinApi.md#deletedigitaltwinlocation)       | **DELETE** /api/digital-twin/locations/{locationId}                           | Delete digital twin location             |
| [**deleteDigitalTwinProcedure**](DigitalTwinApi.md#deletedigitaltwinprocedure)     | **DELETE** /api/digital-twin/procedures/{procedureId}                         | Delete maintenance procedure             |
| [**deleteDigitalTwinSystem**](DigitalTwinApi.md#deletedigitaltwinsystem)           | **DELETE** /api/digital-twin/systems/{systemId}                               | Delete digital twin system               |
| [**detachDigitalTwinProcedure**](DigitalTwinApi.md#detachdigitaltwinprocedure)     | **DELETE** /api/digital-twin/assets/{assetId}/procedures/{procedureId}        | Detach maintenance procedure from asset  |
| [**linkDigitalTwinProcedure**](DigitalTwinApi.md#linkdigitaltwinprocedure)         | **PUT** /api/digital-twin/assets/{assetId}/procedures/{procedureId}           | Link maintenance procedure to asset      |
| [**listDigitalTwinAssets**](DigitalTwinApi.md#listdigitaltwinassets)               | **GET** /api/digital-twin/assets                                              | List registered digital twin assets      |
| [**listDigitalTwinLocations**](DigitalTwinApi.md#listdigitaltwinlocations)         | **GET** /api/digital-twin/locations                                           | List digital twin locations              |
| [**listDigitalTwinProcedures**](DigitalTwinApi.md#listdigitaltwinprocedures)       | **GET** /api/digital-twin/procedures                                          | List maintenance procedures              |
| [**listDigitalTwinSystems**](DigitalTwinApi.md#listdigitaltwinsystems)             | **GET** /api/digital-twin/systems                                             | List digital twin systems                |
| [**patchDigitalTwinAsset**](DigitalTwinApi.md#patchdigitaltwinasset)               | **PATCH** /api/digital-twin/assets/{assetId}                                  | Partially update digital twin asset      |
| [**patchDigitalTwinLocation**](DigitalTwinApi.md#patchdigitaltwinlocation)         | **PATCH** /api/digital-twin/locations/{locationId}                            | Partially update digital twin location   |
| [**patchDigitalTwinProcedure**](DigitalTwinApi.md#patchdigitaltwinprocedure)       | **PATCH** /api/digital-twin/procedures/{procedureId}                          | Partially update maintenance procedure   |
| [**patchDigitalTwinSystem**](DigitalTwinApi.md#patchdigitaltwinsystem)             | **PATCH** /api/digital-twin/systems/{systemId}                                | Partially update digital twin system     |
| [**showDigitalTwinAsset**](DigitalTwinApi.md#showdigitaltwinasset)                 | **GET** /api/digital-twin/assets/{assetId}                                    | Show digital twin asset                  |
| [**showDigitalTwinLocation**](DigitalTwinApi.md#showdigitaltwinlocation)           | **GET** /api/digital-twin/locations/{locationId}                              | Show digital twin location               |
| [**showDigitalTwinProcedure**](DigitalTwinApi.md#showdigitaltwinprocedure)         | **GET** /api/digital-twin/procedures/{procedureId}                            | Show maintenance procedure               |
| [**showDigitalTwinSystem**](DigitalTwinApi.md#showdigitaltwinsystem)               | **GET** /api/digital-twin/systems/{systemId}                                  | Show digital twin system                 |
| [**syncDigitalTwinAsset**](DigitalTwinApi.md#syncdigitaltwinasset)                 | **POST** /api/digital-twin/assets/{assetId}/sync                              | Trigger asset synchronization            |
| [**syncDigitalTwinAssetBom**](DigitalTwinApi.md#syncdigitaltwinassetbom)           | **PUT** /api/digital-twin/assets/{assetId}/bom                                | Sync asset bill of materials             |
| [**updateDigitalTwinAsset**](DigitalTwinApi.md#updatedigitaltwinasset)             | **PUT** /api/digital-twin/assets/{assetId}                                    | Replace digital twin asset               |
| [**updateDigitalTwinAssetStatus**](DigitalTwinApi.md#updatedigitaltwinassetstatus) | **PATCH** /api/digital-twin/assets/{assetId}/status                           | Update asset operational status          |
| [**updateDigitalTwinLocation**](DigitalTwinApi.md#updatedigitaltwinlocation)       | **PUT** /api/digital-twin/locations/{locationId}                              | Replace digital twin location            |
| [**updateDigitalTwinProcedure**](DigitalTwinApi.md#updatedigitaltwinprocedure)     | **PUT** /api/digital-twin/procedures/{procedureId}                            | Replace maintenance procedure            |
| [**updateDigitalTwinSystem**](DigitalTwinApi.md#updatedigitaltwinsystem)           | **PUT** /api/digital-twin/systems/{systemId}                                  | Replace digital twin system              |

## completeDigitalTwinProcedure

> ApiSuccessResponse completeDigitalTwinProcedure(assetId, procedureId)

Complete maintenance procedure for asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CompleteDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // number
        procedureId: 56,
    } satisfies CompleteDigitalTwinProcedureRequest;

    try {
        const data = await api.completeDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type     | Description | Notes                     |
| --------------- | -------- | ----------- | ------------------------- |
| **assetId**     | `number` |             | [Defaults to `undefined`] |
| **procedureId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                    | Response headers |
| ----------- | ------------------------------ | ---------------- |
| **200**     | Procedure completion recorded. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createDigitalTwinAsset

> ApiSuccessResponse createDigitalTwinAsset(requestBody)

Create digital twin asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CreateDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CreateDigitalTwinAssetRequest;

    try {
        const data = await api.createDigitalTwinAsset(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes |
| --------------- | ------------------------- | ----------- | ----- |
| **requestBody** | `{ [key: string]: any; }` |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description    | Response headers |
| ----------- | -------------- | ---------------- |
| **201**     | Asset created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createDigitalTwinLocation

> ApiSuccessResponse createDigitalTwinLocation(requestBody)

Create digital twin location

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CreateDigitalTwinLocationRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CreateDigitalTwinLocationRequest;

    try {
        const data = await api.createDigitalTwinLocation(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes |
| --------------- | ------------------------- | ----------- | ----- |
| **requestBody** | `{ [key: string]: any; }` |             |       |

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
| **201**     | Location created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createDigitalTwinProcedure

> ApiSuccessResponse createDigitalTwinProcedure(requestBody)

Create maintenance procedure

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CreateDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CreateDigitalTwinProcedureRequest;

    try {
        const data = await api.createDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes |
| --------------- | ------------------------- | ----------- | ----- |
| **requestBody** | `{ [key: string]: any; }` |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description        | Response headers |
| ----------- | ------------------ | ---------------- |
| **201**     | Procedure created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createDigitalTwinSystem

> ApiSuccessResponse createDigitalTwinSystem(requestBody)

Create digital twin system

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CreateDigitalTwinSystemRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CreateDigitalTwinSystemRequest;

    try {
        const data = await api.createDigitalTwinSystem(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type                      | Description | Notes |
| --------------- | ------------------------- | ----------- | ----- |
| **requestBody** | `{ [key: string]: any; }` |             |       |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description     | Response headers |
| ----------- | --------------- | ---------------- |
| **201**     | System created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## createDigitalTwinWorkOrder

> ApiSuccessResponse createDigitalTwinWorkOrder(assetId, requestBody)

Create work order for asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { CreateDigitalTwinWorkOrderRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies CreateDigitalTwinWorkOrderRequest;

    try {
        const data = await api.createDigitalTwinWorkOrder(body);
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
| **assetId**     | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description         | Response headers |
| ----------- | ------------------- | ---------------- |
| **201**     | Work order created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteDigitalTwinAsset

> ApiSuccessResponse deleteDigitalTwinAsset(assetId)

Delete digital twin asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { DeleteDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
    } satisfies DeleteDigitalTwinAssetRequest;

    try {
        const data = await api.deleteDigitalTwinAsset(body);
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
| **assetId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description    | Response headers |
| ----------- | -------------- | ---------------- |
| **200**     | Asset deleted. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteDigitalTwinLocation

> ApiSuccessResponse deleteDigitalTwinLocation(locationId)

Delete digital twin location

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { DeleteDigitalTwinLocationRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        locationId: 56,
    } satisfies DeleteDigitalTwinLocationRequest;

    try {
        const data = await api.deleteDigitalTwinLocation(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name           | Type     | Description | Notes                     |
| -------------- | -------- | ----------- | ------------------------- |
| **locationId** | `number` |             | [Defaults to `undefined`] |

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
| **200**     | Location deleted. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteDigitalTwinProcedure

> ApiSuccessResponse deleteDigitalTwinProcedure(procedureId)

Delete maintenance procedure

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { DeleteDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        procedureId: 56,
    } satisfies DeleteDigitalTwinProcedureRequest;

    try {
        const data = await api.deleteDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type     | Description | Notes                     |
| --------------- | -------- | ----------- | ------------------------- |
| **procedureId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description        | Response headers |
| ----------- | ------------------ | ---------------- |
| **200**     | Procedure deleted. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteDigitalTwinSystem

> ApiSuccessResponse deleteDigitalTwinSystem(systemId)

Delete digital twin system

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { DeleteDigitalTwinSystemRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        systemId: 56,
    } satisfies DeleteDigitalTwinSystemRequest;

    try {
        const data = await api.deleteDigitalTwinSystem(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name         | Type     | Description | Notes                     |
| ------------ | -------- | ----------- | ------------------------- |
| **systemId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description     | Response headers |
| ----------- | --------------- | ---------------- |
| **200**     | System deleted. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## detachDigitalTwinProcedure

> ApiSuccessResponse detachDigitalTwinProcedure(assetId, procedureId)

Detach maintenance procedure from asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { DetachDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // number
        procedureId: 56,
    } satisfies DetachDigitalTwinProcedureRequest;

    try {
        const data = await api.detachDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type     | Description | Notes                     |
| --------------- | -------- | ----------- | ------------------------- |
| **assetId**     | `number` |             | [Defaults to `undefined`] |
| **procedureId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                    | Response headers |
| ----------- | ------------------------------ | ---------------- |
| **200**     | Procedure detached from asset. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## linkDigitalTwinProcedure

> ApiSuccessResponse linkDigitalTwinProcedure(assetId, procedureId)

Link maintenance procedure to asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { LinkDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // number
        procedureId: 56,
    } satisfies LinkDigitalTwinProcedureRequest;

    try {
        const data = await api.linkDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type     | Description | Notes                     |
| --------------- | -------- | ----------- | ------------------------- |
| **assetId**     | `number` |             | [Defaults to `undefined`] |
| **procedureId** | `number` |             | [Defaults to `undefined`] |

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
| **200**     | Procedure linked to asset. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listDigitalTwinAssets

> ApiSuccessResponse listDigitalTwinAssets()

List registered digital twin assets

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ListDigitalTwinAssetsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    try {
        const data = await api.listDigitalTwinAssets();
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

| Status code | Description                      | Response headers |
| ----------- | -------------------------------- | ---------------- |
| **200**     | Paginated assets for the tenant. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listDigitalTwinLocations

> ApiSuccessResponse listDigitalTwinLocations()

List digital twin locations

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ListDigitalTwinLocationsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    try {
        const data = await api.listDigitalTwinLocations();
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

| Status code | Description                           | Response headers |
| ----------- | ------------------------------------- | ---------------- |
| **200**     | Locations in the digital twin module. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listDigitalTwinProcedures

> ApiSuccessResponse listDigitalTwinProcedures()

List maintenance procedures

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ListDigitalTwinProceduresRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    try {
        const data = await api.listDigitalTwinProcedures();
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

| Status code | Description                                       | Response headers |
| ----------- | ------------------------------------------------- | ---------------- |
| **200**     | Maintenance procedures configured for the tenant. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listDigitalTwinSystems

> ApiSuccessResponse listDigitalTwinSystems()

List digital twin systems

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ListDigitalTwinSystemsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    try {
        const data = await api.listDigitalTwinSystems();
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

| Status code | Description                                    | Response headers |
| ----------- | ---------------------------------------------- | ---------------- |
| **200**     | Systems registered in the digital twin module. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## patchDigitalTwinAsset

> ApiSuccessResponse patchDigitalTwinAsset(assetId, requestBody)

Partially update digital twin asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { PatchDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies PatchDigitalTwinAssetRequest;

    try {
        const data = await api.patchDigitalTwinAsset(body);
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
| **assetId**     | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description    | Response headers |
| ----------- | -------------- | ---------------- |
| **200**     | Asset updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## patchDigitalTwinLocation

> ApiSuccessResponse patchDigitalTwinLocation(locationId, requestBody)

Partially update digital twin location

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { PatchDigitalTwinLocationRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        locationId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies PatchDigitalTwinLocationRequest;

    try {
        const data = await api.patchDigitalTwinLocation(body);
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
| **locationId**  | `number`                  |             | [Defaults to `undefined`] |
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
| **200**     | Location updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## patchDigitalTwinProcedure

> ApiSuccessResponse patchDigitalTwinProcedure(procedureId, requestBody)

Partially update maintenance procedure

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { PatchDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        procedureId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies PatchDigitalTwinProcedureRequest;

    try {
        const data = await api.patchDigitalTwinProcedure(body);
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
| **procedureId** | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description        | Response headers |
| ----------- | ------------------ | ---------------- |
| **200**     | Procedure updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## patchDigitalTwinSystem

> ApiSuccessResponse patchDigitalTwinSystem(systemId, requestBody)

Partially update digital twin system

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { PatchDigitalTwinSystemRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        systemId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies PatchDigitalTwinSystemRequest;

    try {
        const data = await api.patchDigitalTwinSystem(body);
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
| **systemId**    | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description     | Response headers |
| ----------- | --------------- | ---------------- |
| **200**     | System updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showDigitalTwinAsset

> ApiSuccessResponse showDigitalTwinAsset(assetId)

Show digital twin asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ShowDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
    } satisfies ShowDigitalTwinAssetRequest;

    try {
        const data = await api.showDigitalTwinAsset(body);
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
| **assetId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                          | Response headers |
| ----------- | ------------------------------------ | ---------------- |
| **200**     | Asset details with status telemetry. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showDigitalTwinLocation

> ApiSuccessResponse showDigitalTwinLocation(locationId)

Show digital twin location

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ShowDigitalTwinLocationRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        locationId: 56,
    } satisfies ShowDigitalTwinLocationRequest;

    try {
        const data = await api.showDigitalTwinLocation(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name           | Type     | Description | Notes                     |
| -------------- | -------- | ----------- | ------------------------- |
| **locationId** | `number` |             | [Defaults to `undefined`] |

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
| **200**     | Location details. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showDigitalTwinProcedure

> ApiSuccessResponse showDigitalTwinProcedure(procedureId)

Show maintenance procedure

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ShowDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        procedureId: 56,
    } satisfies ShowDigitalTwinProcedureRequest;

    try {
        const data = await api.showDigitalTwinProcedure(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name            | Type     | Description | Notes                     |
| --------------- | -------- | ----------- | ------------------------- |
| **procedureId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description        | Response headers |
| ----------- | ------------------ | ---------------- |
| **200**     | Procedure details. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showDigitalTwinSystem

> ApiSuccessResponse showDigitalTwinSystem(systemId)

Show digital twin system

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { ShowDigitalTwinSystemRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        systemId: 56,
    } satisfies ShowDigitalTwinSystemRequest;

    try {
        const data = await api.showDigitalTwinSystem(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name         | Type     | Description | Notes                     |
| ------------ | -------- | ----------- | ------------------------- |
| **systemId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description     | Response headers |
| ----------- | --------------- | ---------------- |
| **200**     | System details. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## syncDigitalTwinAsset

> ApiSuccessResponse syncDigitalTwinAsset(assetId)

Trigger asset synchronization

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { SyncDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
    } satisfies SyncDigitalTwinAssetRequest;

    try {
        const data = await api.syncDigitalTwinAsset(body);
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
| **assetId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                 | Response headers |
| ----------- | --------------------------- | ---------------- |
| **202**     | Synchronization job queued. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## syncDigitalTwinAssetBom

> ApiSuccessResponse syncDigitalTwinAssetBom(assetId, requestBody)

Sync asset bill of materials

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { SyncDigitalTwinAssetBomRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies SyncDigitalTwinAssetBomRequest;

    try {
        const data = await api.syncDigitalTwinAssetBom(body);
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
| **assetId**     | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

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
| **200**     | Asset BOM synchronized. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateDigitalTwinAsset

> ApiSuccessResponse updateDigitalTwinAsset(assetId, requestBody)

Replace digital twin asset

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { UpdateDigitalTwinAssetRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateDigitalTwinAssetRequest;

    try {
        const data = await api.updateDigitalTwinAsset(body);
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
| **assetId**     | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description    | Response headers |
| ----------- | -------------- | ---------------- |
| **200**     | Asset updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateDigitalTwinAssetStatus

> ApiSuccessResponse updateDigitalTwinAssetStatus(assetId, requestBody)

Update asset operational status

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { UpdateDigitalTwinAssetStatusRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        assetId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateDigitalTwinAssetStatusRequest;

    try {
        const data = await api.updateDigitalTwinAssetStatus(body);
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
| **assetId**     | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description           | Response headers |
| ----------- | --------------------- | ---------------- |
| **200**     | Asset status updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateDigitalTwinLocation

> ApiSuccessResponse updateDigitalTwinLocation(locationId, requestBody)

Replace digital twin location

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { UpdateDigitalTwinLocationRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        locationId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateDigitalTwinLocationRequest;

    try {
        const data = await api.updateDigitalTwinLocation(body);
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
| **locationId**  | `number`                  |             | [Defaults to `undefined`] |
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
| **200**     | Location updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateDigitalTwinProcedure

> ApiSuccessResponse updateDigitalTwinProcedure(procedureId, requestBody)

Replace maintenance procedure

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { UpdateDigitalTwinProcedureRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        procedureId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateDigitalTwinProcedureRequest;

    try {
        const data = await api.updateDigitalTwinProcedure(body);
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
| **procedureId** | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description        | Response headers |
| ----------- | ------------------ | ---------------- |
| **200**     | Procedure updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateDigitalTwinSystem

> ApiSuccessResponse updateDigitalTwinSystem(systemId, requestBody)

Replace digital twin system

### Example

```ts
import { Configuration, DigitalTwinApi } from '';
import type { UpdateDigitalTwinSystemRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new DigitalTwinApi(config);

    const body = {
        // number
        systemId: 56,
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies UpdateDigitalTwinSystemRequest;

    try {
        const data = await api.updateDigitalTwinSystem(body);
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
| **systemId**    | `number`                  |             | [Defaults to `undefined`] |
| **requestBody** | `{ [key: string]: any; }` |             |                           |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description     | Response headers |
| ----------- | --------------- | ---------------- |
| **200**     | System updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
