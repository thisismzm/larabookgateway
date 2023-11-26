<?php

namespace Larabookir\Gateway\Paypal;

use Illuminate\Support\Facades\Input;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Illuminate\Support\Facades\Config;

class Paypal extends PortAbstract implements PortInterface
{
    /**
     * Payment Name
     * 
     * @var string
     */
    protected $name;

    /**
     * Address of iran RESTFUL server
     *
     * @var string
     */
    protected $mainServer = 'https://api-m.paypal.com/v2/checkout/orders';

    /**
     * Address of main RESTFUL server
     *
     * @var string
     */
    protected $serverUrl;

    /**
     * Address of sandbox RESTFUL server
     *
     * @var string
     */
    protected $sandboxServer = 'https://api-m.sandbox.paypal.com/v2/checkout/orders';

    /**
     * Address of gate for redirect
     *
     * @var string
     */
    protected $gateUrl = '';

    /**
     * Address of sandbox gate for redirect
     *
     * @var string
     */
    protected $sandboxGateUrl = 'https://api.sandbox.paypal.com/v2/checkout/orders';

    /**
     * Currency
     *
     * @var string
     */
    protected $currency;


    public function boot()
    {
        $this->setServer();
    }

    /**
     * Set server for Restful transfers data
     *
     * @return void
     */
    protected function setServer()
    {
        $server = $this->config->get('gateway.paypal.server');
        switch ($server) {
            case 'test':
                return $this->serverUrl = $this->sandboxServer;
                break;

            case 'main':
                return $this->serverUrl = $this->mainServer;
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set($amount)
    {
        $this->amount = $amount;
        // for USD amount must set be like 1.00

        $this->currency = $this->config->get('gateway.paypal.currency');

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
        if ($this->config->get('gateway.paypal.server') == 'test')
            return \Redirect::to($this->sandboxGateUrl);

        else if ($this->config->get('gateway.paypal.server') == 'main')
            return \Redirect::to($this->gatewayUrl);
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
        if (!$this->callbackUrl) $this->callbackUrl = $this->config->get('gateway.paypal.callback_url');

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * Send pay request to server
     *
     * @return void
     *
     * @throws PaypalException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();
        // fields informations and proxy address and port
        $fields = array(
            'client_id' => $this->config->get('gateway.paypal.client_id'),
            'client_secret' => $this->config->get('gateway.paypal.client_secret'),
            'order_name' => 'Custom order',
            'url' => $this->setServer(),
            'callback_url' => $this->getCallback(),
            'currency' => $this->currency,
            'intent' => 'CAPTURE',
            'amount'   => $this->amount,
            'proxy_status' => $this->config->get('gateway.proxy.status'),
            'proxy_address' => (Config::get('services.proxy.address') != NULL) ? Config::get('services.proxy.address') : $this->config->get('gateway.proxy.address'),
            'proxy_port' => $this->config->get('gateway.proxy.port'),
        );

        // create credentials informations
        $basic_credentials = $fields['client_id'] . ':' . $fields['client_secret'];

        //another way for authentication is using token which can captured from login with client_id and secret $authorization = "Authorization: Bearer " . $access_token;
        $authorization = 'Authorization: Basic ' . base64_encode($basic_credentials);

        // create curl request
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Prefer: return=minimal', 'Content-Type: application/json',  $authorization));
            curl_setopt($ch, CURLOPT_URL, $fields['url']);
            // for redirects
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 25);
            // set proxy server
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($ch, CURLOPT_PROXY, $fields['proxy_address']);
            curl_setopt($ch, CURLOPT_PROXYPORT, $fields['proxy_port']);
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                '{
                    "intent": "CAPTURE",
                    "purchase_units": [
                        {
                            "amount": {
                                "currency_code": ' . '"' . $fields['currency'] . '"' . ',
                                "value": ' . $fields["amount"] . ',
                                "breakdown": {
                                    "item_total": {
                                        "currency_code": ' . '"' . $fields['currency'] . '"' . ',
                                        "value": ' . '"' . $fields['amount'] . '"' . '
                                                    }
                                            }
                        }
                    }
                ],
                "application_context": {
                    "return_url":  ' . '"' . $fields['callback_url'] . '"' . ',
                    "cancel_url": ' . '"' . $fields['callback_url'] . '"' . '
                }
                }'
            );

