<?php

namespace Larabookir\Gateway\Saderat;

use DateTime;
use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use SoapClient;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Saderat extends PortAbstract implements PortInterface
{
    /**
     * Address of token RestAPI server
     *
     * @var string
     */
    protected $serverTokenUrl = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/GetToken';

    /**
     * Address of verify RestAPI server
     *
     * @var string
     */
    protected $serverVerifyUrl = 'https://sepehr.shaparak.ir:8081/V1/PeymentApi/Advice';


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
        $token = $this->refId;
        $TerminalID = $this->config->get('gateway.saderat.TID');

        return \View::make('app.gateway.saderat-redirector')->with(compact('token', 'TerminalID'));
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
        if (!$this->callbackUrl) {
            $this->callbackUrl = $this->config->get('gateway.saderat.callback-url');
        }

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * Send pay request to server
     *
     * @return void
     *
     * @throws SaderatnewException
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
        $fields = [
            "Amount" => $this->amount,
            "callbackURL" => $callback,
            "invoiceID" => $this->transactionId(),
            "terminalID" => $this->config->get('gateway.saderat.TID')
        ];
        $response = $this->Post($this->serverTokenUrl, $fields);
        $response = json_decode($response);
        $this->refId = $response->Accesstoken;
        $this->transactionSetRefId();
    }


    /**
     * Verify user payment from bank server
     *
     * @return bool
     *
     * @throws SaderatnewException
     */
    protected function verifyPayment()
    {
        $responce = \Request::all();
        // echo $inputs['respmsg'];
        if ($responce['respcode'] == 0) {
            $fields = [
                "digitalreceipt" => $responce['digitalreceipt'],
                "Tid" => $responce['terminalid'],
            ];
            $result = $this->Post($this->serverVerifyUrl, $fields);
            $result = json_decode($result);
            if (intval($result->ReturnId) >= 1000 && intval($result->ReturnId) == $this->amount) {
                $this->trackingCode = $responce['rrn'];
                $this->cardNumber = $responce['cardnumber'];
                $this->transactionSucceed();
                return true;
            } else {
                $responce['respcode'] = $result->ReturnId;
                $inputs['respmsg'] = $result->Message;
            }
        }

        $this->transactionFailed();
        $this->newLog($responce['respcode'], $inputs['respmsg']);
        throw new SaderatException($responce['respcode']);
        return false;
    }
}
