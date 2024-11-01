# Svea\Instore\OrderApi

All URIs are relative to *https://webpayinstoreapi.svea.com*

Method | HTTP request | Description
------------- | ------------- | -------------
[**cancelOrder**](OrderApi.md#cancelorder) | **POST** /api/v1/orders/{merchantOrderNumber}/cancel | Cancel the entire order. If the order has been delivered the invoice will be credited.
[**createOrder**](OrderApi.md#createorder) | **POST** /api/v1/orders | Creates an order. A SMS message will be sent to the provided mobile phone number where the checkout can be completed.
[**getOrderStatus**](OrderApi.md#getorderstatus) | **GET** /api/v1/orders/{merchantOrderNumber}/status | Gets order status for a created order. When the order has been finalized customer information is added to the order status response.
[**returnOrder**](OrderApi.md#returnorder) | **POST** /api/v1/orders/{merchantOrderNumber}/return | 

# **cancelOrder**
> \Svea\Instore\Model\RevokeOrderResponse cancelOrder($merchantOrderNumber)

Cancel the entire order. If the order has been delivered the invoice will be credited.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// Configure HTTP basic authorization: basic
$config = Svea\Instore\Configuration::getDefaultConfiguration()
              ->setUsername('YOUR_USERNAME')
              ->setPassword('YOUR_PASSWORD');


$apiInstance = new Svea\Instore\Api\OrderApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$merchantOrderNumber = "merchantOrderNumber_example"; // string | The unique merchant order number used when creating the order

try {
    $result = $apiInstance->cancelOrder($merchantOrderNumber);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OrderApi->cancelOrder: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **merchantOrderNumber** | **string**| The unique merchant order number used when creating the order |

### Return type

[**\Svea\Instore\Model\RevokeOrderResponse**](../Model/RevokeOrderResponse.md)

### Authorization

[basic](../../README.md#basic)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: text/plain, application/json, text/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **createOrder**
> \Svea\Instore\Model\CreateOrderResponse createOrder($body)

Creates an order. A SMS message will be sent to the provided mobile phone number where the checkout can be completed.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// Configure HTTP basic authorization: basic
$config = Svea\Instore\Configuration::getDefaultConfiguration()
              ->setUsername('YOUR_USERNAME')
              ->setPassword('YOUR_PASSWORD');


$apiInstance = new Svea\Instore\Api\OrderApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$body = new \Svea\Instore\Model\CreateOrderRequest(); // \Svea\Instore\Model\CreateOrderRequest | 

try {
    $result = $apiInstance->createOrder($body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OrderApi->createOrder: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **body** | [**\Svea\Instore\Model\CreateOrderRequest**](../Model/CreateOrderRequest.md)|  | [optional]

### Return type

[**\Svea\Instore\Model\CreateOrderResponse**](../Model/CreateOrderResponse.md)

### Authorization

[basic](../../README.md#basic)

### HTTP request headers

 - **Content-Type**: application/json, text/json, application/_*+json
 - **Accept**: text/plain, application/json, text/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **getOrderStatus**
> \Svea\Instore\Model\GetOrderStatusResponse getOrderStatus($merchantOrderNumber)

Gets order status for a created order. When the order has been finalized customer information is added to the order status response.

### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// Configure HTTP basic authorization: basic
$config = Svea\Instore\Configuration::getDefaultConfiguration()
              ->setUsername('YOUR_USERNAME')
              ->setPassword('YOUR_PASSWORD');


$apiInstance = new Svea\Instore\Api\OrderApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$merchantOrderNumber = "merchantOrderNumber_example"; // string | The unique merchant order number used when creating the order

try {
    $result = $apiInstance->getOrderStatus($merchantOrderNumber);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OrderApi->getOrderStatus: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **merchantOrderNumber** | **string**| The unique merchant order number used when creating the order |

### Return type

[**\Svea\Instore\Model\GetOrderStatusResponse**](../Model/GetOrderStatusResponse.md)

### Authorization

[basic](../../README.md#basic)

### HTTP request headers

 - **Content-Type**: Not defined
 - **Accept**: text/plain, application/json, text/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

# **returnOrder**
> \Svea\Instore\Model\RevokeOrderResponse returnOrder($merchantOrderNumber, $body)



### Example
```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');
// Configure HTTP basic authorization: basic
$config = Svea\Instore\Configuration::getDefaultConfiguration()
              ->setUsername('YOUR_USERNAME')
              ->setPassword('YOUR_PASSWORD');


$apiInstance = new Svea\Instore\Api\OrderApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$merchantOrderNumber = "merchantOrderNumber_example"; // string | The unique merchant order number used when creating the order
$body = new \Svea\Instore\Model\RevokeOrderRequest(); // \Svea\Instore\Model\RevokeOrderRequest | Order rows to credit. Doesn't have to match the order rows on the invoice.

try {
    $result = $apiInstance->returnOrder($merchantOrderNumber, $body);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling OrderApi->returnOrder: ', $e->getMessage(), PHP_EOL;
}
?>
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **merchantOrderNumber** | **string**| The unique merchant order number used when creating the order |
 **body** | [**\Svea\Instore\Model\RevokeOrderRequest**](../Model/RevokeOrderRequest.md)| Order rows to credit. Doesn&#x27;t have to match the order rows on the invoice. | [optional]

### Return type

[**\Svea\Instore\Model\RevokeOrderResponse**](../Model/RevokeOrderResponse.md)

### Authorization

[basic](../../README.md#basic)

### HTTP request headers

 - **Content-Type**: application/json, text/json, application/_*+json
 - **Accept**: text/plain, application/json, text/json

[[Back to top]](#) [[Back to API list]](../../README.md#documentation-for-api-endpoints) [[Back to Model list]](../../README.md#documentation-for-models) [[Back to README]](../../README.md)

