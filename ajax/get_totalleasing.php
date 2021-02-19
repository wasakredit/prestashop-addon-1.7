<?php
/**
 * @author    Wasa Kredit AB
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */

require(dirname(__FILE__).'./../../../config/config.inc.php');

use Sdk\AccessToken;
use Sdk\Api;
use Sdk\ClientFactory;
use Sdk\Client;
use Sdk\Response;

require _PS_MODULE_DIR_.'wasakredit/vendor/wasa/client-php-sdk/Wasa.php';
require_once _PS_MODULE_DIR_.'wasakredit/utility/SdkHelper.php';

$client = Wasa_Kredit_Checkout_SdkHelper::CreateClient();

$total = Tools::getValue('total');

if ($total > 0) {

    $response = $client->get_leasing_payment_options($total);
    if (!empty($response->data['contract_lengths'])) {
        echo json_encode($response->data['contract_lengths']);
    } else {
        echo '';
    }
} else {
    echo '';
}
