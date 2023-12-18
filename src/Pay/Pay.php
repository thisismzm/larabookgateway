<?php

namespace Larabookir\Gateway\Pay;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Pay extends PortAbstract implements PortInterface
{
	/**
	 * Address of main CURL server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://pay.ir/payment/send';

	/**
	 * Address of CURL server for verify payment
	 *
	 * @var string
	 */
	protected $serverVerifyUrl = 'https://pay.ir/payment/verify';

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://pay.ir/payment/gateway/';

	/**
	 * {@inheritdoc}
	 */
	public function set($amount)
	{
		$this->amount = $amount;

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
		return \Redirect::to($this->gateUrl . $this->refId);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);

		$this->userPayment();
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
			$this->callbackUrl = $this->config->get('gateway.pay.callback-url');

		return urlencode($this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]));
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws PaySendException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();

		$fields = array(
			'api' => $this->config->get('gateway.pay.api'),
			'amount' => $this->amount,
			'redirect' => $this->getCallback(),
			'factorNumber' => $this->refId(),
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->serverUrl);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);

		$response = json_decode($response);

		if (isset($response->status) && $response->status == 1) {
			$this->refId = $response->transId;
			$this->transactionSetRefId();

			return true;
		}

		$this->transactionFailed();
		$this->newLog($response->errorCode, $response->errorMessage);
		throw new PaySendException($response->errorCode);
	}

	/**
	 * Check user payment with GET data
	 *
	 * @return bool
	 *
	 * @throws PayReceiveException
	 */
	protected function userPayment()
	{
		$trackingCode = Request::get('trackingCode');
		$status = Request::get('status');

		if (is_numeric($status) && $status == 1) {
			$this->trackingCode = $trackingCode;
			return true;
		}

		$this->transactionFailed();
		$this->newLog(-5, PayReceiveException::$errors[-5]);
		throw new PayReceiveException(-5);
	}

	/**
	 * Verify user payment from zarinpal server
	 *
	 * @return bool
	 *
	 * @throws PayReceiveException
	 */
	protected function verifyPayment()
	{
		$fields = array(
			'api' => $this->config->get('gateway.pay.api'),
			'transId' => $this->refId
		);

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $this->serverVerifyUrl);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);
		curl_close($ch);
		$response = json_decode($response);

		if (isset($response->status) && $response->status == 1 && isset($response->amount) && $response->amount == $this->amount) {
			$this->transactionSucceed();
			$this->newLog($response->status, Enum::TRANSACTION_SUCCEED_TEXT);

			return true;
		}

		$this->transactionFailed();
		$this->newLog($response->errorCode, PayReceiveException::$errors[$response->errorCode]);
		throw new PayReceiveException($response->errorCode);
	}
}
