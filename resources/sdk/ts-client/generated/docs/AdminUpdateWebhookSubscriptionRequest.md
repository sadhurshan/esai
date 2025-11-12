
# AdminUpdateWebhookSubscriptionRequest


## Properties

Name | Type
------------ | -------------
`url` | string
`events` | Array&lt;string&gt;
`active` | boolean
`retryPolicy` | { [key: string]: any; }

## Example

```typescript
import type { AdminUpdateWebhookSubscriptionRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "url": null,
  "events": null,
  "active": null,
  "retryPolicy": null,
} satisfies AdminUpdateWebhookSubscriptionRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminUpdateWebhookSubscriptionRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


