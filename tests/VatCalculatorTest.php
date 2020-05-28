<?php

namespace Spaze\VatCalculator;

use PHPUnit_Framework_TestCase;
use SoapFault;
use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use stdClass;

function file_get_contents($url)
{
	return VatCalculatorTest::$file_get_contents_result ?: \file_get_contents($url);
}

class VatCalculatorTest extends PHPUnit_Framework_TestCase
{
	public static $file_get_contents_result;


	public function testCalculateVatWithoutCountry()
	{
		$net = 25.00;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($net);
		$this->assertEquals(25.00, $result);
	}


	public function testCalculateVatWithPredefinedRules()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($net, $countryCode);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryPreviousSet()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCountryCode($countryCode);
		$result = $vatCalculator->calculate($net);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompany()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$postalCode = null;
		$company = true;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($net, $countryCode, $postalCode, $company);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompanySet()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCompany($company);
		$result = $vatCalculator->calculate($net, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(24.00, $vatCalculator->getNetPrice());
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompanyBothSet()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCountryCode($countryCode);
		$vatCalculator->setCompany($company);
		$result = $vatCalculator->calculate($net);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testGetTaxRateForLocationWithCountry()
	{
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->getTaxRateForLocation($countryCode);
		$this->assertEquals(0.19, $result);
	}


	public function testGetTaxRateForCountry()
	{
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->getTaxRateForCountry($countryCode);
		$this->assertEquals(0.19, $result);
	}


	public function testGetTaxRateForLocationWithCountryAndCompany()
	{
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->getTaxRateForLocation($countryCode, null, $company);
		$this->assertEquals(0, $result);
	}


	public function testGetTaxRateForCountryAndCompany()
	{
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->getTaxRateForCountry($countryCode, $company);
		$this->assertEquals(0, $result);
	}


	public function testCanValidateValidVatNumber()
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';

		$vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
		$vatCheck->expects($this->any())
			->method('checkVat')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$vatCalculator = new VatCalculator();
		$vatCalculator->setSoapClient($vatCheck);
		$result = $vatCalculator->isValidVatNumber($vatNumber);
		$this->assertTrue($result);
	}


