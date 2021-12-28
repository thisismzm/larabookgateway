<?php

namespace Larabookir\Gateway\Payping;

use Larabookir\Gateway\Exceptions\BankException;

class PaypingException extends BankException
{
    public function __construct($errorId, $errorMessage)
    {
        parent::__construct($errorMessage, $errorId);
    }
}
