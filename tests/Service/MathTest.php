<?php

declare(strict_types=1);

namespace ZurabMamasakhlisi\CommissionTask\Tests\Service;

use PHPUnit\Framework\TestCase;
use ZurabMamasakhlisi\CommissionTask\Service\Math;


class MathTest extends TestCase
{
    /**
     * @var Math
     */
    private $math;

    public function setUp():void
    {
        $this->math = new Math(2);

    }

    /**
     * @param string $filename
     * @param string $expectation
     *
     * @dataProvider dataProviderCalculateCommission
     */
    public function testCalculateCommission(string $expectation, string $filename='input.csv')
    {
        $this->math->loadConfig('config.ini');
        $this->math->fetchCurrencyRates('https://developers.paysera.com/tasks/api/currency-exchange-rates');
        $this->math->parseInputFile($filename);
        $this->math->sortTransactions();
        $this->math->calculateCommissionFees();
        //sort transactions to regain original input sequence
        $this->math->sortTransactionsById();
        $res = '';
        foreach ($this->math->transactionRecords as $record) {
            $res .= number_format($record->fee, 2, '.', '').PHP_EOL;
        }
        print(PHP_EOL.$res);
        $this->assertEquals(
            $expectation,
            $res
        );
    }

    public function dataProviderCalculateCommission(): array
    {
        return [
          'test commission calculation' => ['0.60'.PHP_EOL.'3.00'.PHP_EOL.'0.00'.PHP_EOL.'0.06'.PHP_EOL.'1.50'.PHP_EOL.'0.00'.PHP_EOL.'0.69'.PHP_EOL.'0.30'.PHP_EOL.'0.30'.PHP_EOL.'3.00'.PHP_EOL.'0.00'.PHP_EOL.'0.00'.PHP_EOL.'8607.39'.PHP_EOL, 'input.csv']
        ];
    }
}
