<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator\Exceptions;

use Throwable;

class InvalidCharsInVatNumberException extends VatNumberException
{

	/** @var array<integer, string> */
	private $invalidChars = [];


	/**
	 * @param array<integer, array{0: string, 1:integer}> $invalidChars
	 * @param string $vatNumber
	 * @param Throwable|null $previous
	 */
	public function __construct(array $invalidChars, string $vatNumber, Throwable $previous = null)
	{
		$chars = [];
		foreach ($invalidChars as $invalidChar) {
			$chars[] = sprintf('%s (0x%s) at offset %d', $invalidChar[0], bin2hex($invalidChar[0]), $invalidChar[1]);
			$this->invalidChars[$invalidChar[1]] = $invalidChar[0];
		}
		parent::__construct("VAT number {$vatNumber} contains invalid characters: " . implode(', ', $chars), 0, $previous);
	}


	/**
	 * @return array<integer, string> byte offset => character
	 */
	public function getInvalidChars(): array
	{
		return $this->invalidChars;
	}

}
