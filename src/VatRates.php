<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use DateTimeImmutable;
use DateTimeInterface;

class VatRates
{

	/** @var string */
	public const HIGH = 'high';

	/** @var string */
	public const LOW = 'low';

	/** @var null */
	public const GENERAL = null;

	/**
	 * All available tax rules and their exceptions.
	 *
	 * Taken from: http://ec.europa.eu/taxation_customs/resources/documents/taxation/vat/how_vat_works/rates/vat_rates_en.pdf
	 *
	 * @var array<string, array>
	 */
	private $taxRules = [
		'AT' => [ // Austria
			'rate' => 0.20,
			'exceptions' => [
				'Jungholz' => 0.19,
				'Mittelberg' => 0.19,
			],
		],
		'BE' => [ // Belgium
			'rate' => 0.21,
		],
		'BG' => [ // Bulgaria
			'rate' => 0.20,
		],
		'CY' => [ // Cyprus
			'rate' => 0.19,
		],
		'CZ' => [ // Czech Republic
			'rate' => 0.21,
		],
		'DE' => [ // Germany
			'rate' => 0.19,
			'since' => [
				'2021-01-01 00:00:00 Europe/Berlin' => [
					'rate' => 0.19,
				],
				'2020-07-01 00:00:00 Europe/Berlin' => [
					'rate' => 0.16,
				],
			],
			'exceptions' => [
				'Heligoland' => 0,
				'Büsingen am Hochrhein' => 0,
			],
		],
		'DK' => [ // Denmark
			'rate' => 0.25,
		],
		'EE' => [ // Estonia
			'rate' => 0.20,
		],
		'EL' => [ // Hellenic Republic (Greece)
			'rate' => 0.24,
			'exceptions' => [
				'Mount Athos' => 0,
			],
		],
		'ES' => [ // Spain
			'rate' => 0.21,
			'exceptions' => [
				'Canary Islands' => 0,
				'Ceuta' => 0,
				'Melilla' => 0,
			],
		],
		'FI' => [ // Finland
			'rate' => 0.24,
		],
		'FR' => [ // France
			'rate' => 0.20,
			'exceptions' => [
				// Overseas France
				'Reunion' => 0.085,
				'Martinique' => 0.085,
				'Guadeloupe' => 0.085,
				'Guyane' => 0,
				'Mayotte' => 0,
			],
		],
		'GB' => [ // United Kingdom
			'rate' => 0.20,
			'exceptions' => [
				// UK RAF Bases in Cyprus are taxed at Cyprus rate
				'Akrotiri' => 0.19,
				'Dhekelia' => 0.19,
			],
		],
		'GR' => [ // Greece
			'rate' => 0.24,
			'exceptions' => [
				'Mount Athos' => 0,
			],
		],
		'HR' => [ // Croatia
			'rate' => 0.25,
		],
		'HU' => [ // Hungary
			'rate' => 0.27,
		],
		'IE' => [ // Ireland
			'rate' => 0.23,
			'since' => [
				'2021-03-01 00:00:00 Europe/Dublin' => [
					'rate' => 0.23,
				],
				'2020-09-01 00:00:00 Europe/Dublin' => [
					'rate' => 0.21,
				],
			],
		],
		'IT' => [ // Italy
			'rate' => 0.22,
			'exceptions' => [
				'Campione d\'Italia' => 0,
				'Livigno' => 0,
			],
		],
		'LT' => [ // Lithuania
			'rate' => 0.21,
		],
		'LU' => [ // Luxembourg
			'rate' => 0.17,
		],
		'LV' => [ // Latvia
			'rate' => 0.21,
		],
		'MT' => [ // Malta
			'rate' => 0.18,
		],
		'NL' => [ // Netherlands
			'rate' => 0.21,
			'rates' => [
				self::HIGH => 0.21,
				self::LOW => 0.09,
			],
		],
		'PL' => [ // Poland
			'rate' => 0.23,
		],
		'PT' => [ // Portugal
			'rate' => 0.23,
			'exceptions' => [
				'Azores' => 0.18,
				'Madeira' => 0.22,
			],
		],
		'RO' => [ // Romania
			'rate' => 0.19,
		],
		'SE' => [ // Sweden
			'rate' => 0.25,
		],
		'SI' => [ // Slovenia
			'rate' => 0.22,
		],
		'SK' => [ // Slovakia
			'rate' => 0.20,
		],

		// Countries associated with EU countries that have a special VAT rate
		'MC' => [ // Monaco France
			'rate' => 0.20,
		],
		'IM' => [ // Isle of Man - United Kingdom
			'rate' => 0.20,
		],

		// Non-EU with their own VAT requirements
		'CH' => [ // Switzerland
			'rate' => 0.077,
			'rates' => [
				self::HIGH => 0.077,
				self::LOW => 0.025,
			],
		],
	];

	/**
	 * Optional tax rules.
	 *
	 * Non-EU countries with their own VAT requirements, countries in this list
	 * need to be added manually by `addRateForCountry()` for the rate to be applied.
	 *
	 * @var array<string, array>
	 */
	private $optionalTaxRules = [
		'NO' => [ // Norway
			'rate' => 0.25,
		],
		'TR' => [ // Turkey
			'rate' => 0.18,
		],
	];

