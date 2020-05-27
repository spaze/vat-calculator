<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

class VatDetails
{

    /** @var bool */
    private $valid;

    /** @var string */
    private $countryCode;

    /** @var string */
    private $vatNumber;


    public function __construct(bool $valid, string $countryCode, string $vatNumber)
    {
        $this->valid = $valid;
        $this->countryCode = $countryCode;
        $this->vatNumber = $vatNumber;
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

}
