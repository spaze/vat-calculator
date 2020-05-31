<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator;

use PHPUnit_Framework_TestCase;

class VatRatesTest extends PHPUnit_Framework_TestCase
{

	/** @var VatRates */
	private $vatRates;


	protected function setUp()
	{
		$this->vatRates = new VatRates();
	}


	public function testAddRateKnownCountry(): void
	{
		$country = 'nO';
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
		$this->vatRates->addRateForCountry($country);
		$this->assertTrue($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0.25, $this->vatRates->getTaxRateForLocation($country, null));
	}


	public function testAddRateUnknownCountry(): void
	{
		$country = 'yEs';
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
		$this->vatRates->addRateForCountry($country);
		$this->assertFalse($this->vatRates->shouldCollectVat($country));
		$this->assertEquals(0, $this->vatRates->getTaxRateForLocation($country, null));
	}

}
