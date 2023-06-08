<?php

namespace Larabookir\Gateway\Payping;

use Illuminate\Support\Facades\Request;
use Larabookir\Gateway\Enum;
use Larabookir\Gateway\PortAbstract;
use Larabookir\Gateway\PortInterface;

class Payping extends PortAbstract implements PortInterface
{


    /**
     * get Authorization code service url
     *
     * @var string
     */
    protected $authorizeUrl = 'https://oauth.payping.io/connect/authorize';

    /**
     * get Authorization user TOKEN service url
     *
     * @var string
     */
    protected $getUsertokenUrl = 'https://oauth.payping.io/connect/token';

    /**
     * crate pay request service url
     *
     * @var string
     */
    protected $requestPayUrl = 'https://api.payping.ir/v1/pay';
    
    /**
     * go to bank redirect url
     *
     * @var string
     */
    protected $gotoipgUrl = 'https://api.payping.ir/v1/pay/gotoipg/';

    /**
     * go to bank redirect url
     *
     * @var string
     */
    protected $verifyUrl = 'https://api.payping.ir/v1/pay/verify';

    /**
     * get Token
     */
    public function getToken($callbackUrl, $state = '')
    {
        $client_id = $this->config->get('gateway.payping.client_id');
        $random = bin2hex(openssl_random_pseudo_bytes(32));
        $verifier = $this->base64url_encode(pack('H*', $random));
        $code_challenge = $this->base64url_encode(pack('H*', hash('sha256', $verifier)));
        \Session::put('payping_code_verifier', $verifier);

        $scope = [
            'openid',
            'pay:write',
            'product:write'
        ];

        $fields = [
            "scope" => join(" ", $scope),
            "response_type" => "code",
            "client_id" => $client_id,
            "code_challenge" => $code_challenge,
            "code_challenge_method" => "S256",
            "state" => $state,
            "redirect_uri" => $callbackUrl,
        ];
        
        $url = $this->authorizeUrl . '?' . http_build_query($fields);
        return \Redirect::to($url);
    }

    /**
     * get User token
     */
    public function getOauthCallback($redirect_uri)
    {
        $code = \Request::get('code');
        $client_id = $this->config->get('gateway.payping.client_id');
        $client_secret = $this->config->get('gateway.payping.client_secret');
        $code_verifier = \Session::get('payping_code_verifier');
        $sl = \Request::get('state');

        $fields = [
            "grant_type" => "authorization_code",
            "client_id" => $client_id,
            "client_secret" => $client_secret,
            "code_verifier" => $code_verifier,
            "code" => $code,
            "redirect_uri" => $redirect_uri
        ];

        $url = $this->getUsertokenUrl;
        $header = [];
        $response = $this->APICall($url, $fields, $header, false);

        $result = json_decode($response);
        return $result->access_token ?? null;
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
        if (!$this->callbackUrl) {
            $this->callbackUrl = $this->config->get('gateway.payping.callback-url');
        }

        return $this->makeCallback($this->callbackUrl, ['transaction_id' => $this->transactionId()]);
    }

    /**
     * Send pay request to server
     *
     * @return void
     *
     * @throws PaypingException
     */
    protected function sendPayRequest()
    {
        $this->newTransaction();

        $fields = array(
            'returnUrl'		=> $this->getCallback(),
            'payerName'		=> $this->name ? $this->name : $this->config->get('gateway.payping.name', ''),
            'description'	=> $this->description ? $this->description : $this->config->get('gateway.payping.description', ''),
            'amount'		=> $this->amount / 10, // toman
            'clientRefId'	=> $this->transactionId(),
            'payerIdentity'	=> $this->email ? $this->email :$this->config->get('gateway.payping.email', ''),
        );
        $header = array(
            'authorization: Bearer ' . $this->config->get('gateway.payping.token')
        );
        $response = $this->APICall($this->requestPayUrl, $fields, $header, true);
        $jsonresponse = json_decode($response);
        if (!empty($jsonresponse) && empty($jsonresponse->Error)) {
            $this->refId = $jsonresponse->code;
            $this->gatewayUrl = $this->gotoipgUrl . $jsonresponse->code;
            $this->transactionSetRefId();
            return true;
        }

        $this->transactionFailed();
        $this->newLog(-100, $jsonresponse->Error ?? $response);
        throw new PaypingException(-100, $jsonresponse->Error ?? $response);
    }

    /**
     * Verify payment
     */
    protected function verifyPayment()
    {
        $refId = \Request::get('refid', false);
        $fields = array(
            'amount'	=> $this->amount / 10,
            'refId'		=> $refId,
        );
        $header = array(
            'Accept: application/json',
            'authorization: Bearer ' . $this->config->get('gateway.payping.token')
        );
        $result = $this->APICall($this->verifyUrl, $fields, $header, true);
        $jsonresult = json_decode($result);
        if (isset($jsonresult->success)) {
            $this->trackingCode = $refId;
            $this->cardNumber = \Request::get('cardnumber', '');
            $this->transactionSucceed();
            $this->newLog(100, Enum::TRANSACTION_SUCCEED_TEXT);
            return true;
        }

        $this->transactionFailed();
        $this->newLog(-1, $result);
        throw new PaypingException(-1, $result);
    }


    /**
     * send Post Request
     */
    private function APICall($url, $fields, $header = [], $isJson = false)
    {
        $postvars = '';
        if ($isJson) {
            $postvars = json_encode($fields);
        } else {
            foreach ($fields as $key => $value) {
                $postvars .= $key . "=" . $value . "&";
            }
        }
        array_push($header, "cache-control: no-cache");
        if ($isJson) {
            array_push($header, "content-type: application/json");
        }

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

        if ($err) {
            \Log::error($err);
            return false;
        } else {
            return $response;
        }
    }

    // encode data to base64url
    private function base64url_encode($plainText)
    {
        return rtrim(strtr(base64_encode($plainText), '+/', '-_'), '=');
    }
}
