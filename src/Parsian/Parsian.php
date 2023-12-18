<?php

namespace Larabookir\Gateway\Parsian;

use Illuminate\Support\Facades\Request;
use SoapClient;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Parsian extends PortAbstract implements PortInterface
{
	/**
	 * Url of parsian gateway web service
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://pec.shaparak.ir/NewIPGServices/Sale/SaleService.asmx?wsdl';

	/**
     * Url of parsian verify gateway web service
     *
     * @var string
     */

	protected $verifyServerUrl = 'https://pec.shaparak.ir/NewIPGServices/Confirm/ConfirmService.asmx?wsdl';

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://pec.shaparak.ir/NewIPG/?Token=';

	/**
	 * {@inheritdoc}
	 */
	public function set($amount)
	{
		$this->amount = intval($amount);
		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function ready()
	{
		$this->sendPayRequest();

		return $this;
	}

	/**
	 * {@inheritdoc}
	 */
	public function redirect()
	{
		return \Redirect::to($this->gateUrl . $this->refId());
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->verifyPayment();

		return $this;
	}

	/**
	 * Sets callback url
	 * @param $url
	 */
	function setCallback($url)
	{
		$this->callbackUrl = $url;
		return $this;
	}

	/**
	 * Gets callback url
	 * @return string
	 */
	function getCallback()
	{
		if (!$this->callbackUrl)
			$this->callbackUrl = $this->config->get('gateway.parsian.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Send pay request to parsian gateway
	 *
	 * @return bool
	 *
	 * @throws ParsianErrorException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();
		$params = array(
			'requestData' => [
				'LoginAccount' => $this->config->get('gateway.parsian.pin'),
				'Amount' => $this->amount,
				'OrderId' => $this->transactionId(),
				'CallBackUrl' => $this->getCallback(),
			]
		);

		try {
			$soap = new SoapClient($this->serverUrl);
			$response = $soap->SalePaymentRequest($params);

		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}
		if ($response !== false && isset($response->SalePaymentRequestResult)) {
			$status = $response->SalePaymentRequestResult->Status;
			$token = $response->SalePaymentRequestResult->Token;

			if ($status == 0 && $token !== 0) {
				$this->refId = $token;
				$this->transactionSetRefId();
				return true;
			}

			$errorMessage = ParsianResult::errorMessage($status);
			$this->transactionFailed();
			$this->newLog($status, $errorMessage);
			throw new ParsianErrorException($errorMessage, $status);

		} else {
			$this->transactionFailed();
			$this->newLog(-1, 'خطا در اتصال به درگاه پارسیان');
			throw new ParsianErrorException('خطا در اتصال به درگاه پارسیان', -1);
		}
	}

	/**
	 * Verify payment
	 *
	 * @throws ParsianErrorException
	 */
	protected function verifyPayment()
	{
		if (!Request::has('status') && !Request::has('Token'))
			throw new ParsianErrorException('درخواست غیر معتبر', -1);

		$token = Request::get('Token');
		$status = Request::get('status');

		if ($status != 0) {
			$errorMessage = ParsianResult::errorMessage($status);
			$this->newLog($status, $errorMessage);
			throw new ParsianErrorException($errorMessage, $status);
		}

		if ($this->refId != $token)
			throw new ParsianErrorException('تراکنشی یافت نشد', -1);

		$params = array(
			'requestData' => [
				'LoginAccount' => $this->config->get('gateway.parsian.pin'),  
				'Token' => $token,
			]
		);

		try {
			$soap = new SoapClient($this->verifyServerUrl);
			$result = $soap->ConfirmPayment($params);
		} catch (\SoapFault $e) {
			throw new ParsianErrorException($e->getMessage(), -1);
		}

		if ($result === false || !isset($result->ConfirmPaymentResult->Status))
			throw new ParsianErrorException('پاسخ دریافتی از بانک نامعتبر است.', -1);

        $status = $result->ConfirmPaymentResult->Status;
		if ($status != 0) {
			$errorMessage = ParsianResult::errorMessage($status);
			$this->transactionFailed();
			$this->newLog($status, $errorMessage);
			throw new ParsianErrorException($errorMessage, $status);
		}

		$this->trackingCode = isset($result->ConfirmPaymentResult->RRN) ? $result->ConfirmPaymentResult->RRN : $token;
		$this->cardNumber = isset($result->ConfirmPaymentResult->CardNumberMasked) ? $result->ConfirmPaymentResult->CardNumberMasked : '';
		$this->transactionSucceed();
		$this->newLog($status, ParsianResult::errorMessage($status));
	}
}
