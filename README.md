VatCalculator
================

[![Software License](https://img.shields.io/github/license/spaze/vat-calculator)](LICENSE.md)
[![PHP Tests](https://github.com/spaze/vat-calculator/workflows/PHP%20Tests/badge.svg)](https://github.com/spaze/vat-calculator/actions?query=workflow%3A%22PHP+Tests%22)

Handle all the hard stuff related to EU MOSS tax/vat regulations, the way it should be. This is a fork of [mpociot/vat-calculator](https://github.com/mpociot/vat-calculator) without Laravel/Cashier support.

```php
// Easy to use!
use Spaze\VatCalculator\VatCalculator;

$vatRates = new VatRates();
$vatCalculator = new VatCalculator($vatRates);
$vatCalculator->calculate(71.00, 'DE' /* $countryCode */, '41352' /* $postalCode or null */,  true /* Whether the customer you're calculating the VAT for is a company */);
$vatCalculator->getTaxRateForLocation('NL');
// Check validity of a VAT number
$vatCalculator->isValidVatNumber('NL123456789B01');
```
## Contents

- [Installation](#installation)
	- [Standalone](#installation-standalone)
- [Usage](#usage)
	- [Calculate the gross price](#calculate-the-gross-price)
	- [Validate EU VAT numbers](#validate-eu-vat-numbers)
	- [Get EU VAT number details](#vat-number-details)
	- [Get the IP based country of your user](#get-ip-based-country)
	- [Countries](#countries)
- [License](#license)
- [Contributing](#contributing)

<a name="installation"></a>
## Installation

In order to install the VAT Calculator, just run

```bash
$ composer require spaze/vat-calculator
```

<a name="installation-standalone"></a>
### Standalone

This package is designed for standalone usage. Simply create a new instance of the VAT calculator and use it.

Example:

```php
use Spaze\VatCalculator\VatCalculator;

$vatRates = new VatRates();
$vatCalculator = new VatCalculator($vatRates);
$vatCalculator->setBusinessCountryCode('DE');  // Where your company is based in
$price = $vatCalculator->calculate(49.99, 'LU', null, false);
$price->getPrice();
$price->getNetPrice();
$price->getTaxValue();
$price->getTaxRate();
```

<a name="usage"></a>
## Usage
<a name="calculate-the-gross-price"></a>
### Calculate the gross price
To calculate the gross price (price with VAT added) use the `calculate` method with a net price, a country code, a postal code (null if unknow) and whether you're calculating VAT for a customer that's a company as paremeters.

```php
$grossPrice = $vatCalculator->calculate(24.00, 'DE', null, false /* [, $rateType [, $dateTime]] */);
```
The third parameter is the postal code of the customer, pass `null` if unknown.

As a fourth parameter, you can pass in a boolean indicating whether the customer is a company or a private person. If the customer is a company, which you should check by <a href="#validate-eu-vat-numbers">validating the VAT number</a>, the net price gets returned.

Fifth optional parameter defines which VAT rate to use if there are more defined for the particular country (`VatRates::HIGH`, `VatRates::LOW`, `VatRates::GENERAL` is the default when just one rate is defined).

The sixth parameter, optional, specifies the date to use the VAT rate for. This is needed when a country changes its VAT rate and you want to calculate a price with the previous rate. Pass `DateTime` or `DateTimeImmutable` object. Current date used when not specified.

Returns `VatPrice` object:
```php
$grossPrice->getPrice();
$grossPrice->getNetPrice();
$grossPrice->getTaxValue();
$grossPrice->getTaxRate();

```

<a name="validate-eu-vat-numbers"></a>
### Validate EU VAT numbers

Prior to validating your customers VAT numbers, you can use the `shouldCollectVat` method to check if the country code requires you to collect VAT
in the first place. This method will return `true` even for non-EU countries added manually with `addRateForCountry` (see below).

To ignore those manually added non-EU countries and return `true` only for EU member states, you can use `shouldCollectEuVat`.

```php
if ($vatCalculator->shouldCollectVat('DE')) {

}

if ($vatCalculator->shouldCollectEuVat('DE')) {

}
```

To validate your customers VAT numbers, you can use the `isValidVatNumber` method.
The VAT number should be in a format specified by the [VIES](https://ec.europa.eu/taxation_customs/vies/faqvies.do#item_11).
The given VAT numbers will be truncated and non relevant characters (`-`, `.`, `,`, whitespace) will automatically be removed.
If there are any invalid characters left, like non-latin letters for example, `InvalidCharsInVatNumberException` will be thrown.

This service relies on a third party SOAP API provided by the EU. If, for whatever reason, this API is unavailable a `VatCheckUnavailableException` will be thrown.

If a VAT number from an unsupported/non-EU country is provided, `UnsupportedCountryException` will be thrown.

```php
try {
	$validVat = $vatCalculator->isValidVatNumber('NL 123456789 B01');
} catch (VatCheckUnavailableException $e) {
	// Please handle me
}
```

<a name="vat-number-details"></a>
### Get EU VAT number details

To get the details of a VAT number, you can use the `getVatDetails` method.
The VAT number should be in a format specified by the [VIES](https://ec.europa.eu/taxation_customs/vies/faqvies.do#item_11).
The given VAT numbers will be truncated and non relevant characters / whitespace will automatically be removed.

This service relies on a third party SOAP API provided by the EU. If, for whatever reason, this API is unavailable a `VatCheckUnavailableException` will be thrown.

If a VAT number from a unsupported/non-EU country is provided, `UnsupportedCountryException` will be thrown.

```php
try {
	$vat_details = $vatCalculator->getVatDetails('NL 123456789 B01');
	print_r($vat_details);
	/* Outputs VatDetails object, use getters to access values
	VatDetails Object
	(
		[valid:VatDetails:private] => false
		[countryCode:VatDetails:private] => NL
		[vatNumber:VatDetails:private] => 123456789B01
		[requestId:VatDetails:private] => FOOBAR338
	)
	*/
} catch (VatCheckUnavailableException $e) {
	// Please handle me
}
```

<a name="countries"></a>
## Countries

EU countries are supported as well as some non-EU countries that use VAT. Some countries are not supported even though they also have VAT. Currently, that's the case for the following countries:
- Switzerland (CH)
- Norway (NO)
- Turkey (TR)

These can be added manually with `VatRates::addRateForCountry()`:

```php
$vatRates = new VatRates();
$vatRates->addRateForCountry('NO');
$vatCalculator = new VatCalculator($vatRates);
```

Please keep in mind that with these countries you cannot validate VAT ids with `isValidVatNumber()` because it uses VIES, the EU VAT number validation service, and as these countries are not part of the EU, it will always come back as invalid.

<a name="license"></a>
## License
This library is licensed under the MIT license. Please see [License file](LICENSE.md) for more information.

<a name="contributing"></a>
## Contributing
Run PHPUnit tests and static analysis with `composer test`, see `scripts` in `composer.json`. Tests are also run on GitHub with Actions on each push.
