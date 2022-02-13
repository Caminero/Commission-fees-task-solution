<?php

/*
 * Main Script for solving the task
 */

declare(strict_types=1);

namespace ZurabMamasakhlisi\CommissionTask\Service;

use ZurabMamasakhlisi\CommissionTask\Service\TransactionRecord;

class Math
{
    private int $scale;
    public $currencyRates;
    public array $transactionRecords;
    public array $config;

    public function __construct(int $scale)
    {
        $this->scale = $scale;
    }

    public function loadConfig(string $file): void
    {
        $this->config = parse_ini_file($file,true);
    }

    public function add(string $leftOperand, string $rightOperand): string
    {
        return bcadd($leftOperand, $rightOperand, $this->scale);
    }

    public function divide(string $leftOperand, string $rightOperand): string
    {
        return bcdiv($leftOperand, $rightOperand, $this->scale);
    }

    public function multiply(string $leftOperand, string $rightOperand): string
    {
        return bcmul($leftOperand, $rightOperand, $this->scale);
    }

    public function fetchCurrencyRates(string $url):void
    {
        $json = file_get_contents($url);
        $this->currencyRates = json_decode($json);
    }

    public function getRate(string $currency): float
    {
        return $this->currencyRates->rates->$currency;
    }

    public function parseInputFile(string $filename): void
    {
        $lines = [];
        $file = fopen(__DIR__."\\".$filename,"r");
        while (($line = fgetcsv($file)) !== FALSE) {
            $lines[] = $line;
            $this->transactionRecords[] = new TransactionRecord( $line);
        }
        fclose($file);
    }

    /*
     * Transactions being sorted by userid ASC, transactiontype DESC, date ASC
     */
    public function sortTransactions(): void
    {
        usort($this->transactionRecords, function (TransactionRecord $a, TransactionRecord $b): int {
            if($a->userId === $b->userId) {
                if($b->transType==$a->transType) {
                    return $a->date <=> $b->date;
                }
                return $b->transType <=> $a->transType;
            }
            return $a->userId <=> $b->userId;
        });
    }

    public function sortTransactionsById(): void
    {
        usort($this->transactionRecords, function (TransactionRecord $a, TransactionRecord $b): int {
                return $a->id <=> $b->id;
        });

    }

    public function daysBetweenDates(string $day1, string $day2): int
    {
        $days_elapsed = date_diff(date_create(date($day1)), date_create($day2)) -> days;
        return $days_elapsed;
    }

    public function dayOfWeek(string $date): int
    {
        $unixTimestamp = strtotime($date);
        $dayOfWeek = date("w", $unixTimestamp);
        return intval($dayOfWeek);
    }

    public function amountInEuros(TransactionRecord $trans): float
    {
        if($trans->currency !== "EUR") {
            $amountInEuros = $this->divide($trans->amount, strval($this->getRate($trans->currency)));
            return floatval($amountInEuros);
        }
        return floatval($trans->amount);

    }

    public function amountInOrigCurrency(float $amount, string $currency): float
    {
        if($currency !== "EUR") {
            return $amount * $this->getRate($currency);
        }
        else {
            return $amount;
        }
    }

