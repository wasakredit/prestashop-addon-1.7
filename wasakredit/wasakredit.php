<?php
/**
 * 2008 - 2022 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @author    Wasa Kredit AB
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 * @version   1.0.0
 * @link      http://www.wasakredit.se
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require _PS_MODULE_DIR_.'wasakredit/vendor/wasa/client-php-sdk/Wasa.php';
require_once _PS_MODULE_DIR_.'wasakredit/utility/SdkHelper.php';

class WasaKredit extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'wasakredit';
        $this->tab = 'payments_gateways';
        $this->version = '1.5.0';
        $this->author = 'Wasa Kredit AB';
        $this->need_instance = 0;

        $this->controllers = ['leasing', 'invoice', 'callback', 'confirm'];
        $this->module_key = 'cbeeaf12d953737cdfc75636d737286a';
        $this->bootstrap = true;

        $this->_client = Wasa_Kredit_Checkout_SdkHelper::CreateClient();

        parent::__construct();

        $this->displayName = $this->trans('Wasa Kredit B2B', [], 'Modules.wasakredit.Admin');
        $this->description = $this->trans('The Wasa Kredit B2B checkout.', [], 'Modules.wasakredit.Admin');
        $this->confirmUninstall = $this->trans('Are you sure you want to delete these details?', [], 'Modules.wasakredit.Admin');

        $this->ps_versions_compliancy = [
            'min' => '1.7.6.0',
            'max' => _PS_VERSION_
        ];
    }

    public function install()
    {
        Configuration::updateValue('WASAKREDIT_TEST', false);
        Configuration::updateValue('WASAKREDIT_CLIENTID', '');
        Configuration::updateValue('WASAKREDIT_CLIENTSECRET', '');
        Configuration::updateValue('WASAKREDIT_TEST_CLIENTID', '');
        Configuration::updateValue('WASAKREDIT_TEST_CLIENTSECRET', '');
        Configuration::updateValue('WASAKREDIT_LEASING_ENABLED', '');
        Configuration::updateValue('WASAKREDIT_INVOICE_ENABLED', '');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayProductPriceBlock');
    }

    public function uninstall()
    {
        return Configuration::deleteByName('WASAKREDIT_CLIENTID')
            && Configuration::deleteByName('WASAKREDIT_CLIENTSECRET')
            && Configuration::deleteByName('WASAKREDIT_TEST')
            && Configuration::deleteByName('WASAKREDIT_TEST_CLIENTID')
            && Configuration::deleteByName('WASAKREDIT_TEST_CLIENTSECRET')
            && Configuration::deleteByName('WASAKREDIT_LEASING_ENABLED')
            && Configuration::deleteByName('WASAKREDIT_INVOICE_ENABLED')
            && parent::uninstall();
    }

    public function getContent()
    {
        $html = '';

        if (Tools::isSubmit('btnSubmit') == true) {
            $errors = $this->postValidation();

            if (empty($errors)) {
                $status = $this->postProcess();

                if ($status) {
                    $html .= $this->displayConfirmation(
                        $this->trans('Settings updated', [], 'Admin.Notifications.Success')
                    );
                }
            } else {
                foreach ($errors as $error) {
                    $html .= $this->displayError($error);
                }
            }
        }

        $html .= $this->renderForm();

        return $html;
    }

    private function postValidation()
    {
        $errors = [];

        if (!Tools::getValue('WASAKREDIT_CLIENTID')) {
            $errors[] = $this->trans('The "CLIENTID" field is required.', [], 'Modules.wasakredit.Admin');
        }

        if (!Tools::getValue('WASAKREDIT_CLIENTSECRET')) {
            $this->postErrors[] = $this->trans('The "CLIENTSECRET" field is required.', [], 'Modules.wasakredit.Admin');
        }

        return $errors;
    }

    private function postProcess()
    {
        $values = $this->getConfigValues();

        foreach (array_keys($values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        return true;
    }

    public function hookdisplayHeader($params)
    {
        $this->context->controller->addCSS($this->_path.'views/css/wasakredit.css');
        $this->context->controller->addJS($this->_path.'views/js/wasakredit.js');
    }

    public function validate_leasing_amount($amount)
    {
        return $this->_client
            ->validate_financed_amount($amount)
            ->data['validation_result'];
    }

    public function validate_invoice_amount($amount)
    {
        return $this->_client
            ->validate_financed_invoice_amount($amount)
            ->data['validation_result'];
    }

    public function getLeasingPaymentOptions($params)
    {
        $cart = new Cart($this->context->cookie->id_cart);
        $amount = $cart->getOrderTotal();

        $response = $this->_client->get_leasing_payment_options($amount);

        return (!empty($response->data['contract_lengths']))
            ? $response->data['contract_lengths']
            : [];
    }

    public function hookPaymentOptions($params)
    {
        $cart = new Cart($this->context->cookie->id_cart);
        $amount = $cart->getOrderTotal(false);

        $this->context->smarty->assign([
            'options' => $this->getLeasingPaymentOptions($params),
            'logo'    => $this->context->link->getBaseLink() . '/modules/wasakredit/logo.png',
        ]);

        $methods = [];

        if (Configuration::get('WASAKREDIT_LEASING_ENABLED')) {
            $text = Configuration::get('WASAKREDIT_TEST')
                ? 'Wasa Kredit Leasing [TESTMODE]'
                : 'Wasa Kredit Leasing';

            $methods[] = (new PaymentOption)
                ->setCallToActionText($text)
                ->setAction($this->context->link->getModuleLink($this->name, 'leasing', [], true))
                ->setAdditionalInformation(
                    $this->fetch('module:wasakredit/views/templates/front/leasing_info.tpl')
                );
        }

        if (Configuration::get('WASAKREDIT_INVOICE_ENABLED')) {
            $text = Configuration::get('WASAKREDIT_TEST')
                ? 'Wasa Kredit Faktura [TESTMODE]'
                : 'Wasa Kredit Faktura';

            $methods[] = (new PaymentOption)
                ->setCallToActionText($text)
                ->setAction($this->context->link->getModuleLink($this->name, 'invoice', [], true))
                ->setAdditionalInformation(
                    $this->fetch('module:wasakredit/views/templates/front/invoice_info.tpl')
                );
        }

        return !empty($methods)
            ? $methods
            : [];
    }

    public function hookDisplayProductPriceBlock($params)
    {


        if (!Configuration::get('WASAKREDIT_LEASING_ENABLED')) {
            return false;
        }

        if ($params['type'] != 'after_price') {
            return false;
        }

        if (empty($params['product']->price)) {
            die('A3');
            return false;
        }

        if (isset($params['product']['price_with_reduction'])) {
            $product_price = $params['product']['price_with_reduction'];
        } elseif ($params['product']['price_amount']) {
            $product_price = $params['product']['price_tax_exc'];
        }

        $response = $this->_client->get_monthly_cost_widget($product_price);

        if ($response->statusCode != '200') {
            return false;
        }

        $this->smarty->assign('widget', $response->data);

        return $this->fetch('module:wasakredit/views/templates/hook/displayProductPriceBlock.tpl');
    }

    public function hookPaymentReturn($params)
    {
        if (!Configuration::get('WASAKREDIT_LEASING_ENABLED') || !Configuration::get('WASAKREDIT_INVOICE_ENABLED')) {
            return false;
        }

        $order = $params['order'];
        $state = $order->getCurrentState();

        if (in_array($state, [Configuration::get('PS_OS_PREPARATION')])) {
            $currency = new Currency($order->id_currency);
            $total_to_pay = Tools::displayPrice($order->getOrdersTotalPaid(), $currency, false);

            $this->smarty->assign([
                'total_to_pay' => $total_to_pay,
                'shop_name'    => $this->context->shop->name,
                'status'       => 'ok',
                'id_order'     => $order->id
            ]);

            if (!empty($order->reference)) {
                $this->smarty->assign('reference', $order->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }

        return $this->fetch('module:wasakredit/views/templates/hook/payment_return.tpl');
    }

    public function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.wasakredit.Admin'),
                    'icon' => 'icon-cogs'
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Client ID', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_CLIENTID',
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Client secret key', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_CLIENTSECRET',
                        'required' => true
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Client ID', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST_CLIENTID',
                        'required' => false
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Test Client secret key', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST_CLIENTSECRET',
                        'required' => false
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Test mode', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST',
                        'values' => [
                            [
                                'id' => 'WASAKREDIT_TEST_on',
                                'value' => 1
                            ],
                            [
                                'id' => 'WASAKREDIT_TEST_off',
                                'value' => 0
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Aktivera leasing', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_LEASING_ENABLED',
                        'values' => [
                            [
                                'id' => 'WASAKREDIT_LEASING_ENABLED_on',
                                'value' => 1
                            ],
                            [
                                'id' => 'WASAKREDIT_LEASING_ENABLED_off',
                                'value' => 0
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Aktivera faktura', [], 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_INVOICE_ENABLED',
                        'values' => [
                            [
                                'id' => 'WASAKREDIT_INVOICE_ENABLED_on',
                                'value' => 1
                            ],
                            [
                                'id' => 'WASAKREDIT_INVOICE_ENABLED_off',
                                'value' => 0
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure='.$this->name
            . '&tab_module='.$this->tab
            . '&module_name='.$this->name;

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigValues(),
        );

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigValues()
    {
        return [
            'WASAKREDIT_CLIENTID' => Tools::getValue(
                'WASAKREDIT_CLIENTID',
                Configuration::get('WASAKREDIT_CLIENTID')
            ),
            'WASAKREDIT_TEST' => Tools::getValue(
                'WASAKREDIT_TEST',
                Configuration::get('WASAKREDIT_TEST')
            ),
            'WASAKREDIT_CLIENTSECRET' => Tools::getValue(
                'WASAKREDIT_CLIENTSECRET',
                Configuration::get('WASAKREDIT_CLIENTSECRET')
            ),
            'WASAKREDIT_TEST_CLIENTID' => Tools::getValue(
                'WASAKREDIT_TEST_CLIENTID',
                Configuration::get('WASAKREDIT_TEST_CLIENTID')
            ),
            'WASAKREDIT_TEST_CLIENTSECRET' => Tools::getValue(
                'WASAKREDIT_TEST_CLIENTSECRET',
                Configuration::get('WASAKREDIT_TEST_CLIENTSECRET')
            ),
            'WASAKREDIT_LEASING_ENABLED' => Tools::getValue(
                'WASAKREDIT_LEASING_ENABLED',
                Configuration::get('WASAKREDIT_LEASING_ENABLED')
            ),
            'WASAKREDIT_INVOICE_ENABLED' => Tools::getValue(
                'WASAKREDIT_INVOICE_ENABLED',
                Configuration::get('WASAKREDIT_INVOICE_ENABLED')
            ),
        ];
    }
}
