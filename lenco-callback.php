<?php
//this is the call back file for lenco
require_once "../../../init.php";
require_once "../../../includes/gatewayfunctions.php";
require_once "../../../includes/invoicefunctions.php";

$gatewayModuleName = "lenco";
$gatewayParams     = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams["type"]) {
    die("Module not activated.");
}

$invoiceId = $_GET['invoiceid'] ?? '';
$reference = $_GET['reference'] ?? '';

if (!$invoiceId || !$reference) {
    die("Invalid request parameters.");
}

//ensure invoice exists and is unpaid 
$invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);

$secretKey = $gatewayParams['secretKey'];
$apiUrl    = "https://api.lenco.co/access/v2/collections/status/" . urlencode($reference);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$secretKey}",
    "Accept: application/json"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$responseData = json_decode($response, true);

logTransaction($gatewayModuleName, [
    'GET'         => $_GET,
    'HTTPCode'    => $httpCode,
    'APIResponse' => $responseData
], "Lenco Callback Verification");

if ($httpCode === 200 && !empty($responseData['status']) && $responseData['data']['status'] === 'successful') {
    $transactionId = $responseData['data']['lencoReference'] ?? $reference;
    $amountZMWPaid = floatval($responseData['data']['amount']);

    //reverse conversion back to WHMCS Invoice Currency (USD)
    $exchangeRate  = floatval($gatewayParams['exchangeRate']) > 0 ? floatval($gatewayParams['exchangeRate']) : 1.0;
    $amountInWHMCS = round($amountZMWPaid / $exchangeRate, 2);

    //Prevent duplicate transaction processing die
    checkCbTransID($transactionId);

    //add payment to invoice
    addInvoicePayment($invoiceId, $transactionId, $amountInWHMCS, 0, $gatewayModuleName);
    logTransaction($gatewayModuleName, $responseData, "Successful Payment Applied");
} else {
    logTransaction($gatewayModuleName, $responseData, "Unsuccessful Payment");
}

header("Location: " . rtrim($gatewayParams['systemurl'], '/') . "/viewinvoice.php?id=" . $invoiceId);
exit();
