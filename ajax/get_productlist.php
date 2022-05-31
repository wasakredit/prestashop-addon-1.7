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

if (!Configuration::get('WASAKREDIT_LEASING_ENABLED')) {
    echo '';
    return;
}

$product_ids = Tools::getValue('product_ids');
if (count($product_ids) > 0) {
    $products = array();

    foreach ($product_ids as $product_id) {
        if ($product_id > 0) {
            $price = Product::getPriceStatic($product_id, false, null, 2, null, false, true, 1, false, null, 10000);

            $to_array = array(
                'financed_price' => array(
                    'amount' => number_format($price, 2, '.', ''),
                    'currency' => 'SEK'
                ),
                'product_id' => $product_id
            );
            $products[] = $to_array;
        }
    }

    $payload = array('items' => $products);

    $response = $client->calculate_monthly_cost($payload);
    echo json_encode($response->data['monthly_costs']);
} else {
    echo '';
}
