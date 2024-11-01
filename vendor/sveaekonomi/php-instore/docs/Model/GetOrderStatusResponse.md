# GetOrderStatusResponse

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**merchantOrderNumber** | **string** | The order number that identifies the order for the merchant | [optional] 
**instoreUiUri** | **string** | URL to the site where the customer can complete the order. This can be used if the SMS doesn&#x27;t reach the mobile phone, so it is recommended to display in the cash register while waiting for the checkout to be completed. | [optional] 
**orderStatus** | **string** | Status of the order. It can be Active, Completed or Canceled | [optional] 
**paymentMethodType** | **string** | Payment method type. Set when order is Completed | [optional] 
**campaignCode** | **int** | Set when order is Completed, showing campaign code (if used). Only available for PaymentPlan | [optional] 
**paymentType** | **string** | Payment type. Set when order is Completed | [optional] 
**paymentOrderId** | **int** | Sveas order id. Use this when contacting Svea support if you experience any issues with an order. | [optional] 
**customerInformation** | [**\Svea\Instore\Model\CustomerInformation**](CustomerInformation.md) |  | [optional] 
**tags** | **string[]** | Tags set on the order when it was created | [optional] 
**orderItems** | [**\Svea\Instore\Model\OrderItem[]**](OrderItem.md) | A list of items in the order | [optional] 

[[Back to Model list]](../../README.md#documentation-for-models) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to README]](../../README.md)

