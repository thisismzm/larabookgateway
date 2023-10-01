<?php

namespace Larabookir\Gateway\Plisio;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Plisio extends PortAbstract implements PortInterface
{

    /**
     * Payment Name
     *
     * @var string
     */
    protected $name;

    /**
     * {@inheritdoc}
     */
    public function set($amount)
    {
        $this->amount = $amount / 10;

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
        if (!$this->callbackUrl) $this->callbackUrl = $this->config->get('gateway.Plisio.callback-url');

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * Set Order Name
     *
     * @param $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = substr($name, 0, 300);
    }


    /**
     *  get BTC/USD exchange rate for 1 BTC (example of response: ["6459.83401257"])
     *  more about it - https://www.alfacoins.com/developers#get_requests-rate
     *
     * @return Int rate
     * @throws ALFAcoins_Exception
     */
    public function exchange($from, $to, $amount) //should be short name
    {
        $curl = curl_init();
        $url = $this->url . '/currencies/' . $from;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: application/json; charset=UTF-8"]);
        $output = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            throw new Plisio_Exception("Error: call to URL $url failed with status $status, response $output, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
        }
        curl_close($curl);
        $response = json_decode($output, TRUE);

        if ($response['status'] == 'success') {
            foreach ($response['data'] as $any_currency) {
                if ($any_currency['currency'] == $to) {
                    return $any_currency['fiat_rate'] * $amount;
                }
            }
        }
        throw new Plisio_Exception("NOT FOUND CURRENCY");
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
        $this->newTransaction();
        $url = 'https://plisio.net/api/v1/invoices/new';
        //\Log::info($this->getCallback());
        $params = [
            'api_key'         => $this->config->get('gateway.Plisio.secret_key', ''),
            'order_name'      => !empty($this->name) ? $this->name : 'OrderName',//$this->name,
            'order_number'    => $this->transactionId(),
            'source_currency' => $this->config->get('gateway.Plisio.source_currency', 'USD'), //one of this list https://plisio.net/documentation/appendices/supported-fiat-currencies
            'currency'        => 'BTC', //One of this list https://plisio.net/documentation/appendices/supported-cryptocurrencies
            'source_amount'   => $this->amount,
            'email'           => $this->email,
            'description'     => $this->description,
            'callback_url'    => $this->getCallback(),
        ];
        $send_data = '?';
        foreach ($params as $key => $value) {
            $send_data .= "$key=$value&";
        }
        $curl = curl_init();
        $url = $url . $send_data;
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ["Content-type: application/json; charset=UTF-8"]);
        $output = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            return "PROBLEM IN RESPONSE STATUS CODE IS: $status";
        }
        curl_close($curl);
        $response = json_decode($output, TRUE);

        if ($response['status'] == 'success') {
            $refId = $response['data']['txn_id'];
            $gatewayUrl = $response['data']['invoice_url'];
        } else {
            return $response['data']['message'];//should throw excepiton
        }

        $this->refId = $refId ?? '';
        $this->transactionSetRefId();

        $this->gatewayUrl = $gatewayUrl;
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
        if (!isset($_POST['verify_hash'])) {
            return false;
        }
        $post = $_POST;
        $verifyHash = $post['verify_hash'];
        unset($post['verify_hash']);
        ksort($post);
        $postString = serialize($post);
        $checkKey = hash_hmac('sha1', $postString, $this->config->get('gateway.Plisio.secret_key', ''));
        if ($checkKey != $verifyHash) {
            \Log::info('pliso wrong verifyHash');
            return false;
        }
        $order_status = $post['status'];

        if ($order_status == 'completed') {
            $this->transactionSucceed();
            $this->newLog('completed', Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        } elseif ($order_status == 'pending') {
            $this->newLog('pending', 'transiction paid but not confirm yet');
            throw new Plisio_Exception('transiction paid but not confirm yet', 1001);
        }
        //$this->transactionFailed();
        throw new Plisio_Exception(Enum::TRANSACTION_FAILED_TEXT, 0);
    }
}
