# AuthApi

All URIs are relative to *https://api.elements-supply.ai*

| Method | HTTP request | Description |
|------------- | ------------- | -------------|
| [**authForgotPassword**](AuthApi.md#authforgotpasswordoperation) | **POST** /api/auth/forgot-password | Send password reset link |
| [**authLogin**](AuthApi.md#authloginoperation) | **POST** /api/auth/login | Create an authenticated session |
| [**authLogout**](AuthApi.md#authlogout) | **POST** /api/auth/logout | Terminate the authenticated session |
| [**authResetPassword**](AuthApi.md#authresetpasswordoperation) | **POST** /api/auth/reset-password | Reset password with token |
| [**authSession**](AuthApi.md#authsession) | **GET** /api/auth/me | Inspect the current authenticated session |



## authForgotPassword

> ApiSuccessResponse authForgotPassword(authForgotPasswordRequest)

Send password reset link

Sends a password reset link email if the account exists. The response is identical whether or not the email belongs to an account.

### Example

```ts
import {
  Configuration,
  AuthApi,
} from '';
import type { AuthForgotPasswordOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const api = new AuthApi();

  const body = {
    // AuthForgotPasswordRequest
    authForgotPasswordRequest: ...,
  } satisfies AuthForgotPasswordOperationRequest;

  try {
    const data = await api.authForgotPassword(body);
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
| **authForgotPasswordRequest** | [AuthForgotPasswordRequest](AuthForgotPasswordRequest.md) |  | |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Reset email dispatched (or suppressed for privacy). |  -  |
| **422** | Invalid email payload. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## authLogin

> AuthLogin200Response authLogin(authLoginRequest)

Create an authenticated session

Authenticates a user with email and password credentials and returns the enriched session payload used by Inertia and the SDK.

### Example

```ts
import {
  Configuration,
  AuthApi,
} from '';
import type { AuthLoginOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const api = new AuthApi();

  const body = {
    // AuthLoginRequest
    authLoginRequest: ...,
  } satisfies AuthLoginOperationRequest;

  try {
    const data = await api.authLogin(body);
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
| **authLoginRequest** | [AuthLoginRequest](AuthLoginRequest.md) |  | |

### Return type

[**AuthLogin200Response**](AuthLogin200Response.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Authenticated session payload. |  -  |
| **422** | Invalid credentials supplied. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## authLogout

> ApiSuccessResponse authLogout()

Terminate the authenticated session

### Example

```ts
import {
  Configuration,
  AuthApi,
} from '';
import type { AuthLogoutRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AuthApi(config);

  try {
    const data = await api.authLogout();
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

[bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Session destroyed successfully. |  -  |
| **401** | No active session. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## authResetPassword

> ApiSuccessResponse authResetPassword(authResetPasswordRequest)

Reset password with token

Verifies the password reset token issued via email and sets a new password for the user.

### Example

```ts
import {
  Configuration,
  AuthApi,
} from '';
import type { AuthResetPasswordOperationRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const api = new AuthApi();

  const body = {
    // AuthResetPasswordRequest
    authResetPasswordRequest: ...,
  } satisfies AuthResetPasswordOperationRequest;

  try {
    const data = await api.authResetPassword(body);
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
| **authResetPasswordRequest** | [AuthResetPasswordRequest](AuthResetPasswordRequest.md) |  | |

### Return type

[**ApiSuccessResponse**](ApiSuccessResponse.md)

### Authorization

No authorization required

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Password reset successfully. |  -  |
| **422** | Invalid reset token or password payload. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


## authSession

> AuthLogin200Response authSession()

Inspect the current authenticated session

Returns the authenticated user, company context, feature flags, and active plan code associated with the current session.

### Example

```ts
import {
  Configuration,
  AuthApi,
} from '';
import type { AuthSessionRequest } from '';

async function example() {
  console.log("🚀 Testing  SDK...");
  const config = new Configuration({ 
    // Configure HTTP bearer authorization: bearerAuth
    accessToken: "YOUR BEARER TOKEN",
  });
  const api = new AuthApi(config);

  try {
    const data = await api.authSession();
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

[**AuthLogin200Response**](AuthLogin200Response.md)

### Authorization

[bearerAuth](../README.md#bearerAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`


### HTTP response details
| Status code | Description | Response headers |
|-------------|-------------|------------------|
| **200** | Session metadata. |  -  |
| **401** | Missing or invalid session. |  -  |

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)

