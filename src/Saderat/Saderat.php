<?php

namespace Larabookir\Gateway\Saderat;

use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Enum;

class Saderat extends PortAbstract implements PortInterface
{
	/**
	 * Address of verify transactions server
	 *
	 * @var string
	 */
	protected $serverVerifyUrl = 'https://mabna.shaparak.ir:8081/V1/PeymentApi/Advice';



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
		$TerminalID = $this->config->get('gateway.saderat.TID');
		$Amount = $this->amount;
		$callbackURL = $this->getCallback();
		$InvoiceID = $this->transactionId();

		return \View::make('app.gateway.saderat-redirector')->with(
			compact('TerminalID', 'Amount', 'callbackURL', 'InvoiceID')
		);
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
			$this->callbackUrl = $this->config->get('gateway.saderat.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws SaderatException
	 */
	protected function sendPayRequest()
	{
		$this->newTransaction();
	}

	/**
	 * Check user payment
	 *
	 * @return bool
	 *
	 * @throws SaderatException
	 */
	protected function userPayment()
	{
		$this->refId = Input::get('rrn');
		$this->trackingCode = Input::get('tracenumber');
		$this->cardNumber = Input::get('cardnumber');
		$payRequestResCode = Input::get('respcode');

		if ($payRequestResCode == '0') {
			return true;
		}

		$this->transactionFailed();
		$this->newLog($payRequestResCode, @SaderatException::$errors[$payRequestResCode]);
		throw new SaderatException($payRequestResCode);
	}

	/**
	 * Verify user payment from bank server
	 *
	 * @return bool
	 *
	 * @throws SaderatException
	 * @throws SoapFault
	 */
	protected function verifyPayment()
	{
		$data = array(
			"digitalreceipt" => Input::get('digitalreceipt'),
			"Tid" => Input::get('terminalid'),
		);

		$dataQuery = http_build_query($data);

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $this->serverVerifyUrl);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $dataQuery);

		$result = curl_exec($curl);
		$error = curl_error($curl);
		$errno = curl_errno($curl);
		curl_close($curl);

		$result = json_decode($result);

		if($result && $result->Status !== 'NOK' && $result->ReturnId == $this->amount) {
			$this->transactionSucceed();
			$this->newLog($result->Status, Enum::TRANSACTION_SUCCEED_TEXT);
			return true;
		}

		if ($result) {
			$this->transactionFailed();
			$this->newLog($result->ReturnId, $result->Message);
			throw new SaderatException($result->ReturnId);
		} 

		$this->transactionFailed();
		$this->newLog($errno, $error);
		throw new SaderatException($result->ReturnId);
	}

}
