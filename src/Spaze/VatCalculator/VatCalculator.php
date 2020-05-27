<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use SoapClient;
use SoapFault;

class VatCalculator
{

    /** @var string */
    public const VAT_HIGH = 'high';

    /** @var string */
    public const VAT_LOW = 'low';

    /** @var null */
    public const VAT_GENERAL = null;

    /**
     * VAT Service check URL provided by the EU.
     *
     * @var string
     */
    const VAT_SERVICE_URL = 'http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /**
     * We're using the free ip2c service to lookup IP 2 country.
     *
     * @var string
     */
    const GEOCODE_SERVICE_URL = 'http://ip2c.org/';

    /** @var SoapClient */
    private $soapClient;

    /**
     * All available tax rules and their exceptions.
     *
     * Taken from: http://ec.europa.eu/taxation_customs/resources/documents/taxation/vat/how_vat_works/rates/vat_rates_en.pdf
     *
     * @var array<string, array>
     */
    private $taxRules = [
        'AT' => [ // Austria
            'rate'       => 0.20,
            'exceptions' => [
                'Jungholz'   => 0.19,
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
            'rate'       => 0.19,
            'exceptions' => [
                'Heligoland'            => 0,
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
            'rate'       => 0.24,
            'exceptions' => [
                'Mount Athos' => 0,
            ],
        ],
        'ES' => [ // Spain
            'rate'       => 0.21,
            'exceptions' => [
                'Canary Islands' => 0,
                'Ceuta'          => 0,
                'Melilla'        => 0,
            ],
        ],
        'FI' => [ // Finland
            'rate' => 0.24,
        ],
        'FR' => [ // France
            'rate'       => 0.20,
            'exceptions' => [
                // Overseas France
                'Reunion'    => 0.085,
                'Martinique' => 0.085,
                'Guadeloupe' => 0.085,
                'Guyane'     => 0,
                'Mayotte'    => 0,
            ],
        ],
        'GB' => [ // United Kingdom
            'rate'       => 0.20,
            'exceptions' => [
                // UK RAF Bases in Cyprus are taxed at Cyprus rate
                'Akrotiri' => 0.19,
                'Dhekelia' => 0.19,
            ],
        ],
        'GR' => [ // Greece
            'rate'       => 0.24,
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
        ],
        'IT' => [ // Italy
            'rate'       => 0.22,
            'exceptions' => [
                'Campione d\'Italia' => 0,
                'Livigno'            => 0,
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
                self::VAT_HIGH => 0.21,
                self::VAT_LOW => 0.09,
            ],
        ],
        'PL' => [ // Poland
            'rate' => 0.23,
        ],
        'PT' => [ // Portugal
            'rate'       => 0.23,
            'exceptions' => [
                'Azores'  => 0.18,
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
                self::VAT_HIGH => 0.077,
                self::VAT_LOW => 0.025,
            ],
        ],
        'TR' => [ // Turkey
            'rate' => 0.18,
        ],
        'NO' => [ // Norway
            'rate' => 0.25,
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
                'code'       => 'AT',
                'name'       => 'Jungholz',
            ],
            [
                'postalCode' => '/^699[123]$/',
                'city'       => '/\bmittelberg\b/i',
                'code'       => 'AT',
                'name'       => 'Mittelberg',
            ],
        ],
        'CH' => [
            [
                'postalCode' => '/^8238$/',
                'code'       => 'DE',
                'name'       => 'Büsingen am Hochrhein',
            ],
            [
                'postalCode' => '/^6911$/',
                'code'       => 'IT',
                'name'       => "Campione d'Italia",
            ],
            // The Italian city of Domodossola has a Swiss post office also
            [
                'postalCode' => '/^3907$/',
                'code'       => 'IT',
            ],
        ],
        'DE' => [
            [
                'postalCode' => '/^87491$/',
                'code'       => 'AT',
                'name'       => 'Jungholz',
            ],
            [
                'postalCode' => '/^8756[789]$/',
                'city'       => '/\bmittelberg\b/i',
                'code'       => 'AT',
                'name'       => 'Mittelberg',
            ],
            [
                'postalCode' => '/^78266$/',
                'code'       => 'DE',
                'name'       => 'Büsingen am Hochrhein',
            ],
            [
                'postalCode' => '/^27498$/',
                'code'       => 'DE',
                'name'       => 'Heligoland',
            ],
        ],
        'ES' => [
            [
                'postalCode' => '/^(5100[1-5]|5107[0-1]|51081)$/',
                'code'       => 'ES',
                'name'       => 'Ceuta',
            ],
            [
                'postalCode' => '/^(5200[0-6]|5207[0-1]|52081)$/',
                'code'       => 'ES',
                'name'       => 'Melilla',
            ],
            [
                'postalCode' => '/^(35\d{3}|38\d{3})$/',
                'code'       => 'ES',
                'name'       => 'Canary Islands',
            ],
        ],
        'FR' => [
            [
                'postalCode' => '/^971\d{2,}$/',
                'code'       => 'FR',
                'name'       => 'Guadeloupe',
            ],
            [
                'postalCode' => '/^972\d{2,}$/',
                'code'       => 'FR',
                'name'       => 'Martinique',
            ],
            [
                'postalCode' => '/^973\d{2,}$/',
                'code'       => 'FR',
                'name'       => 'Guyane',
            ],
            [
                'postalCode' => '/^974\d{2,}$/',
                'code'       => 'FR',
                'name'       => 'Reunion',
            ],
            [
                'postalCode' => '/^976\d{2,}$/',
                'code'       => 'FR',
                'name'       => 'Mayotte',
            ],
        ],
        'GB' => [
            // Akrotiri
            [
                'postalCode' => '/^BFPO57|BF12AT$/',
                'code'       => 'CY',
            ],
            // Dhekelia
            [
                'postalCode' => '/^BFPO58|BF12AU$/',
                'code'       => 'CY',
            ],
        ],
        'GR' => [
            [
                'postalCode' => '/^63086$/',
                'code'       => 'GR',
                'name'       => 'Mount Athos',
            ],
        ],
        'IT' => [
            [
                'postalCode' => '/^22060$/',
                'city'       => '/\bcampione\b/i',
                'code'       => 'IT',
                'name'       => "Campione d'Italia",
            ],
            [
                'postalCode' => '/^23030$/',
                'city'       => '/\blivigno\b/i',
                'code'       => 'IT',
                'name'       => 'Livigno',
            ],
        ],
        'PT' => [
            [
                'postalCode' => '/^9[0-4]\d{2,}$/',
                'code'       => 'PT',
                'name'       => 'Madeira',
            ],
            [
                'postalCode' => '/^9[5-9]\d{2,}$/',
                'code'       => 'PT',
                'name'       => 'Azores',
            ],
        ],
    ];

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

    /** @var bool */
    private $forwardSoapFaults = false;


    private function getClientIp(): ?string
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $clientIpAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $clientIpAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $clientIpAddress = null;
        }

