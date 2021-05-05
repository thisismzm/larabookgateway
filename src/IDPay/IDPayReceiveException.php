<?php

namespace Larabookir\Gateway\Idpay;

use Larabookir\Gateway\Exceptions\BankException;

class IdpayReceiveException extends BankException
{
    public static $errors = array(
        '1' => 'پرداخت انجام نشده است',
        '2' => 'پرداخت ناموفق بوده است',
        '3' => 'خطا رخ داده است',
        '4' => 'بلوکه شده',
        '5' => 'برگشت به پرداخت کننده',
        '6' => 'برگشت خورده سیستمی',
        '7' => 'انصراف از پرداخت',
        '8' => 'به درگاه پرداخت منتقل شد',
        '10' => 'در انتظار تایید پرداخت',
        '100' => 'پرداخت تایید شده است',
        '101' => 'پرداخت قبلا تایید شده است',
        '200' => 'به دریافت کننده واریز شد',
    );

    public function __construct($errorId)
    {
        $this->errorId = $errorId;

        parent::__construct(@self::$errors[$errorId].' #'.$errorId, $errorId);
    }
}
