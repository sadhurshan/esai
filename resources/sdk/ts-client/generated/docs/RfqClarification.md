# RfqClarification

## Properties

| Name          | Type                                                                                 |
| ------------- | ------------------------------------------------------------------------------------ |
| `id`          | string                                                                               |
| `rfqId`       | string                                                                               |
| `type`        | string                                                                               |
| `body`        | string                                                                               |
| `author`      | object                                                                               |
| `createdAt`   | Date                                                                                 |
| `attachments` | [Array&lt;RfqClarificationAttachmentsInner&gt;](RfqClarificationAttachmentsInner.md) |

## Example

```typescript
import type { RfqClarification } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    rfqId: null,
    type: null,
    body: null,
    author: null,
    createdAt: null,
    attachments: null,
} satisfies RfqClarification;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqClarification;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
