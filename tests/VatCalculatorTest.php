<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use PHPUnit_Framework_TestCase;
use SoapClient;
use SoapFault;
use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use stdClass;

class VatCalculatorTest extends PHPUnit_Framework_TestCase
{

	/** @var VatCalculator */
	private $vatCalculator;

	/** @var SoapClient */
	private $vatCheck;


	protected function setUp()
	{
		$this->vatCalculator = new VatCalculator(new VatRates());
		$this->vatCheck = $this->getMockFromWsdl(__DIR__ . '/checkVatService.wsdl', 'VatService');
	}


	public function testCalculateVatWithoutCountry()
	{
		$net = 25.00;

		$result = $this->vatCalculator->calculate($net);
		$this->assertEquals(25.00, $result);
	}


	public function testCalculateVatWithPredefinedRules()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$result = $this->vatCalculator->calculate($net, $countryCode);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryPreviousSet()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$this->vatCalculator->setCountryCode($countryCode);
		$result = $this->vatCalculator->calculate($net);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompany()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$postalCode = null;
		$company = true;

		$result = $this->vatCalculator->calculate($net, $countryCode, $postalCode, $company);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompanySet()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$company = true;

		$this->vatCalculator->setCompany($company);
		$result = $this->vatCalculator->calculate($net, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(24.00, $this->vatCalculator->getNetPrice());
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateVatWithCountryAndCompanyBothSet()
	{
		$net = 24.00;
		$countryCode = 'DE';
		$company = true;

		$this->vatCalculator->setCountryCode($countryCode);
		$this->vatCalculator->setCompany($company);
		$result = $this->vatCalculator->calculate($net);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testGetTaxRateForLocationWithCountry()
	{
		$countryCode = 'DE';

		$result = $this->vatCalculator->getTaxRateForLocation($countryCode);
		$this->assertEquals(0.19, $result);
	}


	public function testGetTaxRateForLocationWithCountryAndCompany()
	{
		$countryCode = 'DE';
		$company = true;

		$result = $this->vatCalculator->getTaxRateForLocation($countryCode, null, $company);
		$this->assertEquals(0, $result);
	}


	public function testCanValidateValidVatNumber()
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = null;

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->isValidVatNumber($vatNumber);
		$this->assertTrue($result);
	}


	public function testCanValidateInvalidVatNumber()
	{
		$result = new stdClass();
		$result->valid = false;
		$result->countryCode = 'So';
		$result->vatNumber = 'meInvalidNumber';
		$result->requestIdentifier = null;

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willReturn($result);

		$vatNumber = 'SomeInvalidNumber';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->isValidVatNumber($vatNumber);
		$this->assertFalse($result);
	}


	public function testValidateVatNumberThrowsExceptionOnSoapFailure()
	{
		$this->setExpectedException(VatCheckUnavailableException::class);
		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'So',
				'vatNumber' => 'meInvalidNumber',
				'requesterCountryCode' => null,
				'requesterVatNumber' => null,
			])
			->willThrowException(new SoapFault('Server', 'Something went wrong'));

