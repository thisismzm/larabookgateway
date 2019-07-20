<?php

namespace Larabookir\Gateway\Pasargad;

use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\Enum;
use SoapClient;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;

class Pasargad extends PortAbstract implements PortInterface
{
	/**
	 * Url of parsian gateway web service
	 *
	 * @var string
	 */

	protected $checkTransactionUrl = 'https://pep.shaparak.ir/CheckTransactionResult.aspx';
	protected $verifyUrl = 'https://pep.shaparak.ir/VerifyPayment.aspx';
	protected $refundUrl = 'https://pep.shaparak.ir/doRefund.aspx';

	/**
	 * Address of gate for redirect
	 *
	 * @var string
	 */
	protected $gateUrl = 'https://pep.shaparak.ir/gateway.aspx';

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

        $processor = new RSAProcessor($this->config->get('gateway.pasargad.certificate-path'),RSAKeyType::XMLString);

		$url = $this->gateUrl;
		$redirectUrl = $this->getCallback();
        $invoiceNumber = $this->transactionId();
        $amount = $this->amount;
        $terminalCode = $this->config->get('gateway.pasargad.terminalId');
        $merchantCode = $this->config->get('gateway.pasargad.merchantId');
        $timeStamp = date("Y/m/d H:i:s");
        $invoiceDate = date("Y/m/d H:i:s");
        $action = 1003;
        $data = "#". $merchantCode ."#". $terminalCode ."#". $invoiceNumber ."#". $invoiceDate ."#". $amount ."#". $redirectUrl ."#". $action ."#". $timeStamp ."#";
        $data = sha1($data,true);
        $data =  $processor->sign($data); // امضاي ديجيتال
        $sign =  base64_encode($data); // base64_encode
        return \View::make('app.gateway.pasargad-redirector')->with(compact('url','redirectUrl','invoiceNumber','invoiceDate','amount','terminalCode','merchantCode','timeStamp','action','sign'));
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
			$this->callbackUrl = $this->config->get('gateway.pasargad.callback-url');

		return $this->callbackUrl;
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
	}

	/**
	 * Verify payment
	 *
	 * @throws ParsianErrorException
	 */
	protected function verifyPayment()
	{
        $fields = array(
            'invoiceUID' => Input::get('tref'),
        );

        $result = Parser::post2https($fields, $this->checkTransactionUrl);
        $array = Parser::makeXMLTree($result);

		if ($array["resultObj"]['result'] != "True") {
			$this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
			$this->transactionFailed();
			throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1);
		}

        $this->refId = $array["resultObj"]['transactionReferenceID'];
        $this->transactionSetRefId();

		$this->trackingCode = $array["resultObj"]['traceNumber'];

		// verify payment step 2
        $processor = new RSAProcessor($this->config->get('gateway.pasargad.certificate-path'),RSAKeyType::XMLString);

        $invoiceNumber = $this->transactionId();
        $amount = $this->amount;
        $terminalCode = $this->config->get('gateway.pasargad.terminalId');
        $merchantCode = $this->config->get('gateway.pasargad.merchantId');
        $timeStamp = date("Y/m/d H:i:s");
        $invoiceDate = $array["resultObj"]['invoiceDate'];
        $data = "#". $merchantCode ."#". $terminalCode ."#". $invoiceNumber ."#". $invoiceDate ."#". $amount ."#". $timeStamp ."#";
        $data = sha1($data,true);
        $data = $processor->sign($data); 
        $sign = base64_encode($data); 

		$fields = array(
            "MerchantCode"  => $merchantCode,
            "TerminalCode"  => $terminalCode,
            "InvoiceNumber" => $invoiceNumber,
            "InvoiceDate"	=> $invoiceDate,
            "amount"        => $amount,
            "TimeStamp"     => $timeStamp,
            "sign"          => $sign
        );

		$result = Parser::post2https($fields, $this->verifyUrl);
        $array2 = Parser::makeXMLTree($result);
		if (!isset($array2['actionResult']["result"]) || $array2['actionResult']["result"] != "True") {
			$this->newLog(-1, Enum::TRANSACTION_FAILED_TEXT);
			$this->transactionFailed();
			throw new PasargadErrorException(Enum::TRANSACTION_FAILED_TEXT, -1025);
		}

		$this->transactionSucceed();
		$this->newLog('true', Enum::TRANSACTION_SUCCEED_TEXT);
	}
}