        return $clientIpAddress;
    }


    /**
     * Returns the ISO 3166-1 alpha-2 two letter
     * country code for the client IP. If the
     * IP can't be resolved it returns false.
     */
    public function getIpBasedCountry(): ?string
    {
        $ip = $this->getClientIp();
        $url = self::GEOCODE_SERVICE_URL.$ip;
        $result = file_get_contents($url);
        switch ($result[0]) {
            case '1':
                $data = explode(';', $result);

                return $data[1];
                break;
            default:
                return null;
        }
    }


    public function shouldCollectVat(string $countryCode): bool
    {
        return isset($this->taxRules[strtoupper($countryCode)]);
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
    public function calculate(float $netPrice, ?string $countryCode = null, ?string $postalCode = null, ?bool $company = null, ?string $type = self::VAT_GENERAL): float
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
        $this->taxRate = $this->getTaxRateForLocation($this->getCountryCode(), $this->getPostalCode(), $this->isCompany(), $type);
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
    public function calculateNet(float $gross, ?string $countryCode = null, ?string $postalCode = null, ?bool $company = null, ?string $type = self::VAT_GENERAL): float
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
        $this->taxRate = $this->getTaxRateForLocation($this->getCountryCode(), $this->getPostalCode(), $this->isCompany(), $type);
        $this->taxValue = $this->taxRate > 0 ? $value / (1 + $this->taxRate) * $this->taxRate : 0;
        $this->netPrice = $value - $this->taxValue;

        return $this->netPrice;
    }


    public function getNetPrice(): float
    {
        return $this->netPrice;
    }


    public function getCountryCode(): string
    {
        return strtoupper($this->countryCode);
    }


    public function setCountryCode(string $countryCode): void
    {
        $this->countryCode = $countryCode;
    }


    public function getPostalCode(): string
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
        $this->businessCountryCode = $businessCountryCode;
    }


    public function forwardSoapFaults(): void
    {
        $this->forwardSoapFaults = true;
    }


    /**
     * Returns the tax rate for the given country code.
     * This method is used to allow backwards compatibility.
     *
     * @param string $countryCode
     * @param bool $company
     * @param string|null $type
     * @return float
     */
    public function getTaxRateForCountry(string $countryCode, bool $company = false, ?string $type = null): float
    {
        return $this->getTaxRateForLocation($countryCode, null, $company, $type);
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
        if ($company && strtoupper($countryCode) !== strtoupper($this->businessCountryCode)) {
            return 0;
        }

        if (isset($this->postalCodeExceptions[$countryCode]) && $postalCode !== null) {
            foreach ($this->postalCodeExceptions[$countryCode] as $postalCodeException) {
                if (!preg_match($postalCodeException['postalCode'], $postalCode)) {
                    continue;
                }
                if (isset($postalCodeException['name'])) {
                    return $this->taxRules[$postalCodeException['code']]['exceptions'][$postalCodeException['name']];
                }

                return $this->taxRules[$postalCodeException['code']]['rate'];
            }
        }

        if ($type !== null) {
            return isset($this->taxRules[strtoupper($countryCode)]['rates'][$type]) ? $this->taxRules[strtoupper($countryCode)]['rates'][$type] : 0;
        }

        return isset($this->taxRules[strtoupper($countryCode)]['rate']) ? $this->taxRules[strtoupper($countryCode)]['rate'] : 0;
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
     * @return VatDetails|null
     * @throws VatCheckUnavailableException
     */
    public function getVatDetails(string $vatNumber): ?VatDetails
    {
        $vatNumber = str_replace([' ', "\xC2\xA0", "\xA0", '-', '.', ','], '', trim($vatNumber));
        $countryCode = substr($vatNumber, 0, 2);
        $vatNumber = substr($vatNumber, 2);
        $this->initSoapClient();
        $client = $this->soapClient;
        if ($client) {
            try {
                $result = $client->checkVat([
                    'countryCode' => $countryCode,
                    'vatNumber' => $vatNumber,
                ]);
                return new VatDetails($result->valid, $result->countryCode, $result->vatNumber);
            } catch (SoapFault $e) {
                if ($this->forwardSoapFaults) {
                    throw new VatCheckUnavailableException($e->getMessage(), $e->getCode(), $e->getPrevious());
                }

                return null;
            }
        }
        throw new VatCheckUnavailableException('The VAT check service is currently unavailable. Please try again later.');
    }


    /**
     * @throws VatCheckUnavailableException
     */
    public function initSoapClient(): void
    {
        if (is_object($this->soapClient) || $this->soapClient === false) {
            return;
        }
        try {
            $this->soapClient = new SoapClient(self::VAT_SERVICE_URL);
        } catch (SoapFault $e) {
            if ($this->forwardSoapFaults) {
                throw new VatCheckUnavailableException($e->getMessage(), $e->getCode(), $e->getPrevious());
            }

            $this->soapClient = false;
        }
    }


    public function setSoapClient(SoapClient $soapClient): void
    {
        $this->soapClient = $soapClient;
    }

}