		$vatNumber = 'SomeInvalidNumber';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$this->vatCalculator->isValidVatNumber($vatNumber);
	}


	public function testCanValidateValidVatNumberWithRequesterVatNumber()
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = 'FOOBAR338';

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => 'CZ',
				'requesterVatNumber' => '26168685',
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setBusinessVatNumber('CZ26168685');
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->getVatDetails($vatNumber);
		$this->assertTrue($result->isValid());
		$this->assertSame('DE', $result->getCountryCode());
		$this->assertSame('190098891', $result->getVatNumber());
		$this->assertSame('FOOBAR338', $result->getRequestId());
	}


	public function testCanValidateValidVatNumberWithRequesterVatNumberSet()
	{
		$result = new stdClass();
		$result->valid = true;
		$result->countryCode = 'DE';
		$result->vatNumber = '190098891';
		$result->requestIdentifier = 'FOOBAR338';

		$this->vatCheck->expects($this->any())
			->method('checkVatApprox')
			->with([
				'countryCode' => 'DE',
				'vatNumber' => '190098891',
				'requesterCountryCode' => 'CZ',
				'requesterVatNumber' => '00006947',
			])
			->willReturn($result);

		$vatNumber = 'DE 190 098 891';
		$this->vatCalculator->setSoapClient($this->vatCheck);
		$result = $this->vatCalculator->getVatDetails($vatNumber, 'CZ00006947');
		$this->assertTrue($result->isValid());
		$this->assertSame('DE', $result->getCountryCode());
		$this->assertSame('190098891', $result->getVatNumber());
		$this->assertSame('FOOBAR338', $result->getRequestId());
	}


	public function testCannotValidateVatNumberWhenServiceIsDown()
	{
		$this->setExpectedException(VatCheckUnavailableException::class);

		$vatNumber = 'SomeInvalidNumber';
		$this->vatCalculator->isValidVatNumber($vatNumber);
	}


	public function testCompanyInBusinessCountryGetsValidVatRateDirectSet()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$this->vatCalculator->setBusinessCountryCode('DE');
		$result = $this->vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());
	}


	public function testCompanyOutsideBusinessCountryGetsValidVatRate()
	{
		$net = 24.00;
		$countryCode = 'DE';

		$this->vatCalculator->setBusinessCountryCode('NL');
		$result = $this->vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $this->vatCalculator->getTaxValue());
	}


	public function testReturnsZeroForInvalidCountryCode()
	{
		$net = 24.00;
		$countryCode = 'XXX';

		$result = $this->vatCalculator->calculate($net, $countryCode, null, true);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $this->vatCalculator->getTaxValue());
	}


	public function testChecksPostalCodeForVatExceptions()
	{
		$net = 24.00;
		$postalCode = '27498'; // Heligoland
		$result = $this->vatCalculator->calculate($net, 'DE', $postalCode, false);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.00, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0.00, $this->vatCalculator->getTaxValue());

		$postalCode = '6691'; // Jungholz
		$result = $this->vatCalculator->calculate($net, 'AT', $postalCode, false);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());

		$postalCode = 'BFPO58'; // Dhekelia
		$result = $this->vatCalculator->calculate($net, 'GB', $postalCode, false);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());

		$postalCode = '9122'; // Madeira
		$result = $this->vatCalculator->calculate($net, 'PT', $postalCode, false);
		$this->assertEquals(29.28, $result);
		$this->assertEquals(0.22, $this->vatCalculator->getTaxRate());
		$this->assertEquals(5.28, $this->vatCalculator->getTaxValue());
	}


	public function testPostalCodesWithoutExceptionsGetStandardRate()
	{
		$net = 24.00;

		// Invalid post code
		$postalCode = 'IGHJ987ERT35';
		$result = $this->vatCalculator->calculate($net, 'ES', $postalCode, false);
		//Expect standard rate for Spain
		$this->assertEquals(29.04, $result);
		$this->assertEquals(0.21, $this->vatCalculator->getTaxRate());
		$this->assertEquals(5.04, $this->vatCalculator->getTaxValue());

		// Valid UK post code
		$postalCode = 'S1A 2AA';
		$result = $this->vatCalculator->calculate($net, 'GB', $postalCode, false);
		//Expect standard rate for UK
		$this->assertEquals(28.80, $result);
		$this->assertEquals(0.20, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.80, $this->vatCalculator->getTaxValue());
	}


	public function testShouldCollectVat()
	{
		$this->assertTrue($this->vatCalculator->shouldCollectVat('DE'));
		$this->assertTrue($this->vatCalculator->shouldCollectVat('NL'));
		$this->assertFalse($this->vatCalculator->shouldCollectVat(''));
		$this->assertFalse($this->vatCalculator->shouldCollectVat('XXX'));
	}


	public function testCalculateNetPriceWithoutCountry()
	{
		$gross = 25.00;

		$result = $this->vatCalculator->calculateNet($gross);
		$this->assertEquals(25.00, $result);
	}


	public function testCalculateNetPriceWithPredefinedRules()
	{
		$gross = 28.56;
		$countryCode = 'DE';

		$result = $this->vatCalculator->calculateNet($gross, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryPreviousSet()
	{
		$gross = 28.56;
		$countryCode = 'DE';

		$this->vatCalculator->setCountryCode($countryCode);

		$result = $this->vatCalculator->calculateNet($gross);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0.19, $this->vatCalculator->getTaxRate());
		$this->assertEquals(4.56, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompany()
	{
		$gross = 28.56;
		$countryCode = 'DE';
		$postalCode = null;
		$company = true;

		$result = $this->vatCalculator->calculateNet($gross, $countryCode, $postalCode, $company);
		$this->assertEquals(28.56, $result);
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompanySet()
	{
		$gross = 24.00;
		$countryCode = 'DE';
		$company = true;

		$this->vatCalculator->setCompany($company);
		$result = $this->vatCalculator->calculateNet($gross, $countryCode);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(24.00, $this->vatCalculator->getNetPrice());
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateNetPriceWithCountryAndCompanyBothSet()
	{
		$gross = 24.00;
		$countryCode = 'DE';
		$company = true;

		$this->vatCalculator->setCountryCode($countryCode);
		$this->vatCalculator->setCompany($company);
		$result = $this->vatCalculator->calculateNet($gross);
		$this->assertEquals(24.00, $result);
		$this->assertEquals(0, $this->vatCalculator->getTaxRate());
		$this->assertEquals(0, $this->vatCalculator->getTaxValue());
	}


	public function testCalculateHighVatType()
	{
		$gross = 24.00;
		$countryCode = 'NL';
		$company = false;
		$type = 'high';
		$postalCode = null;

		$result = $this->vatCalculator->calculate($gross, $countryCode, $postalCode, $company, $type);

		$this->assertEquals(29.04, $result);
	}


	public function testCalculateLowVatType()
	{
		$gross = 24.00;
		$countryCode = 'NL';
		$company = false;
		$type = 'low';
		$postalCode = null;

		$result = $this->vatCalculator->calculate($gross, $countryCode, $postalCode, $company, $type);

		$this->assertEquals(26.16, $result);
	}

}
