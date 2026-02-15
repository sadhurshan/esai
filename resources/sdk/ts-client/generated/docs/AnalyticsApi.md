# AnalyticsApi

All URIs are relative to *https://api.elements-supply.ai*

| Method                                                           | HTTP request                                    | Description                        |
| ---------------------------------------------------------------- | ----------------------------------------------- | ---------------------------------- |
| [**analyticsGenerate**](AnalyticsApi.md#analyticsgenerate)       | **POST** /api/analytics/generate                | Generate ad-hoc analytics export   |
| [**analyticsOverview**](AnalyticsApi.md#analyticsoverview)       | **GET** /api/analytics/overview                 | Fetch analytics overview dashboard |
| [**getDashboardMetrics**](AnalyticsApi.md#getdashboardmetrics)   | **GET** /api/dashboard/metrics                  | Retrieve dashboard KPI metrics     |
| [**listAnalyticsReports**](AnalyticsApi.md#listanalyticsreports) | **GET** /api/analytics/reports                  | List analytics reports             |
| [**runAnalyticsReport**](AnalyticsApi.md#runanalyticsreport)     | **POST** /api/analytics/reports/{reportKey}/run | Run analytics report               |
| [**showAnalyticsReport**](AnalyticsApi.md#showanalyticsreport)   | **GET** /api/analytics/reports/{reportKey}      | Show report configuration          |

## analyticsGenerate

> ApiSuccessResponse analyticsGenerate(requestBody)

Generate ad-hoc analytics export

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { AnalyticsGenerateRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    const body = {
        // { [key: string]: any; }
        requestBody: Object,
    } satisfies AnalyticsGenerateRequest;

    try {
        const data = await api.analyticsGenerate(body);
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

| Status code | Description                  | Response headers |
| ----------- | ---------------------------- | ---------------- |
| **202**     | Analytics generation queued. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## analyticsOverview

> ApiSuccessResponse analyticsOverview()

Fetch analytics overview dashboard

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { AnalyticsOverviewRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    try {
        const data = await api.analyticsOverview();
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

| Status code | Description                                          | Response headers |
| ----------- | ---------------------------------------------------- | ---------------- |
| **200**     | Aggregate metrics for the analytics overview screen. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## getDashboardMetrics

> GetDashboardMetrics200Response getDashboardMetrics()

Retrieve dashboard KPI metrics

Returns key sourcing and downstream execution counts for the authenticated company. Requires analytics plan access.

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { GetDashboardMetricsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    try {
        const data = await api.getDashboardMetrics();
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

[**GetDashboardMetrics200Response**](GetDashboardMetrics200Response.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

### HTTP response details

| Status code | Description                                      | Response headers |
| ----------- | ------------------------------------------------ | ---------------- |
| **200**     | Dashboard metric counts for the current company. | -                |
| **402**     | Analytics plan required.                         | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## listAnalyticsReports

> ApiSuccessResponse listAnalyticsReports()

List analytics reports

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { ListAnalyticsReportsRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    try {
        const data = await api.listAnalyticsReports();
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
| **200**     | Available analytics reports for the current plan. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## runAnalyticsReport

> ApiSuccessResponse runAnalyticsReport(reportKey, body)

Run analytics report

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { RunAnalyticsReportRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    const body = {
        // string
        reportKey: reportKey_example,
        // object (optional)
        body: Object,
    } satisfies RunAnalyticsReportRequest;

    try {
        const data = await api.runAnalyticsReport(body);
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
| **reportKey** | `string` |             | [Defaults to `undefined`] |
| **body**      | `object` |             | [Optional]                |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

[apiKeyAuth](../README.md#apiKeyAuth), [bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

### HTTP response details

| Status code | Description              | Response headers |
| ----------- | ------------------------ | ---------------- |
| **202**     | Report execution queued. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

## showAnalyticsReport

> ApiSuccessResponse showAnalyticsReport(reportKey)

Show report configuration

### Example

```ts
import { Configuration, AnalyticsApi } from '';
import type { ShowAnalyticsReportRequest } from '';

async function example() {
    console.log('🚀 Testing  SDK...');
    const config = new Configuration({
        // To configure API key authorization: apiKeyAuth
        apiKey: 'YOUR API KEY',
        // Configure HTTP bearer authorization: bearerAuth
        accessToken: 'YOUR BEARER TOKEN',
    });
    const api = new AnalyticsApi(config);

    const body = {
        // string
        reportKey: reportKey_example,
    } satisfies ShowAnalyticsReportRequest;

    try {
        const data = await api.showAnalyticsReport(body);
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
| **reportKey** | `string` |             | [Defaults to `undefined`] |

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
| **200**     | Report definition including filters. | -                |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
