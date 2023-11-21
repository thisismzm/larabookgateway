<?php

namespace Larabookir\Gateway\Paypal;

use Larabookir\Gateway\Exceptions\BankException;

class PaypalException extends BankException
{
    public static $errors = array(
        0 => 'Proxy server setting Error.',
        200 => 'OK, The request succeeded.',
		201 => 'Created, A POST method successfully created a resource. If the resource was already created by a previous execution of the same method, for example, the server returns the HTTP 200 OK status code.',
        202 => 'Accepted, The server accepted the request and will execute it later.',
        204 => 'No Content, The server successfully executed the method but returns no response body.',
        400 => 'Bad Request, INVALID_REQUEST. Request is not well-formed, syntactically incorrect, or violates schema.',
        401 => 'Bad Request or Unauthorized',
        403 => 'Forbidden, NOT_AUTHORIZED. Authorization failed due to insufficient permissions.',
        404 => 'Not Found, RESOURCE_NOT_FOUND. The specified resource does not exist.',
        405 => 'Method Not Allowed, METHOD_NOT_SUPPORTED. The server does not implement the requested HTTP method.',
        406 => 'Not Acceptable, MEDIA_TYPE_NOT_ACCEPTABLE. The server does not implement the media type that would be acceptable to the client.',
        409 => 'Conflict, RESOURCE_CONFLICT. Request cannot be processed as it conflicts with another request.',
        415 => 'Unsupported Media Type, UNSUPPORTED_MEDIA_TYPE. The server does not support the request payload’s media type.',
        422 => 'Unprocessable Entity, UNPROCESSABLE_ENTITY. The API cannot complete the requested action, or the request action is semantically incorrect or fails business validation.',
        429 => 'Too Many Requests, RATE_LIMIT_REACHED. Too many requests. Blocked due to rate limiting.',
        500 => 'Internal Server Error, INTERNAL_SERVER_ERROR. An internal server error has occurred.',
        503 => 'Service Unavailable, SERVICE_UNAVAILABLE. Service Unavailable.',
    );

    public function __construct($errorId)
    {
        $this->errorId = intval($errorId);

        parent::__construct((isset(self::$errors[$this->errorId]) ? self::$errors[$this->errorId] : '').' #'.$this->errorId, $this->errorId);
    }
}
