<?php
/**
 ** ***************************************************************** **\
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
\                                                                       *
************************************************************************/

if (!defined("WHMCS")) {
    die("<!-- Err... What exactly are you trying to do? -->");
}

/**
 * Define Ravepay configuration options.
 *
 * @return array
 */
function ravepay_config()
{   
    return array(
        'FriendlyName' => array(
            'Type'  => 'System',
            'Value' => 'Ravepay (Debit/Credit Cards, Bank and USSD)'
        ),
        'customTitle' => array(
            'FriendlyName' => 'Custom Title',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'myBusinessName.com'
        ),
        'customLogo' => array(
            'FriendlyName' => 'Custom Logo',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'http://example.com/logo.jpg'
        ),
        'gatewayLogs' => array(
            'FriendlyName' => 'Gateway logs',
            'Type' => 'yesno',
            'Description' => 'Select to enable gateway logs',
            'Default' => '0'
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Select to enable test mode',
            'Default' => '0'
        ),
        'liveSecretKey' => array(
            'FriendlyName' => 'Secret Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'FLWSECK-xxxxxx-X'
        ),
        'livePublicKey' => array(
            'FriendlyName' => 'Public Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'FLWPUBK-xxxxx-X'
        ),
        'testSecretKey' => array(
            'FriendlyName' => 'Test Secrect Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'FLWSECK-xxxxxx-X'
        ),
        'testPublicKey' => array(
            'FriendlyName' => 'Test Public Key',
            'Type' => 'text',
            'Size' => '32',
            'Default' => 'FLWPUBK-xxxxxx-X'
        )
    );
}


/**
 * Payment link.
 *
 * @param array $params Payment Gateway Module Parameters
 *
 * @return string
 */
function ravepay_link($params)
{
    // Client
    $email = $params['clientdetails']['email'];
    $phone = $params['clientdetails']['phonenumber'];
    $country = $params['clientdetails']['country'];
    $params['langpaynow'] = 
        array_key_exists('langpaynow', $params) ? 
            $params['langpaynow'] : 'Pay with Card/Bank/Paypal' ;

    // Config Options
    if ($params['testMode'] == 'on') {
        $publicKey = $params['testPublicKey'];
        $secretKey = $params['testSecretKey'];
        $scriptUrl = "http://flw-pms-dev.eu-west-1.elasticbeanstalk.com/flwv3-pug/getpaidx/api/flwpbf-inline.js";
    } else {
        $publicKey = $params['livePublicKey'];
        $secretKey = $params['liveSecretKey'];
        $scriptUrl = "https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js";
    }
    
    
    // Invoice
    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $transaction_ref   = $invoiceId . '_' .time();

    $callbackUrl = $params['systemurl'] . 'modules/gateways/callback/ravepay.php?'  . 
        http_build_query(array(
            'invoiceid'=>$invoiceId
        ));   

    $code = '
    	<a class="flwpug_getpaid" data-PBFPubKey="' . $publicKey . '" data-txref="' . $transaction_ref . '" data-amount="' . $amount . '" data-customer_email="' . $email . '" data-currency = "' . strtoupper($currency) .'" data-pay_button_text = "" data-country="NG" data-custom_title = "' . $params['customTitle'] .'" data-custom_description = "' . $description .'" data-redirect_url = "' . $callbackUrl . '" data-custom_logo = "' . $params['customLogo'] .'" data-payment_method = "both" data-exclude_banks=""></a>
    	<script type="text/javascript" src="' . $scriptUrl . '"></script>  
    ';

    return $code;
}
