<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use DateTimeInterface;
use SoapClient;
use SoapFault;
use Spaze\VatCalculator\Exceptions\InvalidCharsInVatNumberException;
use Spaze\VatCalculator\Exceptions\UnsupportedCountryException;
use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use stdClass;

class VatCalculator
{

	/**
	 * VAT Service check URL provided by the EU.
	 */
	public const VAT_SERVICE_URL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

	/** @var SoapClient */
	private $soapClient;

	/** @var VatRates */
	private $vatRates;

	/** @var string */
	private $businessCountryCode;

	/** @var string */
	private $businessVatNumber;

	/** @var float */
	private $timeout;


	public function __construct(VatRates $vatRates, ?string $businessCountryCode = null, ?string $businessVatNumber = null, ?float $timeout = null)
	{
		trigger_error('Hi, please stop using spaze/vat-calculator, it is not supported anymore, the VAT rates are incorrect. Use https://github.com/driesvints/vat-calculator (the original) or https://github.com/JakubJachym/vat-calculator (fork of this fork, more features than the original) and for more info see https://github.com/spaze/vat-calculator/releases/tag/v3.6.7', E_USER_WARNING);
		$this->vatRates = $vatRates;
		if ($businessCountryCode) {
			$this->setBusinessCountryCode($businessCountryCode);
		}
		if ($businessVatNumber) {
			$this->setBusinessVatNumber($businessVatNumber);
		}
		$this->timeout = $timeout ?? (float)ini_get('default_socket_timeout');
	}


	public function shouldCollectVat(string $countryCode): bool
	{
		return $this->vatRates->shouldCollectVat($countryCode);
	}


	public function shouldCollectEuVat(string $countryCode): bool
	{
		return $this->vatRates->shouldCollectEuVat($countryCode);
	}


	/**
	 * Calculate the VAT based on the net price, country code and indication if the
	 * customer is a company or not. Specify a date to use VAT rate valid for that date.
	 *
	 * @param float $netPrice
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param bool $company
	 * @param string|null $type
	 * @param DateTimeInterface|null $date Date to use the VAT rate for, null for current date
	 * @return VatPrice
	 */
	public function calculate(float $netPrice, string $countryCode, ?string $postalCode, bool $company, ?string $type = VatRates::GENERAL, ?DateTimeInterface $date = null): VatPrice
	{
		$taxRate = $this->getTaxRateForLocation($countryCode, $postalCode, $company, $type, $date);
		$taxValue = $taxRate * $netPrice;
		return new VatPrice($netPrice, $netPrice + $taxValue, $taxValue, $taxRate);
	}


	/**
	 * Calculate the net price on the gross price, country code and indication if the
	 * customer is a company or not. Specify a date to use VAT rate valid for that date.
	 *
	 * @param float $gross
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param bool $company
	 * @param string|null $type
	 * @param DateTimeInterface|null $date Date to use the VAT rate for, null for current date
	 * @return VatPrice
	 */
	public function calculateNet(float $gross, string $countryCode, ?string $postalCode, bool $company, ?string $type = VatRates::GENERAL, ?DateTimeInterface $date = null): VatPrice
	{
		$taxRate = $this->getTaxRateForLocation($countryCode, $postalCode, $company, $type, $date);
		$taxValue = $taxRate > 0 ? $gross / (1 + $taxRate) * $taxRate : 0;
		return new VatPrice($gross - $taxValue, $gross, $taxValue, $taxRate);
	}


	public function setBusinessCountryCode(string $businessCountryCode): void
	{
		$this->businessCountryCode = strtoupper($businessCountryCode);
	}


	public function setBusinessVatNumber(string $businessVatNumber): void
	{
		$this->businessVatNumber = $businessVatNumber;
	}


	/**
	 * Returns the tax rate for the given country code.
	 * If a postal code is provided, it will try to lookup the different
	 * postal code exceptions that are possible. Specify a date to use VAT rate valid for that date.
	 *
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param bool $company
	 * @param string|null $type
	 * @param DateTimeInterface|null $date Date to use the VAT rate for, null for current date
	 * @return float
	 */
	public function getTaxRateForLocation(string $countryCode, ?string $postalCode, bool $company, ?string $type = null, ?DateTimeInterface $date = null): float
	{
		if ($company && strtoupper($countryCode) !== $this->businessCountryCode) {
			return 0;
		}

		return $this->vatRates->getTaxRateForLocation($countryCode, $postalCode, $type, $date);
	}


	/**
	 * @param string $vatNumber
	 * @return bool
	 * @throws VatCheckUnavailableException
	 * @throws UnsupportedCountryException
	 * @throws InvalidCharsInVatNumberException
	 */
	public function isValidVatNumber(string $vatNumber): bool
	{
		return $this->getVatDetails($vatNumber)->isValid();
	}


	/**
	 * @param string $vatNumber
	 * @param string|null $requesterVatNumber
	 * @return VatDetails
	 * @throws VatCheckUnavailableException
	 * @throws UnsupportedCountryException
	 * @throws InvalidCharsInVatNumberException
	 */
	public function getVatDetails(string $vatNumber, ?string $requesterVatNumber = null): VatDetails
	{
		$vatNumber = str_replace([' ', "\xC2\xA0", "\xA0", '-', '.', ','], '', trim($vatNumber));

		// Regex from https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl sans the quantifiers
		$invalidChars = preg_split('/[0-9A-Za-z+*.]+/', $vatNumber, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
		if ($invalidChars) {
			throw new InvalidCharsInVatNumberException($invalidChars, $vatNumber);
		}

		$countryCode = substr($vatNumber, 0, 2);
		$vatNumber = substr($vatNumber, 2);

		if (!$this->shouldCollectEuVat($countryCode)) {
			throw new UnsupportedCountryException($countryCode);
		}

		try {
			if ($this->soapClient === null) {
				$this->soapClient = new SoapClient(
					self::VAT_SERVICE_URL,
					[
						'stream_context' => stream_context_create([
							'http' => [
								'timeout' => $this->timeout,
							],
						]),
					],
				);
			}
			if ($requesterVatNumber === null) {
				$requesterVatNumber = $this->businessVatNumber;
			}

			/** @var stdClass $result */
			$result = $this->soapClient->checkVatApprox([
				'countryCode' => $countryCode,
				'vatNumber' => $vatNumber,
				'requesterCountryCode' => $requesterVatNumber ? substr($requesterVatNumber, 0, 2) : null,
				'requesterVatNumber' => $requesterVatNumber ? substr($requesterVatNumber, 2) : null,
			]);
			return new VatDetails($result->valid, $result->countryCode, $result->vatNumber, $result->requestIdentifier);
		} catch (SoapFault $e) {
			throw new VatCheckUnavailableException($e->getMessage(), $e->getCode(), $e);
		}
	}


	public function setSoapClient(SoapClient $soapClient): void
	{
		$this->soapClient = $soapClient;
	}

}
