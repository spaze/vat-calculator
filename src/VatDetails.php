<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator;

class VatDetails
{

	/** @var bool */
	private $valid;

	/** @var string */
	private $countryCode;

	/** @var string */
	private $vatNumber;

	/** @var string|null */
	private $requestId;


	public function __construct(bool $valid, string $countryCode, string $vatNumber, ?string $requestId)
	{
		$this->valid = $valid;
		$this->countryCode = $countryCode;
		$this->vatNumber = $vatNumber;
		$this->requestId = $requestId;
	}


	public function isValid(): bool
	{
		return $this->valid;
	}


	public function getCountryCode(): string
	{
		return $this->countryCode;
	}


	public function getVatNumber(): string
	{
		return $this->vatNumber;
	}


	public function getRequestId(): ?string
	{
		return $this->requestId;
	}

}
