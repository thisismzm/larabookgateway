<?php

namespace Larabookir\Gateway\BazarPay;

use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Illuminate\Support\Facades\Input;

class BazarPay extends PortAbstract implements PortInterface
{
    protected $serverUrl = 'https://pardakht.cafebazaar.ir/pardakht/badje/v1/';

    protected $serverVerifyUrl = 'https://pardakht.cafebazaar.ir/pardakht/badje/v1/trace/';

    public function set($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    public function ready()
    {
        $this->sendPayRequest();

        return $this;
    }

    public function redirect()
    {
        return \Redirect::to($this->getGatewayUrl());
    }

    public function verify($transaction)
    {
        parent::verify($transaction);
        $this->userVerify();
        $this->verifyPayment();
        return $this;
    }

    function setCallback($url)
    {
        $this->callbackUrl = $url;
        return $this;
    }

    function getCallback()
    {
        if (!$this->callbackUrl) {
            $this->callbackUrl = $this->config->get('gateway.BazarPay.callback-url');
        }

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    protected function sendPayRequest()
    {
        $this->newTransaction();

        $params = [
            'amount'       => $this->amount,
            'service_name' => $this->name ? $this->name : $this->config->get('gateway.BazarPay.name', ''),
            'destination'  => $this->config->get('gateway.BazarPay.destination', ''),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl . 'checkout/init/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Token ' . $this->config->get('gateway.BazarPay.API_KEY'),
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($response);

        if ($httpcode == 200) {
            $this->refId        = $response->checkout_token;
            $this->trackingCode = $response->checkout_token;
            $this->gatewayUrl   = $response->payment_url;
            $this->transactionSetRefId();
            return true;
        }

        $this->transactionFailed();
        $this->newLog($httpcode, $response->detail ?? json_encode($response));
        throw new BazarPayException($response->detail ?? json_encode($response));
    }

    protected function commit()
    {
        $params = [
            'checkout_token' => $this->refId(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl . 'commit/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Token ' . $this->config->get('gateway.BazarPay.API_KEY'),
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = json_decode($response);

        if ($httpcode === 200) {
            return true;
        }
    }

    protected function verifyPayment()
    {
        $params = [
            'checkout_token' => $this->refId(),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverVerifyUrl);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Token ' . $this->config->get('gateway.BazarPay.API_KEY'),
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = json_decode($response);

        if ($httpcode === 200 && isset($response->status) && $response->status == 'paid_not_committed') {
            $this->transactionSucceed();
            $this->commit();
            $this->newLog($response->status, Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        }

        $this->transactionFailed();
        $code    = $httpcode;
        $message = $response->status ?? 'error';
        $this->newLog($code, $message);
        throw new BazarPayException($code);
    }

    protected function userVerify()
    {
        $status = Input::get('status', '');

        if ($status == 'done') {
            return true;
        }

        $this->transactionFailed();
        $this->newLog($status, '');
        throw new BazarPayException($status);
    }

    public function getGatewayUrl()
    {
        return $this->gatewayUrl . '&redirect_url=' . urlencode($this->getCallback());
    }
}
