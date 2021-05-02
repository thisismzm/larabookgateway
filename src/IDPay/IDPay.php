<?php

namespace Larabookir\Gateway\IDPay;

use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class IDPay extends PortAbstract implements PortInterface
{
	/**
	 * Address of main CURL server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://api.idpay.ir/v1.1/payment';

	/**
	 * Address of CURL server for verify payment
	 *
	 * @var string
	 */
	protected $serverVerifyUrl = 'https://api.idpay.ir/v1.1/payment/verify';

	/**
	 * Address of CURL server for verify payment
	 *
	 * @var string
	 */
	protected $gatewayUrl;

	/**
	 * Payment Name
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Payment Description
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Payer Email Address
	 *
	 * @var string
	 */
	protected $email;

	/**
	 * Payer Mobile Number
	 *
	 * @var string
	 */
	protected $mobileNumber;

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
		return \Redirect::to($this->gatewayUrl);
	}

	/**
	 * {@inheritdoc}
	 */
	public function verify($transaction)
	{
		parent::verify($transaction);
		$this->userVerify();
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
			$this->callbackUrl = $this->config->get('gateway.IDPay.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws IDPaySendException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();

		$params = array(
			'order_id' => $this->transactionId(),
			'amount' => $this->amount,
			'name' => $this->name ? $this->name : $this->config->get('gateway.IDPay.name', ''),
			'phone' => $this->mobileNumber ? $this->mobileNumber : $this->config->get('gateway.IDPay.mobile', ''),
			'mail' => $this->email ? $this->email :$this->config->get('gateway.IDPay.email', ''),
			'desc' => $this->description ? $this->description : $this->config->get('gateway.IDPay.description', ''),
			'callback' => $this->getCallback()
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverUrl);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'X-API-KEY: ' . $this->config->get('gateway.IDPay.API_KEY'),
			'X-SANDBOX: ' . intval($this->config->get('gateway.IDPay.sandbox', '0'))
		));
		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		$response = json_decode($response);

		if ($httpcode == 201) {
			$this->refId = $response->id;
			$this->gatewayUrl = $response->link;
			$this->transactionSetRefId();
			return true;
		}

		$this->transactionFailed();
		$this->newLog($response->error_code, $response->error_message);
		throw new IDPaySendException($response->error_code);
	}

	/**
	 * Check user payment with GET data
	 *
	 * @return bool
	 *
	 * @throws PayReceiveException
	 */
	protected function userVerify()
	{
		$status = Input::get('status');

		if (is_numeric($status) && $status == 10) {
			return true;
		}

		$this->transactionFailed();
		$this->newLog($status, IDPayReceiveException::$errors[$status]);
		throw new IDPayReceiveException($status);
	}


	/**
	 * Verify user payment from IDPay server
	 *
	 * @return bool
	 *
	 * @throws IDPayReceiveException
	 */
	protected function verifyPayment()
	{
		$params = array(
			'id' => $this->refId(),
        	'order_id' => $this->transactionId(),
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->serverVerifyUrl);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'X-API-KEY: ' . $this->config->get('gateway.IDPay.API_KEY'),
			'X-SANDBOX: ' . intval($this->config->get('gateway.IDPay.sandbox', '0'))
		));

		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$response = json_decode($response);

		$this->trackingCode = \Input::get('track_id');
		if (
			$httpcode === 200 &&
			isset($response->status) && $response->status == 100 && 
			isset($response->amount) && $response->amount == $this->amount
		) {
			$this->trackingCode = $response->track_id;
			$this->cardNumber = $response->payment->card_no;
			$this->transactionSucceed();
			$this->newLog($response->status, Enum::TRANSACTION_SUCCEED_TEXT);
			return true;
		}

		$this->transactionFailed();
		$code = $response->error_code ?? $response->status;
		$message = $response->error_message ?? IDPayReceiveException::$errors[$code];
		$this->newLog($code, $message);
		throw new IDPayReceiveException($code);
	}
	
	/**
	 * Set Payer Name
	 *
	 * @param $name
	 * @return void
	 */
	public function setName($name)
	{
		$this->name = substr($name, 0, 300);
	}

	/**
	 * Set Description
	 *
	 * @param $description
	 * @return void
	 */
	public function setDescription($description)
	{
		$this->description = substr($description, 0, 300);
	}

	/**
	 * Set Payer Email Address
	 *
	 * @param $email
	 * @return void
	 */
	public function setEmail($email)
	{
		$this->email = $email;
	}

	/**
	 * Set Payer Mobile Number
	 *
	 * @param $number
	 * @return void
	 */
	public function setMobileNumber($number)
	{
		$this->mobileNumber = $number;
	}

	/**
	 * get gateway url
	 *
	 * @param $number
	 * @return void
	 */
	public function getGatewayUrl()
	{
		return $this->gatewayUrl;
	}
}