    public function calculateCommissionFees(): void
    {
        $prevTrans = NULL;
        $userWeekWithdrawCount = [];
        $userWeekWithdrawAmount = [];
        foreach($this->transactionRecords as $trans) {
            // deposits have fixed rate
            if ($trans->transType == 'deposit') {
                $rate = $this->config['rates']['deposit'];
                $trans->fee = ($trans->amount/100)*$rate;
            }
            else if ($trans->transType == 'withdraw') {
                // business clients have fixed rate
                if ($trans->userType == 'business') {
                    $rate = $this->config['rates']['business_withdraw'];
                    $trans->fee = ($trans->amount/100)*$rate;
                }
                else if ($trans->userType == 'private') {
                    $rate = $this->config['rates']['private_withdraw'];
                    $amountInEuros = $this->amountInEuros($trans);
                    // current withdraw transaction
                    $currTrans = $trans;
                    // if for current user this is first withdraw transaction
                    if ($prevTrans == NULL) {
                        $userWeekWithdrawCount[$trans->userId] = 1;
                        $userWeekWithdrawAmount[$trans->userId] = $amountInEuros;
                        if ($userWeekWithdrawAmount[$trans->userId] > 1000.00) {
                            $charged = $amountInEuros - 1000.00;
                            $trans->fee = ($this->amountInOrigCurrency($charged,$currTrans->currency) / 100) * $rate;
                            //next withdraw charged at regular rate
                            $userWeekWithdrawCount[$trans->userId] = 4;
                        }
                        else {
                            $trans->fee = 0;
                        }
                        //previous withdraw transaction
                        $prevTrans = $trans;
                    }
                    // user prevoiusly had withdraw transaction
                    else if ($prevTrans->userId == $currTrans->userId) {
                        $currTransDayOfWeek = $this->dayOfWeek($currTrans->date);
                        $prevtransDayofWeek = $this->dayOfWeek($prevTrans->date);
                        // checking if same week as for previuos transaction
                        if ($this->daysBetweenDates($prevTrans->date,$currTrans->date) < 7 and 0 <= abs($currTransDayOfWeek - $prevtransDayofWeek)) {
                            $userWeekWithdrawCount[$trans->userId]++;
                            $userWeekWithdrawAmount[$trans->userId] += $amountInEuros;
                            if ($userWeekWithdrawCount[$trans->userId] <= 3 and $userWeekWithdrawAmount[$trans->userId] <= 1000.00) {
                                $trans->fee = 0;
                            }
                            else  {
                                if($userWeekWithdrawCount[$trans->userId] <= 3 and $userWeekWithdrawAmount[$trans->userId] > 1000.00) {
                                    $charged = $userWeekWithdrawAmount[$trans->userId] - 1000.00;
                                    //next withdraw charged at regular rate
                                    $userWeekWithdrawCount[$trans->userId] = 4;
                                }
                                else  {
                                    $charged = $amountInEuros;
                                }
                                $trans->fee = ($this->amountInOrigCurrency($charged,$currTrans->currency) / 100) * $rate;
                                $trans->fee = round($trans->fee,2);
                            }
                        }
                        // new week
                        else {
                            $userWeekWithdrawCount[$trans->userId] = 1;
                            $userWeekWithdrawAmount[$trans->userId] = $amountInEuros;
                                if ($userWeekWithdrawAmount[$trans->userId] > 1000.00) {
                                    $charged = $amountInEuros - 1000.00;
                                    $trans->fee = ($this->amountInOrigCurrency($charged,$currTrans->currency) / 100) * $rate;
                                    $trans->fee = round($trans->fee,2);
                                    //next withdraw charged at regular rate
                                    $userWeekWithdrawCount[$trans->userId] = 4;
                                }
                                else {
                                    $trans->fee = 0;
                                }

                        }
                        $prevTrans = $trans;;
                    }
                    // prevoius withdraw transaction was for other user
                    else {
                        $prevTrans = NULL;
                        $userWeekWithdrawCount[$trans->userId] = 1;
                        $userWeekWithdrawAmount[$trans->userId] = $amountInEuros;
                        if ($userWeekWithdrawAmount[$trans->userId] > 1000.00) {
                            $charged = $amountInEuros - 1000.00;
                            $trans->fee = ($this->amountInOrigCurrency($charged,$currTrans->currency) / 100) * $rate;
                            $trans->fee = round($trans->fee,2);
                        } else {
                            $trans->fee = 0;
                        }
                    }

                }
            }

        }

    }
}


//$mymath = new Math(2);
//$mymath->loadConfig('config.ini');
//$mymath->fetchCurrencyRates('https://developers.paysera.com/tasks/api/currency-exchange-rates');
//$filename = $argv[1];
//$mymath->parseInputFile($filename);
//$results = $mymath->getTransactionsByUserId(4);
//var_dump($results);
