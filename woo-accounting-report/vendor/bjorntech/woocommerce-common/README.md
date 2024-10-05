# bjorntech-woocommerce-common

This project contains several classes and traits that are used to handle various aspects of the WooCommerce integration. Here are the main classes and traits:

## GeneralException

This class extends the PHP Exception class and is used to handle exceptions in a more detailed way.

### GeneralException Methods

- [__construct()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/GeneralException.php#42%2C21-42%2C21): This method is used to initialize the exception with additional data.
- [write_to_logs()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/GeneralException.php#53%2C8-53%2C8): This method is used to write the exception details to the WooCommerce system logs.

## WoocommerceAvailablePaymentGateways

This class is used to manage the available payment gateways in WooCommerce.

### WoocommerceAvailablePaymentGateways Methods

- [init()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/PluginTrait.php#13%2C28-13%2C28): This method is used to initialize the class with the gateway class and allowed users.
- [set_allowed_users()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/WoocommerceAvailablePaymentGateways.php#16%2C17-16%2C17): This method is used to set the allowed users for the payment gateway.
- [add_gateway()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/WoocommerceAvailablePaymentGateways.php#22%2C65-22%2C65): This method is used to add the gateway to the list of WooCommerce payment gateways.
- [filter_gateway()](file:///Users/tobbe/Projects/bjorntech-woocommerce-common/src/WoocommerceAvailablePaymentGateways.php#20%2C75-20%2C75): This method is used to filter the available payment gateways based on the allowed users.

## Traits

### PluginTrait

This trait provides methods for handling plugin related tasks.

### LoggerTrait

This trait provides methods for logging.

### InstanceTrait

This trait provides a method for creating and managing instances of a class.
