<?php

return [

	//-------------------------------
	// Timezone for insert dates in database
	// If you want Gateway not set timezone, just leave it empty
	//--------------------------------
	'timezone' => 'Asia/Tehran',

	//--------------------------------
	// Zarinpal gateway
	//--------------------------------
	'zarinpal' => [
		'merchant-id'  => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
		'type'         => 'zarin-gate',             // Types: [zarin-gate || normal]
		'callback-url' => '/',
		'server'       => 'germany',                // Servers: [germany || iran || test]
		'email'        => 'email@gmail.com',
		'mobile'       => '09xxxxxxxxx',
		'description'  => 'description',
	],

	//--------------------------------
	// Mellat gateway
	//--------------------------------
	'mellat' => [
		'username'     => '',
		'password'     => '',
		'terminalId'   => 0000000,
		'callback-url' => '/'
	],

	//--------------------------------
	// Saman gateway
	//--------------------------------
	'saman' => [
		'merchant'     => '',
		'password'     => '',
		'callback-url'   => '/',
	],

	//--------------------------------
	// Payline gateway
	//--------------------------------
	'payline' => [
		'api' => 'xxxxx-xxxxx-xxxxx-xxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxxxx',
		'callback-url' => '/'
	],

	//--------------------------------
	// Sadad gateway
	//--------------------------------
	'sadad' => [
		'merchant'      => '',
		'transactionKey'=> '',
		'terminalId'    => 000000000,
		'callback-url'  => '/'
	],

	//--------------------------------
	// JahanPay gateway
	//--------------------------------
	'jahanpay' => [
		'api' => 'xxxxxxxxxxx',
		'callback-url' => '/'
	],

	//--------------------------------
	// Parsian gateway
	//--------------------------------
	'parsian' => [
		'pin'          => 'xxxxxxxxxxxxxxxxxxxx',
		'callback-url' => '/'
	],
	//--------------------------------
	// Pasargad gateway
	//--------------------------------
	'pasargad' => [
		'terminalId'    => 000000,
		'merchantId'    => 000000,
		'certificate-path'    => storage_path('gateway/pasargad/certificate.xml'),
		'callback-url' => '/gateway/callback/pasargad'
	],
	//--------------------------------
	// Saderat gateway
	//--------------------------------
	'saderat' => [
		'MID'    => 000000000000000,
		'TID'    => 00000000,
		'PRIVATE_KEY' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
		'callback-url' => '/gateway/callback/saderat'
	],
	//--------------------------------
	// IDPay gateway
	//--------------------------------
	'IDPay' => [
		'API_KEY' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
		'sandbox' => false,
		'callback-url' => '/gateway/callback/IDPay',
		'name'        => '',
		'email'        => '',
		'mobile'       => '',
		'description'  => '',
	],
	//--------------------------------
	// Alfacoins gateway
	//--------------------------------
	'Alfacoins' => [
		'shop_name' => 'xxxxxxxx',
		'shop_password' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
		'shop_secret_key' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
		'name' => '',
		'email' => 'test@gmail.com',
		'description' => '',
		'type' => 'all',
		'currency' => 'USD',
		'callback-url' => url('/callback/alfacoin'),
		'notification-callback-url'  => url('/notificationcallback/alfacoin'),
	],
	//--------------------------------
	// Payping gateway
	//--------------------------------
	'payping' => [
		'client_id' => "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
		'client_secret' => "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
		'token' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
		'name' => '',
		'email' => 'test@gmail.com',
		'description' => '',
		'callback-url' => url('/callback/payping'),
	],
	//--------------------------------
	// Paypal gateway
	//--------------------------------
	'paypal' => [
		'client_id' => 'xxx',
		'client_secret' => 'xxx',
		'server' => 'test', // Servers: [test || main]
		'callback_url' => url('/callback/paypal'),
		'currency' => 'USD'
		// One of this list items https://developer.paypal.com/api/rest/reference/currency-codes/
	],
	//-------------------------------
	// Tables names
	//--------------------------------
	'table' => 'gateway_transactions',

	//-------------------------------
	// Proxy settings
	// first of all this settings read from config folder of app (serivces.php) if it was not exist read from below
	//--------------------------------
	'proxy' => [
		// your proxy address
		'address' => '127.0.0.1', 
		// your proxy port
		'port' => '2081',
		// using from proxy server
		'status' => 'false',
	],
];
