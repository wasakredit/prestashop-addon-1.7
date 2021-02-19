<?php
/**
 * @author    Wasa Kredit AB
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */

use Sdk\AccessToken;
use Sdk\Api;
use Sdk\ClientFactory;
use Sdk\Client;
use Sdk\Response;

require _PS_MODULE_DIR_.'wasakredit/vendor/wasa/client-php-sdk/Wasa.php';
require_once _PS_MODULE_DIR_.'wasakredit/utility/SdkHelper.php';

class WasakreditPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 ||
            $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 ||
            !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'wasakredit') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die(
                $this->trans(
                    'This payment method is not available.',
                    array(),
                    'Modules.wasakredit.Shop'
                )
            );
        }

        $this->context->smarty->assign($this->getTemplateVars());

        $this->setTemplate('module:wasakredit/views/templates/front/wasa_checkout.tpl');
    }

    public function getTemplateVars()
    {
        $this->_client = Wasa_Kredit_Checkout_SdkHelper::CreateClient();

        $cart = $this->context->cart;

        $customer = new Customer($cart->id_customer);
        $purchaser_email = $customer->email;

        $b_address = new Address($cart->id_address_invoice);
        $d_address = new Address($cart->id_address_delivery);
        $purchaser_name = $b_address->firstname.' '.$b_address->lastname;

        $billing_address = array(
            'company_name' => $b_address->company,
            'street_address' => $b_address->address1,
            'postal_code' => $b_address->postcode,
            'city' => $b_address->city,
            'country' => $this->context->country->getNameById($this->context->language->id, $b_address->id_country),
        );

        $delivery_address = array(
            'company_name' => $d_address->company,
            'street_address' => $d_address->address1,
            'postal_code' => $d_address->postcode,
            'city' => $d_address->city,
            'country' => $this->context->country->getNameById($this->context->language->id, $d_address->id_country),
        );

        $recipient_name = $d_address->firstname.' '.$d_address->lastname;

        $cart_products = $cart->getProducts();
        $cart_items = array();

        foreach ($cart_products as $product) {
            $item = array(
              'product_id' => $product['id_product'],
              'product_name' => $product['name'],
              'price_ex_vat' => array(
                'amount' => $product['price_with_reduction_without_tax'],
                'currency' => $this->context->currency->iso_code
              ),
              'quantity' => $product['cart_quantity'],
              'vat_percentage' => $product['rate'],
              'vat_amount' => array(
                'amount' => ($product['price_with_reduction']-$product['price_with_reduction_without_tax']),
                'currency' => $this->context->currency->iso_code
              )
            );

            array_push($cart_items, $item);
        }
        $purchaser_phone = '';

        if (!empty($b_address->phone_mobile)) {
            $purchaser_phone = $b_address->phone_mobile;
        } elseif (!empty($b_address->phone)) {
            $purchaser_phone = $b_address->phone;
        }

        $recipient_phone = '';

        if (!empty($d_address->phone_mobile)) {
            $recipient_phone = $d_address->phone_mobile;
        } elseif (!empty($d_address->phone)) {
            $recipient_phone = $d_address->phone;
        }

        $payload = array(
          'payment_types' => 'leasing',
          'order_reference_id' => $cart->secure_key,
          'order_references' => array(
                    array(
                        'key' => 'partner_checkout_id',
                        'value' => $cart->secure_key
                    ),
                    array(
                        'key' => 'partner_reserved_order_number',
                        'value' => $cart->secure_key
                    ),
          ),
          'purchaser_name' => $purchaser_name,
          'purchaser_email' => $purchaser_email,
          'customer_organization_number' => $b_address->dni,
          'purchaser_phone' => $purchaser_phone,
          'delivery_address' => $delivery_address,
          'billing_address' => $billing_address,
          'recipient_name' => $recipient_name,
          'recipient_phone' => $recipient_phone,
          'cart_items' => $cart_items,
          'shipping_cost_ex_vat' => array(
              'amount' => $cart->getTotalShippingCost(null, false),
              'currency' => $this->context->currency->iso_code
          ),
          'request_domain' => $this->context->link->getBaseLink(),
          'confirmation_callback_url' => $this->context->link->getModuleLink(
              'wasakredit',
              'payment',
              array(),
              true
          ),
          'ping_url' => $this->context->link->getModuleLink(
              'wasakredit',
              'validation',
              array(),
              true
          )
        );

        $response = $this->_client->create_checkout($payload);

        if (!empty($response->data['invalid_properties'][0]['error_message'])) {
            $response = $response->data['invalid_properties'][0]['error_message'];
            $error = true;
            $wasa_id = 0;
        } else {
            preg_match('/id=([^"]+)/', $response->data, $wasa_id);
            $error = false;
            $wasa_id = $wasa_id[1];
        }

        return array(
            'response' => $response,
            'iframe' => $response->data,
            'wasa_id' => $wasa_id,
            'error' => $error,
            'order_reference_id' => $cart->secure_key,
            'secure_key' => $customer->secure_key,
            'id_cart' => $cart->id,
            'redirect' => $this->context->link->getModuleLink(
                'wasakredit',
                'validation',
                array(),
                true
            ),
            'ajax' => $this->context->link->getModuleLink(
                'wasakredit',
                'ajax',
                array(),
                true
            ),
        );
    }
}
