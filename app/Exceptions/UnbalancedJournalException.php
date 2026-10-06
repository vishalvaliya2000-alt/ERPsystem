<?php

namespace App\Exceptions;

use RuntimeException;

class UnbalancedJournalException extends RuntimeException
{
    public function __construct(float $totalDebit, float $totalCredit, string $message = '')
    {
        $diff = abs($totalDebit - $totalCredit);
        $defaultMessage = 'Unbalanced Journal Voucher: Total Debits (₹'.number_format($totalDebit, 2).') must equal Total Credits (₹'.number_format($totalCredit, 2).'). Discrepancy of ₹'.number_format($diff, 2).'.';

        parent::__construct($message ?: $defaultMessage);
    }
}
