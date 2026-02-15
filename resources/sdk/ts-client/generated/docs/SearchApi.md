# SearchApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                           | HTTP request                                   | Description                                   |
| ---------------------------------------------------------------- | ---------------------------------------------- | --------------------------------------------- |
| [**createSavedSearch**](SearchApi.md#createsavedsearchoperation) | **POST** /api/saved-searches                   | Create saved search                           |
| [**deleteSavedSearch**](SearchApi.md#deletesavedsearch)          | **DELETE** /api/saved-searches/{savedSearchId} | Delete saved search                           |
| [**listSavedSearches**](SearchApi.md#listsavedsearches)          | **GET** /api/saved-searches                    | List saved searches                           |
| [**searchGlobal**](SearchApi.md#searchglobal)                    | **GET** /api/search                            | Perform global search across tenant resources |
| [**showSavedSearch**](SearchApi.md#showsavedsearch)              | **GET** /api/saved-searches/{savedSearchId}    | Show saved search                             |
| [**updateSavedSearch**](SearchApi.md#updatesavedsearchoperation) | **PUT** /api/saved-searches/{savedSearchId}    | Update saved search                           |

## createSavedSearch

> ApiSuccessResponse createSavedSearch(createSavedSearchRequest)

Create saved search

### Example

```ts
import {
  Configuration,
  SearchApi,
} from '';
import type { CreateSavedSearchOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SearchApi(config);

  const body = {
    // CreateSavedSearchRequest
    createSavedSearchRequest: ...,
  } satisfies CreateSavedSearchOperationRequest;

  try {
    const data = await api.createSavedSearch(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                         | Type                                                    | Description | Notes |
| ---------------------------- | ------------------------------------------------------- | ----------- | ----- |
| **createSavedSearchRequest** | [CreateSavedSearchRequest](CreateSavedSearchRequest.md) |             |       |

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
| **201**     | Saved search created. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## deleteSavedSearch

> ApiSuccessResponse deleteSavedSearch(savedSearchId)

Delete saved search

### Example

```ts
import { Configuration, SearchApi } from '';
import type { DeleteSavedSearchRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new SearchApi(config);

    const body = {
        // number
        savedSearchId: 56,
    } satisfies DeleteSavedSearchRequest;

    try {
        const data = await api.deleteSavedSearch(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name              | Type     | Description | Notes                     |
| ----------------- | -------- | ----------- | ------------------------- |
| **savedSearchId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description           | Response headers |
| ----------- | --------------------- | ---------------- |
| **200**     | Saved search removed. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listSavedSearches

> ListSavedSearches200Response listSavedSearches()

List saved searches

### Example

```ts
import { Configuration, SearchApi } from '';
import type { ListSavedSearchesRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new SearchApi(config);

    try {
        const data = await api.listSavedSearches();
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

[**ListSavedSearches200Response**](ListSavedSearches200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                                | Response headers |
| ----------- | ------------------------------------------ | ---------------- |
| **200**     | Saved searches for the authenticated user. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## searchGlobal

> SearchGlobal200Response searchGlobal(q, types, dateFrom, dateTo)

Perform global search across tenant resources

### Example

```ts
import {
  Configuration,
  SearchApi,
} from '';
import type { SearchGlobalRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SearchApi(config);

  const body = {
    // string
    q: q_example,
    // Array<string> | Optional filters limiting domain (comma separated). (optional)
    types: ...,
    // Date (optional)
    dateFrom: 2013-10-20,
    // Date (optional)
    dateTo: 2013-10-20,
  } satisfies SearchGlobalRequest;

  try {
    const data = await api.searchGlobal(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name         | Type            | Description                                         | Notes                                |
| ------------ | --------------- | --------------------------------------------------- | ------------------------------------ |
| **q**        | `string`        |                                                     | [Defaults to `undefined`]            |
| **types**    | `Array<string>` | Optional filters limiting domain (comma separated). | [Optional]                           |
| **dateFrom** | `Date`          |                                                     | [Optional] [Defaults to `undefined`] |
| **dateTo**   | `Date`          |                                                     | [Optional] [Defaults to `undefined`] |

### Return type

[**SearchGlobal200Response**](SearchGlobal200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                                     | Response headers |
| ----------- | ----------------------------------------------- | ---------------- |
| **200**     | Aggregated search results ordered by relevance. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showSavedSearch

> ShowSavedSearch200Response showSavedSearch(savedSearchId)

Show saved search

### Example

```ts
import { Configuration, SearchApi } from '';
import type { ShowSavedSearchRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new SearchApi(config);

    const body = {
        // number
        savedSearchId: 56,
    } satisfies ShowSavedSearchRequest;

    try {
        const data = await api.showSavedSearch(body);
        console.log(data);
    } catch (error) {
        console.error(error);
    }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name              | Type     | Description | Notes                     |
| ----------------- | -------- | ----------- | ------------------------- |
| **savedSearchId** | `number` |             | [Defaults to `undefined`] |

### Return type

[**ShowSavedSearch200Response**](ShowSavedSearch200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description          | Response headers |
| ----------- | -------------------- | ---------------- |
| **200**     | Saved search detail. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## updateSavedSearch

> ApiSuccessResponse updateSavedSearch(savedSearchId, updateSavedSearchRequest)

Update saved search

### Example

```ts
import {
  Configuration,
  SearchApi,
} from '';
import type { UpdateSavedSearchOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new SearchApi(config);

  const body = {
    // number
    savedSearchId: 56,
    // UpdateSavedSearchRequest
    updateSavedSearchRequest: ...,
  } satisfies UpdateSavedSearchOperationRequest;

  try {
    const data = await api.updateSavedSearch(body);
    console.log(data);
  } catch (error) {
    console.error(error);
  }
}

// Run the test
example().catch(console.error);
```

### Parameters

| Name                         | Type                                                    | Description | Notes                     |
| ---------------------------- | ------------------------------------------------------- | ----------- | ------------------------- |
| **savedSearchId**            | `number`                                                |             | [Defaults to `undefined`] |
| **updateSavedSearchRequest** | [UpdateSavedSearchRequest](UpdateSavedSearchRequest.md) |             |                           |

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
| **200**     | Saved search updated. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
