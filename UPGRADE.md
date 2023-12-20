# Upgrading from <3.6 to 3.7
* The namespace has been changed for this fork from `Spaze\VatCalculator` to `JakubJachym\VatCalculator` (**BC BREAK**)

# Upgrading from 2.* to 3.*

* Version 3 doesn't support Laravel/Cashier anymore (**BC BREAK**).
* The namespace has been changed for this fork from `Mpociot\VatCalculator` to `Spaze\VatCalculator` (**BC BREAK**)
* Exceptions (`VatCheckUnavailableException`) are always thrown, `forwardSoapFaults` option has been removed (**BC BREAK**)
* Some countries have various VAT rates depending on location resulting in `getTaxRateForCountry()` removal, use `getTaxRateForLocation()` instead (**BC BREAK**)
* Rates have been moved to a separate class `VatRates`, you need to pass the class to `VatCalculator` constructor  (**BC BREAK**)
* `calculate()` & `calculateNet()` methods return `VatPrice` object instead of the calculated price (**BR BREAK**)
* After running `calculate()` & `calculateNet()`, the `VatCalculator` object keeps its state, `getNetPrice()`, `getTaxRate()`, `getCountryCode()`, `setCountryCode()`, `getPostalCode()`, `setPostalCode()`, `isCompany()`, `setCompany()` removed (**BR BREAK**)
* Norway, Turkey, Switzerland VAT rates removed, can be manually added back with `VatRates::addRateForCountry()`
* `shouldCollectVat()` will also return `true` for those manually added non-EU countries, use `shouldCollectEuVat()` to return `true` only for EU member states
* `getIPBasedCountry()` & `getClientIP()` methods have been removed, use some other package (or `CF-IPCountry` HTTP header if you're behind Cloudflare)
* Some methods have been properly *camelCased*: methods like `getClientIP()` -> `getClientIp()` and `shouldCollectVAT` -> `shouldCollectVat` and a few more
* `VATCheckUnavailableException` has been *camelCased* to `VatCheckUnavailableException`
* If a VAT number from an unsupported/non-EU country is provided for validation or for `getVatDetails()` call, `UnsupportedCountryException` will be thrown
* VIES WSDL is now loaded over HTTPS, if you hit issues you should update your system's list of trusted CAs, or install [composer/ca-bundle](https://github.com/composer/ca-bundle), use it [with PHP streams](https://github.com/composer/ca-bundle#to-use-with-php-streams), create your own `SoapClient` instance with `stream_context` option (`new SoapClient(self::VAT_SERVICE_URL, ['stream_context' => $context])`) and use `VatCalculator::setSoapClient()` to use the custom client
* Requires PHP 7.3

# Upgrading from 1.* to 2.*

Version 2 of the VAT Calculator provides a new method to get a more precise VAT rate result.
It's recommended to use the new `getTaxRateForLocation` method instead of `getTaxRateForCountry`.

This method expects 3 arguments:

* country code - The country code of the customer
* postal code - The postal code of the customer
* company - Flag to indicate if the customer is a company
