<?php
/**
 * @author    Jarda Nalezny <jaroslav@nalezny.cz>
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 *
 */

class JnWasakreditAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
    }

    public function postProcess()
    {

        $secure_key = Context::getContext()->customer->secure_key;

        if (!Tools::isSubmit('secure_key') ||
            Tools::getValue('secure_key') != $secure_key ||
            !Tools::getValue('action')
        ) {
            die(json_encode(array('progress' => 'back')));
        }

        $id_cart = Tools::getValue('id_cart');
        $id_wasakredit = Tools::getValue('id_wasakredit');
        $order_reference_id = Tools::getValue('order_reference_id');
        $action = Tools::getValue('action');

        if ($action == 'update_wasaid') {
            if ((!empty($id_wasakredit)) &&
                (!empty($id_cart)) &&
                (!empty($order_reference_id))
            ) {
                $sql = "SELECT * FROM `"._DB_PREFIX_."cart` 
                WHERE `id_cart` = '".(int)$id_cart."' 
                AND `secure_key` = '".psql($order_reference_id)."'";

                if (!empty(Db::getInstance()->getRow($sql))) {
                    $context = Context::getContext();
                    $context->cookie->id_wasakredit = $id_wasakredit;
                    die(json_encode(array('progress' => 'go')));
                }

                die(json_encode(array('progress' => 'back')));
            }
        }

        die(json_encode(array('progress' => 'back')));
    }
}
