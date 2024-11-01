# PresetValue

## Properties
Name | Type | Description | Notes
------------ | ------------- | ------------- | -------------
**attributeName** | **string** | Name of the attribute that should be set. Valid values are: \&quot;NationalId\&quot;, \&quot;EmailAddress\&quot;, \&quot;PostalCode\&quot;, \&quot;IsCompany\&quot; | [optional] 
**value** | **string** | Value that should be set for the selected attribute. Limitations:  NationalId: A valid national id (individual or company)  EmailAddress: A valid e-mail address. Max 50 characters.  PostalCode: A valid postal code  IsCompany: True/False. Set if checkout is B2B or B2C. Required if NationalId is set. | [optional] 
**isReadonly** | **bool** | Should the preset value be locked for editing, set read only to true. | [optional] 

[[Back to Model list]](../../README.md#documentation-for-models) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to README]](../../README.md)

