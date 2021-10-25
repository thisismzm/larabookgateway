<?php

namespace Larabookir\Gateway\Alfacoins;

/**
 * Class ALFAcoins_privateAPI
 */
class ALFAcoins_privateAPI {
  /**
   * @var string API Shop Name
   */
  private $name;
  /**
   * @var string API Secret Key
   */
  private $secret_key;
  /**
   * @var string API Password Hash
   */
  private $password_hash;
  /**
   * @var string ALFAcoins API URL
   */
  private $url = 'https://www.alfacoins.com/api';

  /**
   * @param string $name API Shop Name
   * @param string $password API Password
   * @param string $secret_key API Secret Key
   */
  public function __construct($name, $password, $secret_key) {
    $this->setShopName($name);
    $this->setSecretKey($secret_key);
    $this->setShopPassword($password);
  }

  /**
   * @param string $name Api Shop Name
   */
  public function setShopName($name) {
    $this->name = $name;
  }

  /**
   * @param string $secret_key API Secret Key
   */
  public function setSecretKey($secret_key) {
    $this->secret_key = $secret_key;
  }

  /**
   * @param string $password API Password
   */
  public function setShopPassword($password) {
    $this->password_hash = strtoupper(md5($password));
  }

  /**
   * BitSend primary use to payout salaries for staff or making direct deposits to different cryptocurrency addresses
   * @param float $amount Deposit amount in merchant's fiat currency (optional)
   * @param float $coin_amount Deposit amount in selected cryptocurrency (optional)
   * @param string $type Cryptocurrency to pay with
   * @param array $options Client cryptocurrency address for deposit, and additional tags
   *                 i.e. {"address": "1FE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm"} for Bitcoin, Litecoin, Ethereum, Dash
   *                 {"address": "qFE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm", "legacy_address": "1FE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm"} for Bitcoin Cash
   *                 {"address": "rExZpwNwwrmFWbX81AqbKJYkq8W6ZoeWE6", "destination_tag": "1294967290"} for XRP
   * @param string $recipient_name Client Name (for email notification)
   * @param string $recipient_email Client email (for email notification)
   * @param string $reference Deposit description (for client notification)
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function bitsend($amount = null, $coin_amount = null, $type = 'bitcoin', $options = [], $recipient_name, $recipient_email, $reference = '') {
    $params = [
      'name' => $this->name,
      'secret_key' => $this->secret_key,
      'password' => $this->password_hash,
      'type' => "$type",
      'options' => $options,
      'amount' => $amount,
      'recipient_name' => $recipient_name,
      'recipient_email' => $recipient_email,
      'reference' => $reference
    ];
    if (!empty($coin_amount)) {
      unset($params['amount']);
      $params['coin_amount'] = number_format($coin_amount, 8, '.', '');
    }
    $result = $this->postRequest("bitsend", $params);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid bitsend result, error: " . $result['error']);
    return $result;
  }

  /**
   * BitSend status primary use to get information of bitsend payout
   * @param int $bitsend_id Bitsend ID
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function bitsend_status($bitsend_id) {
    $result = $this->postRequest("bitsend_status", ['bitsend_id' => (int) $bitsend_id]);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid bitsend_status result, error: " . $result['error']);
    return $result;
  }

  /**
   * Refund completed order
   * @param int $txn_id Order ID for Refund
   * @param int|float $amount Amount for refund. Set 0 for full refund
   * @param array $options Client cryptocurrency address for deposit, and additional tags
   *                 i.e. {"address": "1FE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm"} for Bitcoin, Litecoin, Ethereum, Dash
   *                 {"address": "qFE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm", "legacy_address": "1FE7bSYsXSMrdXTCdRUWUB6jGFFba74fzm"} for Bitcoin Cash
   *                 {"address": "rExZpwNwwrmFWbX81AqbKJYkq8W6ZoeWE6", "destination_tag": "1294967290"} for XRP
   * @param bool $useNewRate (Optional) Use current time rates for fiat to cryptocurrency conversion or use order's rate
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function refund($txn_id, $amount = 0, $options, $useNewRate = false) {
    $params = [
      'txn_id' => (int) $txn_id,
      'options' => $options,
      'new_rate' => $useNewRate
    ];
    if ($amount != 0) {
      $params['amount'] = $amount;
    }
    $result = $this->postRequest("refund", $params);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid refund result, error: " . $result['error']);
    return $result;
  }

  /**
   * Merchant's volume and balance statistics
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function stats() {
    $result = $this->postRequest("stats");
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid stats result, error: " . $result['error']);
    return $result;
  }

  /**
   * Create order for payment
   * @param string $type Cryptocurrency to get paid with
   * @param float $amount Deposit amount in merchant's fiat currency
   * @param string $currency (Optional) Amount currency. If currency is empty merchant's default currency will be used.
   * @param string $order_id Merchant's Order ID
   * @param string $description (Optional) Description for order
   * @param array $options (Optional)
   *                       {
   *                        "notificationURL": "[custom Merchant's URL for payment notification]",
   *                        "redirectURL": "[Merchant's page which is shown after payment is made by a customer]",
   *                        "payerName": "[Customer's name for notification]",
   *                        "payerEmail": "[Customer's email for notification]"
   *                       }
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function create($type, $amount, $currency, $order_id, $description, $options = []) {
    $result = $this->postRequest("create", [
      'type' => $type,
      'amount' => $amount,
      'order_id' => $order_id,
      'currency' => $currency,
      'description' => $description,
      'options' => $options,
    ]);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid create result, error: " . $result['error']);
    return $result;
  }

  /**
   * Get status of created Order
   * @param int $order_id ALFAcoins TXN ID
   * @return array
   * @throws ALFAcoins_Exception
   */
  public function status($order_id) {
    $result = $this->postRequest("status", ['txn_id' => $order_id]);
    if (!empty($result['error']))
      throw new ALFAcoins_Exception("Invalid status result, error: " . $result['error']);
    return $result;
  }

  /**
   * @param string $method
   * @param array $params
   * @return array
   * @throws ALFAcoins_Exception
   */
  private function postRequest($method, $params = []) {
    if (empty($this->name) || empty($this->password_hash) || empty($this->secret_key))
      throw new ALFAcoins_Exception("Error: Shop name, password or secret_key are not set.");

    $essential_params = [
      'name' => $this->name,
      'secret_key' => $this->secret_key,
      'password' => $this->password_hash,
    ];
    $params = array_merge($essential_params, $params);
    $content = json_encode($params);
    $url = $this->url . '/' . $method . '.json';
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: application/json; charset=UTF-8"]);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $content);
    $json_response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if ($status != 200) {
      throw new ALFAcoins_Exception("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }
    curl_close($curl);
    $response = json_decode($json_response, TRUE);
    return $response;
  }
}
