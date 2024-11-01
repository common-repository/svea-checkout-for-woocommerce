# OrderItem

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**articleNumber** | **string** | Article number of the order item. | [optional] 
**name** | **string** | Item name. This will be visible to the end customer on e.g. the invoice.   Max length limit 40 character | 
**quantity** | **int** | The quantity of units for order item. Note! Minor format used; setting the value 100 equals quantity of 1. | 
**unitPrice** | **int** | Price per unit including vat. Note! Minor format used; setting the value 100 equals 1 unit | 
**discountPercent** | **int** | The discount percent for the order row. Note! Minor format used; setting the value 1000 equals 10% discount | [optional] 
**discountAmount** | **int** | The discount amount for this item including vat. Note! Minor format used; setting the value 100 equals 1 unit | [optional] 
**vatPercent** | **int** | The vat percent for this item. The value must be a valid vat percent for the country code of the request. Note! Minor format used; setting the value 2500 equals 25% vat. | 
**unit** | **string** | The unit to measure the items. | 
**rowNumber** | **int** | Each row can be assigned a row number for future reference | [optional] 
**merchantData** | **string** | Optional data, size for clothes etc.  Max length limit 255 character | [optional] 

[[Back to Model list]](../../README.md#documentation-for-models) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to README]](../../README.md)

