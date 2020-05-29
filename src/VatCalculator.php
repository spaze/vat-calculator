<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use SoapClient;
use SoapFault;

class VatCalculator
{

	/**
	 * VAT Service check URL provided by the EU.
	 *
	 * @var string
	 */
	const VAT_SERVICE_URL = 'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

	/** @var SoapClient */
	private $soapClient;

	/** @var VatRates */
	private $vatRates;

	/** @var float */
	private $netPrice = 0.0;

	/** @var string */
	private $countryCode;

	/** @var string */
	private $postalCode;

	/** @var float */
	private $taxValue = 0;

	/** @var float */
	private $taxRate = 0;

	/** @var bool */
	private $company = false;

	/** @var string */
	private $businessCountryCode;

	/** @var string */
	private $businessVatNumber;


	public function __construct(VatRates $vatRates)
	{
		$this->vatRates = $vatRates;
	}


	public function shouldCollectVat(string $countryCode): bool
	{
		return $this->vatRates->shouldCollectVat($countryCode);
	}


	/**
	 * Calculate the VAT based on the net price, country code and indication if the
	 * customer is a company or not.
	 *
	 * @param float $netPrice
	 * @param string|null $countryCode
	 * @param string|null $postalCode
	 * @param bool|null $company
	 * @param string|null $type
	 * @return float
	 */
	public function calculate(float $netPrice, ?string $countryCode = null, ?string $postalCode = null, ?bool $company = null, ?string $type = VatRates::GENERAL): float
	{
		if ($countryCode) {
			$this->setCountryCode($countryCode);
		}
		if ($postalCode) {
			$this->setPostalCode($postalCode);
		}
		if (!is_null($company) && $company !== $this->isCompany()) {
			$this->setCompany($company);
		}
		$this->netPrice = floatval($netPrice);
		$this->taxRate = $this->getCountryCode() === null ? 0 : $this->getTaxRateForLocation($this->getCountryCode(), $this->getPostalCode(), $this->isCompany(), $type);
		$this->taxValue = $this->taxRate * $this->netPrice;
		return $this->netPrice + $this->taxValue;
	}


	/**
	 * Calculate the net price on the gross price, country code and indication if the
	 * customer is a company or not.
	 *
	 * @param float $gross
	 * @param string|null $countryCode
	 * @param string|null $postalCode
	 * @param bool|null $company
	 * @param string|null $type
	 * @return float
	 */
	public function calculateNet(float $gross, ?string $countryCode = null, ?string $postalCode = null, ?bool $company = null, ?string $type = VatRates::GENERAL): float
	{
		if ($countryCode) {
			$this->setCountryCode($countryCode);
		}
		if ($postalCode) {
			$this->setPostalCode($postalCode);
		}
		if (!is_null($company) && $company !== $this->isCompany()) {
			$this->setCompany($company);
		}

		$value = floatval($gross);
		$this->taxRate = $this->getCountryCode() === null ? 0 : $this->getTaxRateForLocation($this->getCountryCode(), $this->getPostalCode(), $this->isCompany(), $type);
		$this->taxValue = $this->taxRate > 0 ? $value / (1 + $this->taxRate) * $this->taxRate : 0;
		$this->netPrice = $value - $this->taxValue;

		return $this->netPrice;
	}


	public function getNetPrice(): float
	{
		return $this->netPrice;
	}


	public function getCountryCode(): ?string
	{
		return $this->countryCode ? strtoupper($this->countryCode) : null;
	}


	public function setCountryCode(string $countryCode): void
	{
		$this->countryCode = $countryCode;
	}


	public function getPostalCode(): ?string
	{
		return $this->postalCode;
	}


	public function setPostalCode(string $postalCode): void
	{
		$this->postalCode = $postalCode;
	}


	public function getTaxRate(): float
	{
		return $this->taxRate;
	}


	public function isCompany(): bool
	{
		return $this->company;
	}


	public function setCompany(bool $company): void
	{
		$this->company = $company;
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
	 * postal code exceptions that are possible.
	 *
	 * @param string $countryCode
	 * @param string|null $postalCode
	 * @param bool $company
	 * @param string|null $type
	 * @return float
	 */
	public function getTaxRateForLocation(string $countryCode, ?string $postalCode = null, bool $company = false, ?string $type = null): float
	{
		if ($company && strtoupper($countryCode) !== $this->businessCountryCode) {
			return 0;
		}

		return $this->vatRates->getTaxRateForLocation($countryCode, $postalCode, $type);
	}


	public function getTaxValue(): float
	{
		return $this->taxValue;
	}


	/**
	 * @param string $vatNumber
	 * @return bool
	 * @throws VatCheckUnavailableException
	 */
	public function isValidVatNumber(string $vatNumber): bool
	{
		$details = $this->getVatDetails($vatNumber);

		if ($details) {
			return $details->isValid();
		} else {
			return false;
		}
	}


	/**
	 * @param string $vatNumber
	 * @param string|null $requesterVatNumber
	 * @return VatDetails
	 * @throws VatCheckUnavailableException
	 */
	public function getVatDetails(string $vatNumber, ?string $requesterVatNumber = null): VatDetails
	{
		$vatNumber = str_replace([' ', "\xC2\xA0", "\xA0", '-', '.', ','], '', trim($vatNumber));
		$countryCode = substr($vatNumber, 0, 2);
		$vatNumber = substr($vatNumber, 2);
		try {
			if ($this->soapClient === null) {
				$this->soapClient = new SoapClient(self::VAT_SERVICE_URL);
			}
			if ($requesterVatNumber === null) {
				$requesterVatNumber = $this->businessVatNumber;
			}

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
