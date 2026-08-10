<?php
/*this is the main file for lenco
for help call 0973215564 */
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly.");
}

function lenco_MetaData()
{
    return [
        'DisplayName' => 'Lenco Payment Gateway',
        'APIVersion' => '1.1',
    ];
}

function lenco_config()
{
    return [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "Lenco",
        ],
        "publicKey" => [
            "FriendlyName" => "Lenco Public Key",
            "Type" => "text",
            "Size" => "50",
            "Default" => "",
            "Description" => "Enter your Lenco public key here", //you need to get your public keys from lenco official site
        ],
        "secretKey" => [
            "FriendlyName" => "Lenco Secret Key",
            "Type" => "password",
            "Size" => "50",
            "Default" => "",
            "Description" => "Enter your Lenco secret key here",
        ],
        "exchangeRate" => [
            "FriendlyName" => "USD to ZMW Exchange Rate",
            "Type" => "text",
            "Size" => "10",
            "Default" => "28.50",
            "Description" => "Set to base currency ZMW.",
        ],
    ];
}

function lenco_link($params)
{
    $publicKey   = $params['publicKey'];
    $invoiceId   = $params['invoiceid'];
    $amountUSD   = $params['amount'];
    $email       = $params['clientdetails']['email'];
    $phone       = $params['clientdetails']['phonenumber'];
    $systemUrl   = rtrim($params['systemurl'], '/');
    $callbackUrl = $systemUrl . "/modules/gateways/callback/lenco.php?invoiceid=" . $invoiceId;

    $exchangeRate = floatval($params['exchangeRate']) > 0 ? floatval($params['exchangeRate']) : 1.0;
    $amountZMW    = round($amountUSD * $exchangeRate, 2);

    // encode variables for JavaScript
    $firstName = json_encode($params['clientdetails']['firstname']);
    $lastName  = json_encode($params['clientdetails']['lastname']);
    $emailJs   = json_encode($email);
    $phoneJs   = json_encode($phone);
    $refJs     = json_encode('ref-' . $invoiceId . '-' . time());

    return <<<HTML
        <script src="https://pay.lenco.co/js/v1/inline.js"></script>
        <button type="button" class="btn btn-primary" onclick="getPaidWithLenco()">Pay Now</button>
        <script>
            function getPaidWithLenco() {
                LencoPay.getPaid({
                    key: '{$publicKey}',
                    reference: {$refJs},
                    email: {$emailJs},
                    amount: {$amountZMW},
                    currency: "ZMW",
                    customer: {
                        firstName: {$firstName},
                        lastName: {$lastName},
                        phone: {$phoneJs}
                    },
                    onSuccess: function(response) {
                        window.location.href = '{$callbackUrl}&reference=' + encodeURIComponent(response.reference);
                    },
                    onClose: function() {
                        alert('Payment was not completed.');
                    }
                });
            }
        </script>
HTML;
}
