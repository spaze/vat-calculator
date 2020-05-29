# Upgrading from 2.* to 3.*

* Version 3 doesn't support Laravel/Cashier anymore (**BC BREAK**).
* The namespace has been changed for this fork from `Mpociot\VatCalculator` to `Spaze\VatCalculator` (**BC BREAK**)
* Exceptions (`VatCheckUnavailableException`) are always thrown, `forwardSoapFaults` option has been removed (**BC BREAK**)
* Some countries have various VAT rates depending on location resulting in `getTaxRateForCountry()` removal, use `getTaxRateForLocation()` instead (**BC BREAK**)
* Rates have been moved to a separate class `VatRates`, you need to pass the class to `VatCalculator` constructor  (**BC BREAK**)
* Norway VAT rate removed, can be manually added back with `VatRates::addRateForCountry()`
* `getIPBasedCountry()` & `getClientIP()` methods have been removed, use some other package (or `CF-IPCountry` HTTP header if you're behind Cloudflare)
* Some methods have been properly *camelCased*: methods like `getClientIP()` -> `getClientIp()` and `shouldCollectVAT` -> `shouldCollectVat` and a few more
* `VATCheckUnavailableException` has been *camelCased* to `VatCheckUnavailableException`
* Requires PHP 7.3

# Upgrading from 1.* to 2.*

Version 2 of the VAT Calculator provides a new method to get a more precise VAT rate result.
It's recommended to use the new `getTaxRateForLocation` method instead of `getTaxRateForCountry`.

This method expects 3 arguments:

* country code - The country code of the customer
* postal code - The postal code of the customer
* company - Flag to indicate if the customer is a company