	public function testCanValidateInvalidVatNumber()
	{
		$result = new stdClass();
		$result->valid = false;
		$result->countryCode = 'So';
		$result->vatNumber = 'meInvalidNumber';

		$vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
		$vatCheck->expects($this->any())
			->method('checkVat')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
			])
			->willReturn($result);

		$vatNumber = 'SomeInvalidNumber';
		$vatCalculator = new VatCalculator();
		$vatCalculator->setSoapClient($vatCheck);
		$result = $vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testValidateVatNumberReturnsFalseOnSoapFailure()
	{
		$vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
		$vatCheck->expects($this->any())
			->method('checkVat')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
			])
			->willThrowException(new SoapFault('Server', 'Something went wrong'));

		$vatNumber = 'SomeInvalidNumber';
		$vatCalculator = new VatCalculator();
		$vatCalculator->setSoapClient($vatCheck);
		$result = $vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testValidateVatNumberReturnsFalseOnSoapFailureWithoutForwarding()
	{
		$vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
		$vatCheck->expects($this->any())
			->method('checkVat')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
			])
			->willThrowException(new SoapFault('Server', 'Something went wrong'));

		$vatNumber = 'SomeInvalidNumber';
		$vatCalculator = new VatCalculator();
		$vatCalculator->setSoapClient($vatCheck);
		$result = $vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testValidateVatNumberThrowsExceptionOnSoapFailure()
	{
		$this->setExpectedException(VatCheckUnavailableException::class);
		$vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
		$vatCheck->expects($this->any())
			->method('checkVat')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
			])
			->willThrowException(new SoapFault('Server', 'Something went wrong'));

		$vatNumber = 'SomeInvalidNumber';
		$vatCalculator = new VatCalculator();
		$vatCalculator->forwardSoapFaults();
		$vatCalculator->setSoapClient($vatCheck);
		$vatCalculator->isValidVatNumber($vatNumber);
	}


	public function testCannotValidateVatNumberWhenServiceIsDown()
	{
		$this->setExpectedException(\Spaze\VatCalculator\Exceptions\VatCheckUnavailableException::class);

		$vatCalculator = new class() extends VatCalculator {
			public function initSoapClient(): void
			{
				return;
			}
		};

		$vatNumber = 'SomeInvalidNumber';
		$vatCalculator->isValidVatNumber($vatNumber);
	}


	public function testCompanyInBusinessCountryGetsValidVatRateDirectSet()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$vatCalculator->setBusinessCountryCode('DE');
		$result = $vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());
	}


	public function testCompanyOutsideBusinessCountryGetsValidVatRate()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$vatCalculator->setBusinessCountryCode('NL');
		$result = $vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $vatCalculator->getTaxValue());
	}


	public function testReturnsZeroForInvalidCountryCode()
	{
		$net = 24.00;
		$countryCode = 'XXX';

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $vatCalculator->getTaxValue());
	}


	public function testChecksPostalCodeForVatExceptions()
	{
		$net = 24.00;
		$vatCalculator = new VatCalculator();
		$postalCode = '27498'; // Heligoland
		$result = $vatCalculator->calculate($net, 'DE', $postalCode, false);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $vatCalculator->getTaxValue());

		$postalCode = '6691'; // Jungholz
		$result = $vatCalculator->calculate($net, 'AT', $postalCode, false);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());

		$postalCode = 'BFPO58'; // Dhekelia
		$result = $vatCalculator->calculate($net, 'GB', $postalCode, false);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());

		$postalCode = '9122'; // Madeira
		$result = $vatCalculator->calculate($net, 'PT', $postalCode, false);
		$this->assertEquals(29.28, $result);
		$this->assertEquals(0.22, $vatCalculator->getTaxRate());
		$this->assertEquals(5.28, $vatCalculator->getTaxValue());
	}


	public function testPostalCodesWithoutExceptionsGetStandardRate()
	{
		$net = 24.00;
		$vatCalculator = new VatCalculator();

		// Invalid post code
		$postalCode = 'IGHJ987ERT35';
		$result = $vatCalculator->calculate($net, 'ES', $postalCode, false);
		//Expect standard rate for Spain
		$this->assertEquals(29.04, $result);
		$this->assertEquals(0.21, $vatCalculator->getTaxRate());
		$this->assertEquals(5.04, $vatCalculator->getTaxValue());

		// Valid UK post code
		$postalCode = 'S1A 2AA';
		$result = $vatCalculator->calculate($net, 'GB', $postalCode, false);
		//Expect standard rate for UK
		$this->assertEquals(28.80, $result);
		$this->assertEquals(0.20, $vatCalculator->getTaxRate());
		$this->assertEquals(4.80, $vatCalculator->getTaxValue());
	}


	public function testShouldCollectVat()
	{
		$vatCalculator = new VatCalculator();
		$this->assertTrue($vatCalculator->shouldCollectVat('DE'));
		$this->assertTrue($vatCalculator->shouldCollectVat('NL'));
		$this->assertFalse($vatCalculator->shouldCollectVat(''));
		$this->assertFalse($vatCalculator->shouldCollectVat('XXX'));
	}


	public function testCalculateNetPriceWithoutCountry()
	{
		$gross = 25.00;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculateNet($gross);
		$this->assertEquals(25.00, $result);
	}


	public function testCalculateNetPriceWithPredefinedRules()
	{
		$gross = 28.56;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculateNet($gross, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryPreviousSet()
	{
		$gross = 28.56;
		$countryCode = 'DE';

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCountryCode($countryCode);

		$result = $vatCalculator->calculateNet($gross);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.19, $vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompany()
	{
		$gross = 28.56;
		$countryCode = 'DE';
		$postalCode = null;
		$company = true;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculateNet($gross, $countryCode, $postalCode, $company);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompanySet()
	{
		$gross = 24.00;
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCompany($company);
		$result = $vatCalculator->calculateNet($gross, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(24.00, $vatCalculator->getNetPrice());
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompanyBothSet()
	{
		$gross = 24.00;
		$countryCode = 'DE';
		$company = true;

		$vatCalculator = new VatCalculator();
		$vatCalculator->setCountryCode($countryCode);
		$vatCalculator->setCompany($company);
		$result = $vatCalculator->calculateNet($gross);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $vatCalculator->getTaxRate());
		$this->assertEquals(0, $vatCalculator->getTaxValue());
	}


	public function testCalculateHighVatType()
	{
		$gross = 24.00;
		$countryCode = 'NL';
		$company = false;
		$type = 'high';
		$postalCode = null;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($gross, $countryCode, $postalCode, $company, $type);

		$this->assertEquals(29.04, $result);
	}


	public function testCalculateLowVatType()
	{
		$gross = 24.00;
		$countryCode = 'NL';
		$company = false;
		$type = 'low';
		$postalCode = null;

		$vatCalculator = new VatCalculator();
		$result = $vatCalculator->calculate($gross, $countryCode, $postalCode, $company, $type);

		$this->assertEquals(26.16, $result);
	}
}
