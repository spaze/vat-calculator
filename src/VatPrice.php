<?php
declare(strict_types = 1);


namespace Spaze\VatCalculator;


class VatPrice
{

	/** @var float */
	private $netPrice;

	/** @var float */
	private $price;

	/** @var float */
	private $taxValue;

	/** @var float */
	private $taxRate;


	public function __construct(float $netPrice, float $price, float $taxValue, float $taxRate)
	{
		$this->netPrice = $netPrice;
		$this->price = $price;
		$this->taxValue = $taxValue;
		$this->taxRate = $taxRate;
	}


	public function getNetPrice(): float
	{
		return $this->netPrice;
	}


	public function getPrice(): float
	{
		return $this->price;
	}


	public function getTaxValue(): float
	{
		return $this->taxValue;
	}


	public function getTaxRate(): float
	{
		return $this->taxRate;
	}

}
