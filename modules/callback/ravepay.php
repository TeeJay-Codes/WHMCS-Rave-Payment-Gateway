<?php

// Hack to collect the $_get data before the cms messes with it.
$response = $_GET['resp'];        

/**
/ ********************************************************************* \
 *                                                                      *
 *   Ravepay Payment Gateway                                            *
 *   Version: 1.0.0                                                     *
 *   Build Date: 13 November 2017                                       *
 *                                                                      *
 ************************************************************************
 *                                                                      *
 *   Email: support@ravepay.co                                         *
 *   Website: https://www.ravepay.co                                   *
 *                                                                      *
\ ********************************************************************* /
**/

// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

// Detect module name from filename.
$gatewayModuleName = basename(__FILE__, '.php');

// Fetch gateway configuration parameters.
$gatewayParams = getGatewayVariables($gatewayModuleName);

// Die if module is not active.
if (!$gatewayParams['type']) {
    die("Module Not Activated");
}


if ($_GET['resp'])
{
    $response = json_decode($response);
    
    $flw_ref = $response->tx->flwRef;

    $chargeResponse = $response->tx->chargeResponseCode;

    $invoiceId = explode('_', $response->tx->txRef);
    
    /**
     * Validate Callback Invoice ID.
     *
     * Checks invoice ID is a valid invoice number. Note it will count an
     * invoice in any status as valid.
     *
     * Performs a die upon encountering an invalid Invoice ID.
     *
     * Returns a normalised invoice ID.
     *
     * @param int $invoiceId Invoice ID
     * @param string $gatewayName Gateway Name
     */
    $invoiceId = checkCbInvoiceID($invoiceId[0], $getewayParams['name']);
    
    /**
     * Check Callback Transaction ID.
     *
     * Performs a check for any existing transactions with the same given
     * transaction number.
     *
     * Performs a die upon encountering a duplicate.
     *
     * @param string $transactionId Unique Transaction ID
     */
    checkCbTransID($flw_ref);

    // get amount
    $result = select_query('tblinvoices', 'total', array('id'=>$invoiceId));
    $data = mysql_fetch_array($result);
    
    $amount = $data['total'];

    $result = select_query("tblclients", "tblinvoices.invoicenum,tblclients.currency,tblcurrencies.code", array("tblinvoices.id" => $invoiceId), "", "", "", "tblinvoices ON tblinvoices.userid=tblclients.id INNER JOIN tblcurrencies ON tblcurrencies.id=tblclients.currency");
    $data = mysql_fetch_array($result);
    $currency = $data['code'];
    // $result = select_query("tblcurrencies", "code", array("id" => $data['currency']));
    //        $data = mysql_fetch_array($result);
    //        $currency = $data['code'];


    if ($gatewayParams['testMode'] == 'on') {
        $secretKey = $gatewayParams['testSecretKey'];
        $endpoint = 'http://flw-pms-dev.eu-west-1.elasticbeanstalk.com/flwv3-pug/getpaidx/api/verify';
    } else {
        $secretKey = $gatewayParams['liveSecretKey'];
        $endpoint = 'https://api.ravepay.co/flwv3-pug/getpaidx/api/verify';
    }

        $query = array(
            "SECKEY" => $secretKey,
            "flw_ref" => $flw_ref
        );

        $data_string = json_encode($query);
                
        $ch = curl_init($endpoint);                                                                      
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);                                              
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $transaction_report = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($transaction_report, 0, $header_size);
        $body = substr($transaction_report, $header_size);

        curl_close($ch);

        $resp = json_decode($transaction_report);


        $chargeResponse = $resp->data->flwMeta->chargeResponse;
        $chargeAmount = $resp->data->amount;
        $chargeCurrency = $resp->data->transaction_currency;
        $fee = $resp->data->appfee;
        $invoiceUrl = $gatewayParams['systemurl'] . "viewinvoice.php?id=" . $invoiceId;

            if (($chargeResponse == "00" || $chargeResponse == "0") && ($chargeCurrency == $currency)) {
              // Mark the invoice as paid
            addInvoicePayment($invoiceId, $resp->data->flw_ref, $chargeAmount, $fee, $gatewayModuleName);

            // Add transaction to Gateway logs
            if ($gatewayParams['gatewayLogs'] == 'on') {
                $output = "Transaction ref: " . $resp->data->flw_ref
                    . "\r\nOriginal Transaction ref: " . $flw_ref
                    . "\r\nOrder_ref: " . $resp->data->order_ref
                    . "\r\nInvoice ID: " . $invoiceId
                    . "\r\nStatus: success"
                    . "\r\nChargeResponse: " . $chargeResponse
                    . "\r\nChargeCurrency: " . $chargeCurrency
                    . "\r\namount: " . $amount
                    . "\r\nDump: " . $transaction_report;
                logTransaction($gatewayModuleName, $output, "Successful");
            }

            header("Location: " . $invoiceUrl);
            exit;
            
        } else {
            // Add transaction to Gateway logs
            if ($gatewayParams['gatewayLogs'] == 'on') {
                $output = "Transaction ref: " . $resp->data->flw_ref
                    . "\r\nOriginal Transaction ref: " . $flw_ref
                    . "\r\nOrder_ref: " . $resp->data->order_ref
                    . "\r\nInvoice ID: " . $invoiceId
                    . "\r\nStatus: failed"
                    . "\r\nChargeResponse: " . $chargeResponse
                    . "\r\nChargeCurrency: " . $chargeCurrency
                    . "\r\namount: " . $amount
                    . "\r\nDump: " . $transaction_report;
                logTransaction($gatewayModuleName, $output, "Failed");
            }

            header("Location: " . $invoiceUrl);
            exit;

        }

}
