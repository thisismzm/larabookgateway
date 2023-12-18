<?php

namespace Larabookir\Gateway\Alfacoins;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Larabookir\Gateway\Alfacoins\ALFAcoins_privateAPI;
use Larabookir\Gateway\Alfacoins\ALFAcoins_publicAPI;

class Alfacoins extends PortAbstract implements PortInterface
{

	/**
	 * Notification Callback Url
	 *
	 * @var string
	 */
	protected $notificationCallbackUrl;

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
		$this->verifyPayment();
		return $this;
	}

	/**
	 * Sets callback url
	 * @param $url
	 */
	public function setCallback($url)
	{
		$this->callbackUrl = $url;
		return $this;
	}

	/**
	 * Gets callback url
	 * @return string
	 */
	public function getCallback()
	{
		if (!$this->callbackUrl)
			$this->callbackUrl = $this->config->get('gateway.Alfacoins.callback-url');

		return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 * Sets callback url
	 * @param $url
	 */
	public function setNotificationCallback($url)
	{
		$this->notificationCallbackUrl = $url;
		return $this;
	}

	/**
	 * Gets callback url
	 * @return string
	 */
	public function getNotificationCallback()
	{
		if (!$this->notificationCallbackUrl)
			$this->notificationCallbackUrl = $this->config->get('gateway.Alfacoins.notification-callback-url');

		return $this->makeCallback($this->notificationCallbackUrl, ['transaction_id' => $this->transactionId()]);
	}

	/**
	 *  get BTC/USD exchange rate for 1 BTC (example of response: ["6459.83401257"])
	 *  more about it - https://www.alfacoins.com/developers#get_requests-rate
	 *
	 * @return Int rate
	 * @throws ALFAcoins_Exception
	 */
	public function exchange($from, $to)
	{
		$api = new ALFAcoins_publicAPI();
		return $api->rate($from, $to);
	}

	/**
	 *  get all service fees for all supported cryptocurrencies, 
	 * 	more about it - https://www.alfacoins.com/developers#get_requests-fees
	 *
	 * @return array fees
	 * @throws ALFAcoins_Exception
	 */
	public function fees($from, $to)
	{
		$api = new ALFAcoins_publicAPI();
		return $api->fees();
	}


		/**
	 * Send pay request to server
	 *
	 * @return void
	 *
	 * @throws ALFAcoins_Exception
	 */
	protected function sendPayRequest()
	{
		//dd($this->config->get('gateway.Alfacoins.type', 'bitcoin'));
		$this->newTransaction();

		// initialize ALFAcoins Private API class with your API settings
		$api = new ALFAcoins_privateAPI(
			$this->config->get('gateway.Alfacoins.shop_name', ''),
			$this->config->get('gateway.Alfacoins.shop_password', ''),
			$this->config->get('gateway.Alfacoins.shop_secret_key', '')
		);

		$order_description = $this->description ? $this->description : $this->config->get('gateway.Alfacoins.description', '');
		$options = [
			'notificationURL' => $this->getNotificationCallback(),
			'redirectURL' => $this->getCallback(),
			'payerName' =>  $this->name ? $this->name : $this->config->get('gateway.Alfacoins.name', ''),
			'payerEmail' => $this->email ? $this->email :$this->config->get('gateway.Alfacoins.email', ''),
		];

		$order = $api->create(
			$this->config->get('gateway.Alfacoins.type', 'bitcoin'),
			$this->amount,
			$this->config->get('gateway.Alfacoins.currency', 'USD'),
			$this->transactionId(),
			$order_description, 
			$options
		);
		\Log::info('Alfacoin: order create result: ' . json_encode($order));

		$this->refId = $order['id'];
        $this->transactionSetRefId();

		$this->gatewayUrl = $order['url'];
	}

	/**
	 * Verify user payment from Alfacoins server
	 *
	 * @return bool
	 *
	 * @throws ALFAcoins_Exception
	 */
	protected function verifyPayment()
	{
		$order_status = $this->status($this->refId);

		if ($order_status['status'] == 'completed') {
			$this->cardNumber = $order_status['deposit']['address'];
			$this->transactionSucceed();
			$this->newLog('completed', Enum::TRANSACTION_SUCCEED_TEXT);
			return true;
		} elseif ($order_status['status'] == 'paid') {
			$this->cardNumber = $order_status['deposit']['address'];
			$this->newLog('paid', 'transiction paid but not confirm yet');
			throw new ALFAcoins_Exception('transiction paid but not confirm yet', 1001);
		}

		//$this->transactionFailed();
		throw new ALFAcoins_Exception(Enum::TRANSACTION_FAILED_TEXT, 0);
	}

	/**
	 * get order status
	 * more about it - https://www.alfacoins.com/developers#post_requests-status
	 *
	 * @return array
	 *
	 * @throws ALFAcoins_Exception
	 */
	protected function status($order_id)
	{
		$api = new ALFAcoins_privateAPI(
			$this->config->get('gateway.Alfacoins.shop_name', ''),
			$this->config->get('gateway.Alfacoins.shop_password', ''),
			$this->config->get('gateway.Alfacoins.shop_secret_key', '')
		);
		
		return $api->status($order_id);
	}

}