	/**
	 * All possible postal code exceptions.
	 *
	 * @var array<string, array>
	 */
	private $postalCodeExceptions = [
		'AT' => [
			[
				'postalCode' => '/^6691$/',
				'code' => 'AT',
				'name' => 'Jungholz',
			],
			[
				'postalCode' => '/^699[123]$/',
				'city' => '/\bmittelberg\b/i',
				'code' => 'AT',
				'name' => 'Mittelberg',
			],
		],
		'CH' => [
			[
				'postalCode' => '/^8238$/',
				'code' => 'DE',
				'name' => 'Büsingen am Hochrhein',
			],
			[
				'postalCode' => '/^6911$/',
				'code' => 'IT',
				'name' => "Campione d'Italia",
			],
			// The Italian city of Domodossola has a Swiss post office also
			[
				'postalCode' => '/^3907$/',
				'code' => 'IT',
			],
		],
		'DE' => [
			[
				'postalCode' => '/^87491$/',
				'code' => 'AT',
				'name' => 'Jungholz',
			],
			[
				'postalCode' => '/^8756[789]$/',
				'city' => '/\bmittelberg\b/i',
				'code' => 'AT',
				'name' => 'Mittelberg',
			],
			[
				'postalCode' => '/^78266$/',
				'code' => 'DE',
				'name' => 'Büsingen am Hochrhein',
			],
			[
				'postalCode' => '/^27498$/',
				'code' => 'DE',
				'name' => 'Heligoland',
			],
		],
		'ES' => [
			[
				'postalCode' => '/^(5100[1-5]|5107[0-1]|51081)$/',
				'code' => 'ES',
				'name' => 'Ceuta',
			],
			[
				'postalCode' => '/^(5200[0-6]|5207[0-1]|52081)$/',
				'code' => 'ES',
				'name' => 'Melilla',
			],
			[
				'postalCode' => '/^(35\d{3}|38\d{3})$/',
				'code' => 'ES',
				'name' => 'Canary Islands',
			],
		],
		'FR' => [
			[
				'postalCode' => '/^971\d{2,}$/',
				'code' => 'FR',
				'name' => 'Guadeloupe',
			],
			[
				'postalCode' => '/^972\d{2,}$/',
				'code' => 'FR',
				'name' => 'Martinique',
			],
			[
				'postalCode' => '/^973\d{2,}$/',
				'code' => 'FR',
				'name' => 'Guyane',
			],
			[
				'postalCode' => '/^974\d{2,}$/',
				'code' => 'FR',
				'name' => 'Reunion',
			],
			[
				'postalCode' => '/^976\d{2,}$/',
				'code' => 'FR',
				'name' => 'Mayotte',
			],
		],
		'GB' => [
			// Akrotiri
			[
				'postalCode' => '/^BFPO57|BF12AT$/',
				'code' => 'CY',
			],
			// Dhekelia
			[
				'postalCode' => '/^BFPO58|BF12AU$/',
				'code' => 'CY',
			],
		],
		'GR' => [
			[
				'postalCode' => '/^63086$/',
				'code' => 'GR',
				'name' => 'Mount Athos',
			],
		],
		'IT' => [
			[
				'postalCode' => '/^22060$/',
				'city' => '/\bcampione\b/i',
				'code' => 'IT',
				'name' => "Campione d'Italia",
			],
			[
				'postalCode' => '/^23030$/',
				'city' => '/\blivigno\b/i',
				'code' => 'IT',
				'name' => 'Livigno',
			],
		],
		'PT' => [
			[
				'postalCode' => '/^9[0-4]\d{2,}$/',
				'code' => 'PT',
				'name' => 'Madeira',
			],
			[
				'postalCode' => '/^9[5-9]\d{2,}$/',
				'code' => 'PT',
				'name' => 'Azores',
			],
		],
	];


	/** @var DateTimeImmutable */
	private $now;


	public function __construct()
	{
		$this->now = new DateTimeImmutable();
	}


	public function addRateForCountry(string $country): void
	{
		$country = strtoupper($country);
		$this->taxRules[$country] = $this->optionalTaxRules[$country] ?? null;
	}


	public function shouldCollectVat(string $countryCode): bool
	{
		return isset($this->taxRules[strtoupper($countryCode)]);
	}


	/**
	 * Returns the tax rate for the given country code.
	 * If a postal code is provided, it will try to lookup the different
	 * postal code exceptions that are possible. Specify a date to use VAT rate valid for that date.
	 *
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param string|null $type
	 * @param DateTimeInterface|null $date Date to use the VAT rate for, null for current date
	 * @return float
	 */
	public function getTaxRateForLocation(string $countryCode, ?string $postalCode, ?string $type = self::GENERAL, ?DateTimeInterface $date = null): float
	{
		$countryCode = strtoupper($countryCode);
		if (isset($this->postalCodeExceptions[$countryCode]) && $postalCode !== null) {
			foreach ($this->postalCodeExceptions[$countryCode] as $postalCodeException) {
				if (!preg_match($postalCodeException['postalCode'], $postalCode)) {
					continue;
				}
				if (isset($postalCodeException['name'])) {
					return $this->taxRules[$postalCodeException['code']]['exceptions'][$postalCodeException['name']];
				}
				return $this->getRules($postalCodeException['code'], $date)['rate'];
			}
		}

		if ($type !== VatRates::GENERAL) {
			return isset($this->taxRules[$countryCode]['rates'][$type]) ? $this->taxRules[$countryCode]['rates'][$type] : 0;
		}

		return $this->getRules($countryCode, $date)['rate'];
	}


	private function getRules(string $countryCode, ?DateTimeInterface $date = null): array
	{
		if (!isset($this->taxRules[$countryCode])) {
			return ['rate' => 0];
		}
		if (isset($this->taxRules[$countryCode]['since'])) {
			foreach ($this->taxRules[$countryCode]['since'] as $since => $rates) {
				if (new DateTimeImmutable($since) <= ($date ?? $this->now)) {
					return $rates;
				}
			}
		}
		return $this->taxRules[$countryCode];
	}

}
