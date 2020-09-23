<?php

namespace Larabookir\Gateway\Parsian;


class ParsianResult
{

	public static $errors = array(
		-32768 => 'خطاي ناشناخته رخ داده است UnkownError',
		-1552 => 'برگشت تراکنش مجاز نمی باشد', //PaymentRequestIsNotEligibleToReversal
		-1551 => 'برگشت تراکنش قبلا اًنجام شده است', //PaymentRequestIsAlreadyReversed
		-1550 => 'برگشت تراکنش در وضعیت جاري امکان پذیر نمی باشد',// PaymentRequestStatusIsNotReversalable 
		-1549 => 'زمان مجاز براي درخواست برگشت تراکنش به اتمام رسیده است', //MaxAllowedTimeToReversalHasExceeded
		-1548 => 'فراخوانی سرویس درخواست پرداخت قبض ناموفق بود', // BillPaymentRequestServiceFailed
		-1540 => 'تایید تراکنش ناموفق می باشد ', //InvalidConfirmRequestService
		-1533 => 'تراکنش قبلاً تایید شده است ', //PaymentIsAlreadyConfirmed
		-1532 => 'تراکنش از سوي پذیرنده تایید شد', //MerchantHasConfirmedPaymentRequest
		-1531 => 'تایید تراکنش ناموفق امکان پذیر نمی باشد', //CannotConfirmNonSuccessfulPayment
		-1530 => 'پذیرنده مجاز به تایید این تراکنش نمی باشد', //MerchantConfirmPaymentRequestAccessVaio
		-1528 => 'اطلاعات پرداخت یافت نشد', //ConfirmPaymentRequestInfoNotFound
		-1527 => 'انجام عملیات درخواست پرداخت تراکنشخرید ناموفق بود', //CallSalePaymentRequestServiceFailed
		-1507 => 'تراکنش برگشت به سوئیچ ارسال شد ', //ReversalCompleted
		-1505 => 'تایید تراکنش توسط پذیرنده انجام شد ', //PaymentConfirmRequested
		-138 => 'عملیات پرداخت توسط کاربر لغو شد ', //CanceledByUser
		-132 => 'مبلغ تراکنش کمتر از حداقل مجاز می باشد ', //InvalidMinimumPaymentAmount
		-131 => 'نامعتبر می باشد Token', //InvalidToken
		-130 => 'زمان منقضی شده است Token', //TokenIsExpired
		-128 => 'معتبر نمی باشد IP قالب آدرس', //InvalidIpAddressFormat
		-127 => 'آدرس اینترنتی معتبر نمی باشد', //InvalidMerchantIp
		-126 => 'کد شناسایی پذیرنده معتبر نمی باشد', //InvalidMerchantPin
		-121 => 'رشته داده شده بطور کامل عددي نمی باشد', //InvalidStringIsNumeric
		-120 => 'طول داده ورودي معتبر نمی باشد', //InvalidStringIsNumeric
		-119 => 'سازمان نامعتبر می باشد', //InvalidOrganizationId
		-118 => 'مقدار ارسال شده عدد نمی باشد', //ValueIsNotNumeric
		-117 => 'طول رشته کم تر از حد مجاز می باشد', //LenghtIsLessOfMinimum
		-116 => 'طول رشته بیش از حد مجاز می باشد', //LenghtIsMoreOfMaximum
		-115 => 'شناسه پرداخت نامعتبر می باشد', //InvalidPayId
		-114 => 'شناسه قبض نامعتبر می باشد', //InvalidBillId
		-113 => 'پارامتر ورودي خالی می باشد', //ValueIsNull
		-112 => 'شماره سفارش تکراري است', //OrderIdDuplicated
		-111 => 'مبلغ تراکنش بیش از حد مجاز پذیرنده می باشد', //InvalidMerchantMaxTransAmount
		-108 => 'قابلیت برگشت تراکنش براي پذیرنده غیر فعال می باشد', //ReverseIsNotEnabled
		-107 => 'قابلیت ارسال تاییده تراکنش براي پذیرنده غیر فعال می باشد', //AdviceIsNotEnabled
		-106 => 'قابلیت شارژ براي پذیرنده غیر فعال می باشد', //ChargeIsNotEnabled
		-105 => 'قابلیت تاپ آپ براي پذیرنده غیر فعال می باشد', //TopupIsNotEnabled
		-103 => 'قابلیت خرید براي پذیرنده غیر فعال می باشد', //SaleIsNotEnabled
		-102 => 'تراکنش با موفقیت برگشت داده شد', //ReverseSuccessful
		-101 => 'پذیرنده اهراز هویت نشد', //MerchantAuthenticationFailed
		-100 => 'پذیرنده غیرفعال می باشد', //MerchantIsNotActive
		-1 => 'خطاي سرور', //Server Error
		0 => 'عملیات موفق می باشد', //Successful
		1 => 'صادرکننده ي کارت از انجام تراکنش صرف نظر کرد', 
		2 => 'عملیات تاییدیه این تراکنش قبلا باموفقیت صورت پذیرفته است', 
		3 => 'پذیرنده ي فروشگاهی نامعتبر می باشد', //Invalid Merchant
		5 => 'از انجام تراکنش صرف نظر شد', //Do Not Honour
		6 => 'بروز خطایی ناشناخته', //Error
		8 => 'باتشخیص هویت دارنده ي کارت، تراکنش موفق می باشد', //Honour With Identification
		9 => 'درخواست رسیده در حال پی گیري و انجام است', //Request Inprogress
		10 => 'تراکنش با مبلغی پایین تر از مبلغ درخواستی', //Approved For Partial Amount
		13 => 'مبلغ تراکنش نادرست است', //Invalid Amount
		14 => 'شماره کارت ارسالی نامعتبر است (وجود ندارد)', //Invalid Card Number
		30 => 'قالب پیام داراي اشکال است ', //Format Error
		40 => 'عملیات درخواستی پشتیبانی نمی گردد ', //Requested Function is not supported 
		51 => 'موجودي کافی نمی باشد ', 
		54 => 'تاریخ انقضاي کارت سپري شده است ',
		55 => 'رمز کارت نا معتبر است ',
		56 => 'کارت نا معتبر است ',
	);

	public static function errorMessage($errorId)
	{
		return isset(self::$errors[$errorId])?self::$errors[$errorId] : $errorId;
	}
}
