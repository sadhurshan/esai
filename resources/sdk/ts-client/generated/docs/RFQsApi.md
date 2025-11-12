# RFQsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**awardRfq**](RFQsApi.md#awardrfqoperation) | **POST** /api/rfqs/{rfqId}/award | Award RFQ |
| [**awardRfqLines**](RFQsApi.md#awardrfqlines) | **POST** /api/rfqs/{rfqId}/award-lines | Award specific RFQ lines |
| [**createRfq**](RFQsApi.md#createrfq) | **POST** /api/rfqs | Create RFQ |
| [**createRfqAmendment**](RFQsApi.md#createrfqamendmentoperation) | **POST** /api/rfqs/{rfqId}/clarifications/amendment | Publish amendment |
| [**createRfqClarificationAnswer**](RFQsApi.md#createrfqclarificationanswer) | **POST** /api/rfqs/{rfqId}/clarifications/answer | Submit clarification answer |
| [**createRfqClarificationQuestion**](RFQsApi.md#createrfqclarificationquestion) | **POST** /api/rfqs/{rfqId}/clarifications/question | Submit clarification question |
| [**deleteRfq**](RFQsApi.md#deleterfq) | **DELETE** /api/rfqs/{rfqId} | Delete RFQ |
| [**inviteSupplierToRfq**](RFQsApi.md#invitesuppliertorfqoperation) | **POST** /api/rfqs/{rfqId}/invitations | Invite supplier to RFQ |
| [**listRfqClarifications**](RFQsApi.md#listrfqclarifications) | **GET** /api/rfqs/{rfqId}/clarifications | List clarifications |
| [**listRfqInvitations**](RFQsApi.md#listrfqinvitations) | **GET** /api/rfqs/{rfqId}/invitations | List RFQ invitations |
| [**listRfqs**](RFQsApi.md#listrfqs) | **GET** /api/rfqs | List RFQs |
| [**showRfq**](RFQsApi.md#showrfq) | **GET** /api/rfqs/{rfqId} | Retrieve RFQ |
| [**updateRfq**](RFQsApi.md#updaterfq) | **PUT** /api/rfqs/{rfqId} | Update RFQ |



## awardRfq

> ApiSuccessResponse awardRfq(rfqId, awardRfqRequest)

Award RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { AwardRfqOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // AwardRfqRequest
    awardRfqRequest: ...,
  } satisfies AwardRfqOperationRequest;

  try {
    const data = await api.awardRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **awardRfqRequest** | [AwardRfqRequest](AwardRfqRequest.md) |  | |

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
| **201** | Award created. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## awardRfqLines

> ApiSuccessResponse awardRfqLines(rfqId, awardLinesRequest)

Award specific RFQ lines

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { AwardRfqLinesRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // AwardLinesRequest
    awardLinesRequest: ...,
  } satisfies AwardRfqLinesRequest;

  try {
    const data = await api.awardRfqLines(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **awardLinesRequest** | [AwardLinesRequest](AwardLinesRequest.md) |  | |

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
| **200** | Award lines processed. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createRfq

> CreateRfq201Response createRfq(itemName, type, quantity, material, method, clientCompany, status, items, tolerance, finish, deadlineAt, isOpenBidding, notes, cad)

Create RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { CreateRfqRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    itemName: itemName_example,
    // string
    type: type_example,
    // number
    quantity: 56,
    // string
    material: material_example,
    // string
    method: method_example,
    // string
    clientCompany: clientCompany_example,
    // string
    status: status_example,
    // Array<CreateRfqRequestItemsInner>
    items: ...,
    // string (optional)
    tolerance: tolerance_example,
    // string (optional)
    finish: finish_example,
    // Date (optional)
    deadlineAt: 2013-10-20T19:20:30+01:00,
    // boolean (optional)
    isOpenBidding: true,
    // string (optional)
    notes: notes_example,
    // Blob (optional)
    cad: BINARY_DATA_HERE,
  } satisfies CreateRfqRequest;

  try {
    const data = await api.createRfq(body);
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
| **itemName** | `string` |  | [Defaults to `undefined`] |
| **type** | `ready_made`, `manufacture` |  | [Defaults to `undefined`] [Enum: ready_made, manufacture] |
| **quantity** | `number` |  | [Defaults to `undefined`] |
| **material** | `string` |  | [Defaults to `undefined`] |
| **method** | `string` |  | [Defaults to `undefined`] |
| **clientCompany** | `string` |  | [Defaults to `undefined`] |
| **status** | `awaiting`, `open`, `closed`, `awarded`, `cancelled` |  | [Defaults to `undefined`] [Enum: awaiting, open, closed, awarded, cancelled] |
| **items** | `Array<CreateRfqRequestItemsInner>` |  | |
| **tolerance** | `string` |  | [Optional] [Defaults to `undefined`] |
| **finish** | `string` |  | [Optional] [Defaults to `undefined`] |
| **deadlineAt** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **isOpenBidding** | `boolean` |  | [Optional] [Defaults to `undefined`] |
| **notes** | `string` |  | [Optional] [Defaults to `undefined`] |
| **cad** | `Blob` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**CreateRfq201Response**](CreateRfq201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **201** | RFQ created. |  -  |
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createRfqAmendment

> ApiSuccessResponse createRfqAmendment(rfqId, createRfqAmendmentRequest)

Publish amendment

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { CreateRfqAmendmentOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // CreateRfqAmendmentRequest
    createRfqAmendmentRequest: ...,
  } satisfies CreateRfqAmendmentOperationRequest;

  try {
    const data = await api.createRfqAmendment(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **createRfqAmendmentRequest** | [CreateRfqAmendmentRequest](CreateRfqAmendmentRequest.md) |  | |

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
| **201** | Amendment published. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createRfqClarificationAnswer

> ApiSuccessResponse createRfqClarificationAnswer(rfqId, createRfqAmendmentRequest)

Submit clarification answer

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { CreateRfqClarificationAnswerRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // CreateRfqAmendmentRequest
    createRfqAmendmentRequest: ...,
  } satisfies CreateRfqClarificationAnswerRequest;

  try {
    const data = await api.createRfqClarificationAnswer(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **createRfqAmendmentRequest** | [CreateRfqAmendmentRequest](CreateRfqAmendmentRequest.md) |  | |

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
| **201** | Answer submitted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## createRfqClarificationQuestion

> ApiSuccessResponse createRfqClarificationQuestion(rfqId, createRfqAmendmentRequest)

Submit clarification question

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { CreateRfqClarificationQuestionRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // CreateRfqAmendmentRequest
    createRfqAmendmentRequest: ...,
  } satisfies CreateRfqClarificationQuestionRequest;

  try {
    const data = await api.createRfqClarificationQuestion(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **createRfqAmendmentRequest** | [CreateRfqAmendmentRequest](CreateRfqAmendmentRequest.md) |  | |

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
| **201** | Question submitted. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## deleteRfq

> ApiSuccessResponse deleteRfq(rfqId)

Delete RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { DeleteRfqRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies DeleteRfqRequest;

  try {
    const data = await api.deleteRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |

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
| **200** | RFQ deleted. |  -  |
| **404** | Resource not found. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## inviteSupplierToRfq

> ApiSuccessResponse inviteSupplierToRfq(rfqId, inviteSupplierToRfqRequest)

Invite supplier to RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { InviteSupplierToRfqOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // InviteSupplierToRfqRequest
    inviteSupplierToRfqRequest: ...,
  } satisfies InviteSupplierToRfqOperationRequest;

  try {
    const data = await api.inviteSupplierToRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **inviteSupplierToRfqRequest** | [InviteSupplierToRfqRequest](InviteSupplierToRfqRequest.md) |  | |

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
| **422** | Payload validation failed. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listRfqClarifications

> ListRfqClarifications200Response listRfqClarifications(rfqId)

List clarifications

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { ListRfqClarificationsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ListRfqClarificationsRequest;

  try {
    const data = await api.listRfqClarifications(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ListRfqClarifications200Response**](ListRfqClarifications200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Clarifications for the RFQ. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listRfqInvitations

> ListRfqInvitations200Response listRfqInvitations(rfqId)

List RFQ invitations

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { ListRfqInvitationsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ListRfqInvitationsRequest;

  try {
    const data = await api.listRfqInvitations(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**ListRfqInvitations200Response**](ListRfqInvitations200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Invitations for the given RFQ. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## listRfqs

> ListRfqs200Response listRfqs(perPage, page, tab, q, sort, sortDirection)

List RFQs

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { ListRfqsRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // number (optional)
    perPage: 56,
    // number (optional)
    page: 56,
    // 'all' | 'open' | 'received' | 'sent' (optional)
    tab: tab_example,
    // string (optional)
    q: q_example,
    // 'sent_at' | 'deadline_at' (optional)
    sort: sort_example,
    // 'asc' | 'desc' (optional)
    sortDirection: sortDirection_example,
  } satisfies ListRfqsRequest;

  try {
    const data = await api.listRfqs(body);
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
| **perPage** | `number` |  | [Optional] [Defaults to `undefined`] |
| **page** | `number` |  | [Optional] [Defaults to `undefined`] |
| **tab** | `all`, `open`, `received`, `sent` |  | [Optional] [Defaults to `undefined`] [Enum: all, open, received, sent] |
| **q** | `string` |  | [Optional] [Defaults to `undefined`] |
| **sort** | `sent_at`, `deadline_at` |  | [Optional] [Defaults to `undefined`] [Enum: sent_at, deadline_at] |
| **sortDirection** | `asc`, `desc` |  | [Optional] [Defaults to `undefined`] [Enum: asc, desc] |

### Return type

[**ListRfqs200Response**](ListRfqs200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Collection of RFQs visible to the current tenant. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## showRfq

> CreateRfq201Response showRfq(rfqId)

Retrieve RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { ShowRfqRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
  } satisfies ShowRfqRequest;

  try {
    const data = await api.showRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |

### Return type

[**CreateRfq201Response**](CreateRfq201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | RFQ details. |  -  |
| **404** | Resource not found. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## updateRfq

> CreateRfq201Response updateRfq(rfqId, itemName, type, quantity, material, method, tolerance, finish, status, isOpenBidding, notes, deadlineAt, cad)

Update RFQ

### Example

```ts
import {
  Configuration,
  RFQsApi,
} from '';
import type { UpdateRfqRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // To configure API key authorization: apiKeyAuth
    apiKey: "YOUR API KEY",
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new RFQsApi(config);

  const body = {
    // string
    rfqId: 38400000-8cf0-11bd-b23e-10b96e4ef00d,
    // string (optional)
    itemName: itemName_example,
    // string (optional)
    type: type_example,
    // number (optional)
    quantity: 56,
    // string (optional)
    material: material_example,
    // string (optional)
    method: method_example,
    // string (optional)
    tolerance: tolerance_example,
    // string (optional)
    finish: finish_example,
    // string (optional)
    status: status_example,
    // boolean (optional)
    isOpenBidding: true,
    // string (optional)
    notes: notes_example,
    // Date (optional)
    deadlineAt: 2013-10-20T19:20:30+01:00,
    // Blob (optional)
    cad: BINARY_DATA_HERE,
  } satisfies UpdateRfqRequest;

  try {
    const data = await api.updateRfq(body);
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
| **rfqId** | `string` |  | [Defaults to `undefined`] |
| **itemName** | `string` |  | [Optional] [Defaults to `undefined`] |
| **type** | `string` |  | [Optional] [Defaults to `undefined`] |
| **quantity** | `number` |  | [Optional] [Defaults to `undefined`] |
| **material** | `string` |  | [Optional] [Defaults to `undefined`] |
| **method** | `string` |  | [Optional] [Defaults to `undefined`] |
| **tolerance** | `string` |  | [Optional] [Defaults to `undefined`] |
| **finish** | `string` |  | [Optional] [Defaults to `undefined`] |
| **status** | `awaiting`, `open`, `closed`, `awarded`, `cancelled` |  | [Optional] [Defaults to `undefined`] [Enum: awaiting, open, closed, awarded, cancelled] |
| **isOpenBidding** | `boolean` |  | [Optional] [Defaults to `undefined`] |
| **notes** | `string` |  | [Optional] [Defaults to `undefined`] |
| **deadlineAt** | `Date` |  | [Optional] [Defaults to `undefined`] |
| **cad** | `Blob` |  | [Optional] [Defaults to `undefined`] |

### Return type

[**CreateRfq201Response**](CreateRfq201Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `multipart/form-data`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | RFQ updated. |  -  |
| **404** | Resource not found. |  * X-Request-Id -  <br>  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

