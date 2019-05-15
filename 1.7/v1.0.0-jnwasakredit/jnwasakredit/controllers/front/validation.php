<?php
/**
 * @author    Jarda Nalezny <jaroslav@nalezny.cz>
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */

class JnWasakreditValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
    }

    public function postProcess()
    {
        $context = Context::getContext();
        $cart = $this->context->cart;

        if (!empty($context->cookie->id_wasakredit)) {
            $this->updateWasaId($cart->id, $context->cookie->id_wasakredit);
        }

        $sql = "SELECT * FROM `"._DB_PREFIX_."jn_wasakredit` 
        WHERE `id_cart` = '".$cart->id."'";

        $id_wasakredit = Db::getInstance()->getRow($sql)['id_wasakredit'];

        if ($cart->id_customer == 0
            || empty($id_wasakredit)
            || $cart->id_address_delivery == 0
            || $cart->id_address_invoice == 0
            || !$this->module->active
        ) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'jnwasakredit') {
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

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $mailVars = array();

        try {
            if ($id_wasakredit) {
                $this->module->validateOrder(
                    (int)$cart->id,
                    Configuration::get('PS_OS_PREPARATION'),
                    $total,
                    $this->module->displayName,
                    $id_wasakredit,
                    $mailVars,
                    (int)$currency->id,
                    false,
                    $customer->secure_key
                );
            } else {
                $this->module->validateOrder(
                    (int)$cart->id,
                    Configuration::get('PS_OS_ERROR'),
                    $total,
                    $this->module->displayName,
                    $id_wasakredit,
                    $mailVars,
                    (int)$currency->id,
                    false,
                    $customer->secure_key
                );
            }

            $context->cookie->id_wasakredit = '';
        } catch (Exception $e) {
        }

        $id_order = Order::getOrderByCartId($cart->id);

        if ($id_order) {
            Tools::redirect(
                'index.php?controller=order-confirmation&id_cart='.
                (int)$cart->id.'&id_module='.
                (int)$this->module->id.'&id_order='.
                $this->module->currentOrder.'&key='.
                $customer->secure_key
            );
        } else {
            $this->errors[] = $this->trans(
                'Something is wrong with your payment, please contact us.',
                array(),
                'Modules.wasakredit.Shop'
            );
            $this->redirectWithNotifications(
                $this->context->link->getPageLink(
                    'order',
                    true,
                    null,
                    array(
                        'step' => '3'
                    )
                )
            );
        }
    }

    private function updateWasaId($id_cart, $id_wasakredit)
    {
        $sql = "SELECT * FROM `"._DB_PREFIX_."jn_wasakredit` 
        WHERE `id_cart` = '".(int)$id_cart."'";

        if (empty(Db::getInstance()->getRow($sql))) {
            $sql = 'INSERT INTO '._DB_PREFIX_.'jn_wasakredit 
            (id_cart,id_wasakredit) 
            VALUES ("'.(int)$id_cart.'","'.pSQL($id_wasakredit).'")';
        } else {
            $sql = 'UPDATE '._DB_PREFIX_.'jn_wasakredit 
            SET id_wasakredit = "'.pSQL($id_wasakredit).'" 
            WHERE  id_cart = "'.(int)$id_cart.'"';
        }

        return Db::getInstance()->execute($sql);
    }
}
