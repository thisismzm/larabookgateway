<?php

namespace Larabookir\Gateway\Thawani;

use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;
use Illuminate\Support\Facades\Input;

class Thawani extends PortAbstract implements PortInterface
{
    protected $serverUrl = 'https://checkout.thawani.om';

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
            $this->callbackUrl = $this->config->get('gateway.Thawani.callback-url');
        }

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    protected function sendPayRequest()
    {
        $this->newTransaction();

        $params = [
            "client_reference_id" => $this->transactionId(),
            "mode"                => "payment",
            "products"            => [
                [
                    "name"        => "Total Price",
                    "quantity"    => 1,
                    "unit_amount" => $this->amount,
                ],
            ],
            "success_url"         => $this->getCallback(),
            "cancel_url"          => $this->getCallback(),
            "metadata"            => [
                'description' => $this->description ?? '',
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl . '/api/v1/checkout/session');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'thawani-api-key: ' . $this->config->get('gateway.Thawani.SECRET_KEY'),
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $response = json_decode($response);
        if ($httpcode == 200 && $response->success) {
            if ($response->data) {
                $this->refId        = $response->data->session_id;
                $this->trackingCode = $response->data->invoice;
                $this->transactionSetRefId();
                return true;
            }
        }

        $this->transactionFailed();
        $this->newLog($httpcode, $response->detail ?? json_encode($response));
        throw new ThawaniException($response->detail ?? json_encode($response));
    }

    protected function verifyPayment()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl . '/api/v1/checkout/session/' . $this->refId());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'thawani-api-key: ' . $this->config->get('gateway.Thawani.SECRET_KEY'),
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response = json_decode($response);

        if ($httpcode === 200 && isset($response->data) && $response->data->payment_status == 'paid') {
            $this->transactionSucceed();
            $this->newLog($response->code, Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        }

        $this->transactionFailed();
        $code    = $httpcode;
        $message = $response->status ?? 'error';
        $this->newLog($code, $message);
        throw new ThawaniException($code);
    }

    public function getGatewayUrl()
    {
        return $this->serverUrl . '/pay/' . $this->refId . '?key=' . $this->config->get('gateway.Thawani.PUBLISHABLE_KEY');
    }
}
