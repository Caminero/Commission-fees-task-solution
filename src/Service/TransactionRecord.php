<?php

declare(strict_types=1);

namespace ZurabMamasakhlisi\CommissionTask\Service;


class TransactionRecord {
    public string $date;
    public string $userId;
    public string $userType;
    public string $transType;
    public string $amount;
    public string $currency;
    //special id of transaction for retaining original input sequence
    public int $id;
    public float $fee;
    public static int $idCounter = 1;

    public function  __construct(array $record)
    {
        $this->id = self::$idCounter++;
        $this->date = $record[0];
        $this->userId = $record[1];
        $this->userType = $record[2];
        $this->transType = $record[3];
        $this->amount = $record[4];
        $this->currency = $record[5];
        $this->fee = 0.00;

    }

    public function __toString(): string
    {
        return $this->userId.' '.$this->date.' '.$this->userType.' '.$this->transType.' '.$this->amount.' '.$this->currency.' '.$this->fee;
    }

}