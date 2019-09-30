<?php

namespace Larabookir\Gateway\Saderat;

use Larabookir\Gateway\Exceptions\BankException;

class SaderatException extends BankException
{
	public static $errors = array(
		-1 => 'تراکنش پیدا نشد.',
		-2 => 'تراکنش قبلا Reverse شده است.',
		-3 => 'Total Error خطای عمومی – خطای Exception ها',
		-4 => 'امکان انجام درخواست برای این تراکنش وجود ندارد.',
		-5 => 'آدرس IP نامعتبر میباشد ) IP در لیست آدرسهای معرفی شده توسط پذیرنده موجود نمیباشد(',
		-6 => 'عدم فعال بودن سرویس برگشت تراکنش برای پذیرنده',
	);

	public function __construct($errorId)
	{
		$this->errorId = intval($errorId);

		parent::__construct(@self::$errors[$this->errorId].' #'.$this->errorId, $this->errorId);
	}
}
