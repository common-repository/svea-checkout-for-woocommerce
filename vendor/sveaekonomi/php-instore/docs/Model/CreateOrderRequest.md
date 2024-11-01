# CreateOrderRequest

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**merchantOrderNumber** | **string** | The merchant order number. It must be unique for the merchant | 
**countryCode** | **string** | Country code for the merchant. The country code must be preconfigured as valid for the merchant to be valid. | 
**currency** | **string** | Currency. ISO currency symbol, e.g. SEK, NOK, EUR. If not set the default currency for the CountryCode is used. | [optional] 
**mobilePhoneNumber** | **string** | Mobile phone number to the end customer. If provided, an SMS with a link to the checkout page will be sent to the number. | [optional] 
**tags** | **string[]** | A list of strings that can be used to identify the order, and provide reporting on e.g. orders from a certain store. | [optional] 
**orderItems** | [**\Svea\Instore\Model\OrderItem[]**](OrderItem.md) | A list of items in the order | 
**callbackUri** | **string** | A callback can be done when the checkout is completed. A HTTP POST message with the merchant order number is done to the URI entered.   If no callback is wanted, leave this field blank. Polling for completed order status to find completed orders is then done by HTTP GET on the order status endpoint.  Note! No more information about the order is sent with the push. To retrieve all information about the order a HTTP GET on the order status endpoint needs to be done as well. | [optional] 
**termsUri** | **string** | A link to where the merchant terms and conditions are hosted. | 
**deferredDelivery** | **bool** | All orders will be marked as delivered in the Payment Admin system by default, allowing the invoice to be sent to the end user. Set this to &#x27;true&#x27; if the order should not be marked as delivered, e.g. if the goods has not been delivered yet. | [optional] [default to false]
**minutesUntilLinkExpires** | **double** | Number of minutes the Instore link will be available. If no value is present Instore will use the default value of 20 minutes. Value has to be greater then 0. | [optional] 
**presetValues** | [**\Svea\Instore\Model\PresetValue[]**](PresetValue.md) | List of attributes to preset values for on the checkout. See model documentation for valid attributes and value limitations. | [optional] 
**requireElectronicIdAuthentication** | **bool** | Possibility to disable electronic id authentication (e.g. BankID), for orders that are not completed in a physical store and therefore can use the same flow as a web order. | [optional] [default to true]
**merchantName** | **string** |  | [optional] 

[[Back to Model list]](../../README.md#documentation-for-models) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to README]](../../README.md)

