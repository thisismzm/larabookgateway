<?php
namespace Larabookir\Gateway;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Carbon\Carbon;

abstract class PortAbstract
{
	/**
	 * Transaction id
	 *
	 * @var null|int
	 */
	protected $transactionId = null;

	/**
	 * Transaction row in database
	 */
	protected $transaction = null;

	/**
	 * Customer card number
	 *
	 * @var string
	 */
	protected $cardNumber = '';

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * Port id
	 *
	 * @var int
	 */
	protected $portName;

	/**
	 * Reference id
	 *
	 * @var string
	 */
	protected $refId;

	/**
	 * Amount in Rial
	 *
	 * @var int
	 */
	protected $amount;

	/**
	 * callback URL
	 *
	 * @var url
	 */
	protected $callbackUrl;

	/**
	 * Tracking code payment
	 *
	 * @var string
	 */
	protected $trackingCode;

	 /**
     * payment url
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
	 * Initialize of class
	 *
	 * @param Config $config
	 * @param DataBaseManager $db
	 * @param int $port
	 */
	function __construct()
	{
		$this->db = app('db');
	}

	/** bootstraper */
	function boot(){

	}

	function setConfig($config)
	{
		$this->config = $config;
	}

	/**
	 * @return mixed
	 */
	function getTable()
	{
		return $this->db->table($this->config->get('gateway.table'));
	}

	/**
	 * @return mixed
	 */
	function getLogTable()
	{
		return $this->db->table($this->config->get('gateway.table') . '_logs');
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function getPortName()
	{
		return $this->portName;
	}

	/**
	 * Get port id, $this->port
	 *
	 * @return int
	 */
	function setPortName($name)
	{
		$this->portName = $name;
	}

	/**
	 * Return card number
	 *
	 * @return string
	 */
	function cardNumber()
	{
		return $this->cardNumber;
	}

	/**
	 * Return tracking code
	 */
	function trackingCode()
	{
		return $this->trackingCode;
	}

	/**
	 * Get transaction id
	 *
	 * @return int|null
	 */
	function transactionId()
	{
		return $this->transactionId;
	}

	/**
	 * Return reference id
	 */
	function refId()
	{
		return $this->refId;
	}

	/**
	 * Sets price
	 * @param $price
	 * @return mixed
	 */
	function price($price)
	{
		return $this->set($price);
	}

	/**
	 * Return result of payment
	 * If result is done, return true, otherwise throws an related exception
	 *
	 * This method must be implements in child class
	 *
	 * @param object $transaction row of transaction in database
	 *
	 * @return $this
	 */
	function verify($transaction)
	{
		$this->transaction = $transaction;
		$this->transactionId = $transaction->id;
		$this->amount = intval($transaction->price);
		$this->refId = $transaction->ref_id;
	}

	function getTimeId()
	{
		$genuid = function(){
			return substr(str_pad(str_replace('.','', microtime(true)),12,0),0,12);
		};
		$uid=$genuid();
		while ($this->getTable()->whereId($uid)->first())
			$uid = $genuid();
		return $uid;
	}

	/**
	 * Insert new transaction to poolport_transactions table
	 *
	 * @return int last inserted id
	 */
	protected function newTransaction()
	{
		$uid = $this->getTimeId();
		$this->transactionId = $this->getTable()->insert([
			'id' => $uid,
			'port' => $this->getPortName(),
			'price' => $this->amount,
			'status' => Enum::TRANSACTION_INIT,
			'ip' => Request::getClientIp(),
			'created_at' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]) ? $uid : null;

		return $this->transactionId;
	}

	/**
	 * Commit transaction
	 * Set status field to success status
	 *
	 * @return bool
	 */
	protected function transactionSucceed()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'status' => Enum::TRANSACTION_SUCCEED,
			'tracking_code' => $this->trackingCode,
			'card_number' => $this->cardNumber,
			'payment_date' => Carbon::now(),
			'updated_at' => Carbon::now(),
		]);
	}

	/**
	 * Failed transaction
	 * Set status field to error status
	 *
	 * @return bool
	 */
	protected function transactionFailed()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'status' => Enum::TRANSACTION_FAILED,
			'updated_at' => Carbon::now(),
		]);
	}

	/**
	 * Update transaction refId
	 *
	 * @return void
	 */
	protected function transactionSetRefId()
	{
		return $this->getTable()->whereId($this->transactionId)->update([
			'ref_id' => $this->refId,
			'updated_at' => Carbon::now(),
		]);

	}

	/**
	 * New log
	 *
	 * @param string|int $statusCode
	 * @param string $statusMessage
	 */
	protected function newLog($statusCode, $statusMessage)
	{
		return $this->getLogTable()->insert([
			'transaction_id' => $this->transactionId,
			'result_code' => $statusCode,
			'result_message' => $statusMessage,
			'log_date' => Carbon::now(),
		]);
	}

	/**
	 * Add query string to a url
	 *
	 * @param string $url
	 * @param array $query
	 * @return string
	 */
	protected function makeCallback($url, array $query)
	{
		return $this->url_modify(array_merge($query, ['_token' => csrf_token()]), url($url));
	}

	/**
	 * manipulate the Current/Given URL with the given parameters
	 * @param $changes
	 * @param  $url
	 * @return string
	 */
	protected function url_modify($changes, $url)
	{
		// Parse the url into pieces
		$url_array = parse_url($url);

		// The original URL had a query string, modify it.
		if (!empty($url_array['query'])) {
			parse_str($url_array['query'], $query_array);
			$query_array = array_merge($query_array, $changes);
		} // The original URL didn't have a query string, add it.
		else {
			$query_array = $changes;
		}

		return (!empty($url_array['scheme']) ? $url_array['scheme'] . '://' : null) .
		(!empty($url_array['host']) ? $url_array['host'] : null) .
		$url_array['path'] . '?' . http_build_query($query_array);
	}

	/**
	 * Set this property for success retry callback
	 *
	 * @return this
	 */
	function setTransaction($transaction)
	{
		$this->transactionId = $transaction->id;
		$this->transaction = $transaction;
		$this->cardNumber = $transaction->card_number;
		$this->refId = $transaction->ref_id;
		$this->amount = $transaction->price;
		$this->trackingCode = $transaction->tracking_code;
	}

	/**
	 * send data as post function
	 *
	 * @param string $url
	 * @param array $fields
	 * @param boolean $isJson
 	 * @param array $header
	 * @return crulResponse
	 */
	function Post($url, $fields, $isJson = false, $header = [])
	{
		$postvars = '';
		if ($isJson) {
			$postvars = json_encode($fields);
			array_push($header, "content-type: application/json");
		} else {
			foreach ($fields as $key => $value) {
				$postvars .= $key . "=" . $value . "&";
			}
			rtrim($postvars, '&');
		}
		array_push($header, "cache-control: no-cache");

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 100,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => $postvars,
			CURLOPT_HTTPHEADER => $header,
		));
		$response = curl_exec($curl);
		$err = curl_error($curl);
		$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($httpcode == 200 && $response == '') {
			$response = '{"success": true}';
		}
	
		return ($err) ? $err : $response;
	}

	/**
     * Set Payer Name
     *
     * @param $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = mb_substr($name, 0, 300);
    }

    /**
     * Set Description
     *
     * @param $description
     * @return void
     */
    public function setDescription($description)
    {
        $this->description = mb_substr($description, 0, 300);
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
