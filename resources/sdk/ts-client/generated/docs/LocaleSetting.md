
# LocaleSetting


## Properties

Name | Type
------------ | -------------
`locale` | string
`timezone` | string
`numberFormat` | string
`dateFormat` | string
`firstDayOfWeek` | number
`weekendDays` | Array&lt;number&gt;

## Example

```typescript
import type { LocaleSetting } from ''

// TODO: Update the object below with actual values
const example = {
  "locale": null,
  "timezone": null,
  "numberFormat": null,
  "dateFormat": null,
  "firstDayOfWeek": null,
  "weekendDays": null,
} satisfies LocaleSetting

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as LocaleSetting
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