            $output = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            // for debuging if we have any error
            // if (curl_errno($ch)) {
            //     return 'Curl error: ' . curl_error($ch);
            // }

            curl_close($ch);

            $response = json_decode($output, true);

            // show error if request not accepted
            if ($status != 201 && $status != 200) {
                return "PROBLEM IN RESPONSE STATUS CODE IS: $status";
            }
        } catch (\Exception $e) {
            $this->transactionFailed();
            $this->newLog('RestfulFault', $e->getMessage());
            throw $e;
        }
        // If the request was not created can be because of user connection problem
        if ($response === NULL) {
            return "Order of Paypal API call not created";
        }

        // if response was created, reference id and gateway url extracted
        if ($response['status'] && 'CREATED' === $response['status']) {
            $refId = $response['id'];
            $gatewayUrl = $response['links'][1]['href'];
        } else {
            $this->transactionFailed();
            throw new Paypal($response['status']);
        }

        // set refId and gatewayUrl
        $this->refId = $refId ?? '';
        $this->transactionSetRefId();

        $this->gatewayUrl = $gatewayUrl ?? '';
    }

    /**
     * {@inheritdoc}
     * @throws PaypalException
     */
    public function verify($transaction)
    {
        parent::verify($transaction);
        // if ref id of transaction was not set
        if (!$transaction->ref_id)
            throw new PaypalException(401);
        $this->verifyPayment($transaction->ref_id);
        return $this;
    }

    /**
     * Verify user payment from paypal server
     *
     * @return bool
     *
     * @throws PaypalException
     */
    protected function verifyPayment(string $refId)
    {
        // fields informations and proxy address and port
        $fields = array(
            'client_id' => $this->config->get('gateway.paypal.client_id'),
            'client_secret' => $this->config->get('gateway.paypal.client_secret'),
            'url' => $this->setServer(),
            'proxy_address' => (Config::get('services.proxy.address') != NULL) ? Config::get('services.proxy.address') : $this->config->get('gateway.proxy.address'),
            'proxy_port' => $this->config->get('gateway.proxy.port'),
        );

        // create credentials informations
        $basic_credentials = $fields['client_id'] . ':' . $fields['client_secret'];
        //another way for authentication is using token which can captured from login with client_id and secret $authorization = "Authorization: Bearer " . $access_token;
        $authorization = 'Authorization: Basic ' . base64_encode($basic_credentials);
        $capture_url = $fields['url'] . '/' . $refId . '/capture';

        // post request with curl
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $capture_url);
        // redirect request for redirect
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        // set proxy server
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, $fields['proxy_address']);
        curl_setopt($ch, CURLOPT_PROXYPORT, $fields['proxy_port']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Prefer: return=minimal', $authorization));

        $response = curl_exec($ch);

        // for debugging
        // if (curl_errno($ch)) {
        //     return 'Curl error: ' . curl_error($ch);
        // }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = json_decode($response);

        if (
            $httpcode === 201 &&
            isset($response->status) && $response->status === 'COMPLETED'  &&
            isset($response->purchase_units[0]->payments->captures[0]->amount->value) && $response->purchase_units[0]->payments->captures[0]->amount->value == $this->amount
        ) {
            // store tracking code to the database and set transaction to succeed
            $this->trackingCode = $response->purchase_units[0]->payments->captures[0]->id;
            $this->transactionSucceed();
            $this->newLog($response->status, Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        }

        // set transaction fail and create code and message error
        $this->transactionFailed();
        $code = $response->error_code ?? $httpcode;
        $message = $response->message ?? PaypalException::$errors[$code];
        $this->newLog($code, $message);
        throw new PaypalException($code);
    }
}
