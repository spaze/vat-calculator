<?php

namespace Spaze\VatCalculator\tests;

use Illuminate\Support\Facades\Validator;
use Mockery as m;
use Spaze\VatCalculator\Exceptions\VatCheckUnavailableException;
use Spaze\VatCalculator\Facades\VatCalculator;
use Spaze\VatCalculator\VatCalculatorServiceProvider;
use Orchestra\Testbench\TestCase;

class VatCalculatorValidatorExtensionTest extends TestCase
{
    protected $translator;
    protected $data;
    protected $rules;
    protected $messages;

    public function tearDown()
    {
        parent::tearDown();
        m::close();
        VatCalculator::clearResolvedInstances();
    }

    protected function getPackageProviders($app)
    {
        return [VatCalculatorServiceProvider::class];
    }

    public function testValidatesVatNumber()
    {
        $vatNumber = 'DE 190 098 891';

        VatCalculator::shouldReceive('isValidVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturnTrue();

        $validator = Validator::make(['vat_number' => $vatNumber], ['vat_number' => 'required|vat_number']);

        $this->assertTrue($validator->passes());
    }

    public function testValidatesInvalidVatNumber()
    {
        $vatNumber = '098 891';

        VatCalculator::shouldReceive('isValidVatNumber')
            ->with($vatNumber)
            ->once()
            ->andReturnFalse();

        $validator = Validator::make(['vat_number' => $vatNumber], ['vat_number' => 'required|vat_number']);

        $this->assertTrue($validator->fails());
    }

    public function testValidatesUnavailableVatNumberCheck()
    {
        $vatNumber = '098 891';

        VatCalculator::shouldReceive('isValidVatNumber')
            ->with($vatNumber)
            ->once()
            ->andThrow(new VatCheckUnavailableException());

        $validator = Validator::make(['vat_number' => $vatNumber], ['vat_number' => 'required|vat_number']);

        $this->assertTrue($validator->fails());
    }

    public function testDefaultErrorMessageWorks()
    {
        $vatNumber = '098 891';

        VatCalculator::shouldReceive('isValidVatNumber')
            ->with($vatNumber)
            ->once()
            ->andThrow(new VatCheckUnavailableException());

        $validator = Validator::make(['vat_number' => $vatNumber], ['vat_number' => 'required|vat_number']);

        $errors = $validator->errors()->toArray();

        $this->assertArrayHasKey('vat_number', $errors);
        $this->assertEquals($errors['vat_number'][0], 'vat number is not a valid VAT ID number.');
    }
}
