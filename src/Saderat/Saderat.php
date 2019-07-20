<?php

namespace Larabookir\Gateway\Saderat;

use DateTime;
use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\Enum;
use SoapClient;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Saderat extends PortAbstract implements PortInterface
{
	/**
	 * Address of main SOAP server
	 *
	 * @var string
	 */
	protected $serverUrl = 'https://mabna.shaparak.ir/TokenService?wsdl';

	/**
	 * Address of verify SOAP server
	 *
	 * @var string
	 */
	protected $serverVerifyUrl = 'https://mabna.shaparak.ir/TransactionReference/TransactionReference?wsdl';

	/**
	 * public key generated by mabna co for signature
	 *
	 * @var string
	 */
	protected $publicKey = '
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCETtzC9pZ+dnQ0z0pXL6pNrkn4vGdbLTf3fhH5
MsVYsFIPuuaUSC9EnbTa8G9p1AIKNsjQaBbzfkvgdu5Tz8qEXZfYQV2bnSCtl/87M7Xn0raAmGTr
jSliTdsxMyJHObzAPkamjHemAxHd9VkwXfZOPAh00ueag+buTAkbzL1MlQIDAQAB
-----END PUBLIC KEY-----
';


	/**
	 * get privateKey from config
	 */
	private function getPrivateKey()
	{
		return
'
-----BEGIN PRIVATE KEY-----
'. trim($this->config->get('gateway.saderat.PRIVATE_KEY')) .'
-----END PRIVATE KEY-----
';
	}


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
		$refId = $this->refId;

		return \View::make('app.gateway.saderat-redirector')->with(compact('refId'));
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

		//remove _token variables from url because bank ignure it
		$parse = parse_url($this->getCallback());
		parse_str($parse['query'], $output);
		unset($output['_token']);
		$query = http_build_query($output);
		$baseurl = strtok($this->getCallback(), '?');
		$callback = $baseurl . '?' . $query;

		$fieldsArray = array(
			'AMOUNT' => $this->amount,
			'CRN' => $this->transactionId(),
			'MID' => $this->config->get('gateway.saderat.MID'),
			'REFERALADRESS' => $callback,
			'TID' => $this->config->get('gateway.saderat.TID')
		);

		/**
		 * Make a signature temporary
		 * Template : AMOUNT + CRN + MID + REFERALADRESS + TID
		 */
		$source = implode($fieldsArray);

		//get key resource to start based on public key
		$keyResource = openssl_get_publickey($this->publicKey);

		// Crypted item , each paid has it's own specific signature
		foreach ($fieldsArray as $key => $value) {
			openssl_public_encrypt($value, $cryptText, $keyResource);
			$$key = base64_encode($cryptText);
		}

		$signature = '';
		$privateKey = openssl_pkey_get_private($this->getPrivateKey());
		if (!openssl_sign($source, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
			throw new SaderatException(1009);
		}

		/**
		 * Make proper array of token params
		 */
		$fields = array(
			"Token_param" =>
				array(
					"AMOUNT" => $AMOUNT,
					"CRN" => $CRN,
					"MID" => $MID,
					"REFERALADRESS" => $REFERALADRESS,
					"SIGNATURE" => base64_encode($signature),
					"TID" => $TID,
				)
		);

		try {
			$soap = new SoapClient($this->serverUrl);
			$response = $soap->reservation($fields);

		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}

		$response = $response->return;

		if ($response->result != '0') {
			$this->transactionFailed();
			$this->newLog($response->result, SaderatException::$errors[$response->result]);
			throw new SaderatException($response->result);
		}

		/**
		 * Final signature is created
		 */
		$signature = base64_decode($response->signature);

		/**
		 * State whether signature is okay or not
		 */
		if(!openssl_verify($response->token, $signature, $keyResource)){
			throw new SaderatException(1009);
		}

		/**
		 * Free the key from memory
		 **/
		openssl_free_key($keyResource);

		$this->refId = $response->token;
		$this->transactionSetRefId();
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
		$this->refId = Input::get('transaction_id');
		$this->trackingCode = Input::get('TRN');
		$this->cardNumber = '';
		$payRequestResCode = Input::get('RESCODE');

		if ($payRequestResCode == '00') {
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
		//get key resource to start based on public key
		$keyResource = openssl_get_publickey($this->publicKey);

		$fieldsArray = array(
			'MID' => $this->config->get('gateway.saderat.MID'),
			'TRN' => $this->trackingCode,
			'CRN' => $this->refId
		);

		/**
		 * Make a signature temporary
		 * Template : MID + TRN + CRN
		 */
		$source = implode($fieldsArray);

		// Crypted item , each paid has it's own specific signature
		foreach ($fieldsArray as $key => $value) {
			openssl_public_encrypt($value, $cryptText, $keyResource);
			$$key = base64_encode($cryptText);
		}

		$signature = '';
		$privateKey = openssl_pkey_get_private($this->getPrivateKey());
		if (!openssl_sign($source, $signature, $privateKey, OPENSSL_ALGO_SHA1)) {
			throw new SaderatException(1009);
		}

		$fields = array(
			"SaleConf_req" => array(
				"MID" => $MID,
				"CRN" => $CRN,
				"TRN" => $TRN,
				"SIGNATURE" => base64_encode($signature)
			)
		);

		try {
			$soap = new SoapClient($this->serverVerifyUrl);
			$response = $soap->sendConfirmation($fields);
		} catch (\SoapFault $e) {
			$this->transactionFailed();
			$this->newLog('SoapFault', $e->getMessage());
			throw $e;
		}

		$response = $response->return;
		/**
		 * Final signature is created
		 */
		$signature = base64_decode($response->SIGNATURE);
		$data = $response->RESCODE . $response->REPETETIVE . $response->AMOUNT . $response->DATE . $response->TIME . $response->TRN . $response->STAN;

		/**
		 * state whether signature is okay or not
		 */
		$verifyResult = openssl_verify($data, $signature, $keyResource);

		/**
		 * Result Webservice Array
		 */
		if (! (!empty($response->RESCODE) && $response->RESCODE == '00' && $response->successful == true && $verifyResult)) {
			$this->transactionFailed();
			$this->newLog($response->return, SaderatException::$errors[$response->RESCODE]);
			throw new SaderatException($response->RESCODE);
		}

		return true;
	}
}