# 💼 Laravel Gateway Package

[larabook.ir](http://larabook.ir/اتصال-درگاه-بانک-لاراول/)

This package allows you to connect to all Iranian banks using a single, unique API.

If you encounter any bugs or issues, please inform us by creating an [issue](https://github.com/larabook/gateway/issues) on our GitHub repository.

## 🏦 Available Banks:

1. MELLAT
2. SADAD (MELLI)
3. SAMAN
4. PARSIAN
5. PASARGAD
6. ZARINPAL
7. JAHANPAY
8. PAYLINE
9. PAY (PAY.IR)
10. IDPAY (IDPAY.IR)
11. ALFACOINS (alfacoins.com)
12. PAYPING (payping.io)
13. PLISIO (plisio.net)
14. BAZARPAY
15. THAWANI

## 🛠️ Installation

Follow these steps to install the package:

1. Run the following command in your terminal:

    ```sh
    composer require larabook/gateway
    ```

2. Add the following lines to `config/app.php`:

   ```php
   'providers' => [
       // ...
       Larabookir\Gateway\GatewayServiceProvider::class,
   ],
   
   'aliases' => [
       // ...
       'Gateway' => Larabookir\Gateway\Gateway::class,
   ]
   ```

3. Publish the configuration file:

    ```sh
    php artisan vendor:publish --provider=Larabookir\Gateway\GatewayServiceProvider
    ```

4. Run migrations:

    ```sh
    php artisan migrate
    ```

## ⚙️ Configuration

The configuration file is located at `config/gateway.php`. Open the file and enter your bank's credentials.

## 🚀 Usage

You can connect to a bank using either a facade or the service container:

```php
try {
   $gateway = \Gateway::make(new \Mellat());
   // $gateway->setCallback(url('/path/to/callback/route')); // You can also change the callback
   $gateway->price(1000)->ready();
   $refId =  $gateway->refId();
   $transID = $gateway->transactionId();

   // Your code here

   return $gateway->redirect();

} catch (Exception $e) {
   echo $e->getMessage();
}
```

You can call the gateway using these methods:

1. `Gateway::make(new Mellat());`
2. `Gateway::mellat();`
3. `app('gateway')->make(new Mellat());`
4. `app('gateway')->mellat();`

Replace `MELLAT` with the desired bank's name.

In the `price` method, enter the price in IRR (RIAL).

In your callback:

```php
try {
   $gateway = \Gateway::verify();
   $trackingCode = $gateway->trackingCode();
   $refId = $gateway->refId();
   $cardNumber = $gateway->cardNumber();

   // Your code here

} catch (Exception $e) {
   echo $e->getMessage();
}
```

## 🤝 Contributing

If you're interested in contributing to this package, you can help in the following ways:

1. Improving documentation.
2. Reporting issues or bugs.
3. Collaborating on writing code and adding support for other banks.

This package is an extension of PoolPort, with added functionality and improvements.
