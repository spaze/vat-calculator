<?php
declare(strict_types = 1);

namespace JakubJachym\VatCalculator\Exceptions;

use Throwable;

class UnsupportedCountryException extends VatNumberException
{

	public function __construct(string $countryCode, int $code = 0, Throwable $previous = null)
	{
		parent::__construct('Unsupported/non-EU country ' . $countryCode, $code, $previous);
	}

}
