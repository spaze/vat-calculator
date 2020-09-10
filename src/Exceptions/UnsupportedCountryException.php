<?php
declare(strict_types = 1);

namespace Spaze\VatCalculator\Exceptions;

use Exception;
use Throwable;

class UnsupportedCountryException extends Exception
{

	public function __construct(string $countryCode, int $code = 0, Throwable $previous = null)
	{
		parent::__construct('Unsupported/non-EU country ' . $countryCode, $code, $previous);
	}

}
