<?php
/**
 * 2008 - 2017 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @author    Jarda Nalezny <jaroslav@nalezny.cz>
 * @copyright Copyright (c) permanent, Wasa Kredit B2B
 * @license   Wasa Kredit B2B
 * @version   1.0.0
 * @link      http://www.wasakredit.se
 *
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

use Sdk\AccessToken;
use Sdk\Api;
use Sdk\Client;
use Sdk\Response;

require _PS_MODULE_DIR_.'jnwasakredit/wasa/Wasa.php';

class JnWasaKredit extends PaymentModule
{
    private $html = '';
    private $postErrors = array();

    public $CLIENTID;
    public $CLIENTSECRET;
    public $TEST;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'jnwasakredit';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.3';
        $this->author = 'Jarda Nalezny';
        $this->controllers = array('payment', 'validation', 'ajax');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->module_key = 'cbeeaf12d953737cdfc75636d737286a';
        $this->bootstrap = true;
        $this->shop_url = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__;

        $config = Configuration::getMultiple(array(
            'JN_WASAKREDIT_CLIENTID',
            'JN_WASAKREDIT_CLIENTSECRET',
            'JN_WASAKREDIT_TEST'
        ));

        $this->_client = new Client(
            $config['JN_WASAKREDIT_CLIENTID'],
            $config['JN_WASAKREDIT_CLIENTSECRET'],
            $config['JN_WASAKREDIT_TEST']
        );
        
        if (isset($config['JN_WASAKREDIT_CLIENTID'])) {
            $this->CLIENTID = $config['JN_WASAKREDIT_CLIENTID'];
        }
        if (isset($config['JN_WASAKREDIT_CLIENTSECRET'])) {
            $this->CLIENTSECRET = $config['JN_WASAKREDIT_CLIENTSECRET'];
        }
        if (isset($config['JN_WASAKREDIT_TEST'])) {
            $this->TEST = $config['JN_WASAKREDIT_TEST'];
        }


        parent::__construct();

        $this->displayName = $this->trans(
            'Wasa Kredit',
            array(),
            'Modules.wasakredit.Admin'
        );
        $this->description = $this->trans(
            'The Wasa Kredit B2B payment',
            array(),
            'Modules.wasakredit.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to delete these details?',
            array(),
            'Modules.wasakredit.Admin'
        );
        $this->ps_versions_compliancy = array('min' => '1.7.1.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'jn_wasakredit` (
            `id_cart` int unsigned NOT NULL,
            `id_wasakredit` varchar(36) COLLATE "utf8_general_ci" NOT NULL
            ) ENGINE='._MYSQL_ENGINE_.' COLLATE "utf8_general_ci"');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayHeader')
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
        ;
    }

    public function uninstall()
    {
        return Configuration::deleteByName('JN_WASAKREDIT_CLIENTID')
            && Configuration::deleteByName('JN_WASAKREDIT_CLIENTSECRET')
            && Configuration::deleteByName('JN_WASAKREDIT_TEST')
            && parent::uninstall()
        ;
    }

    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('JN_WASAKREDIT_CLIENTID')) {
                $this->postErrors[] = $this->trans(
                    'The "CLIENTID" field is required.',
                    array(),
                    'Modules.wasakredit.Admin'
                );
            } elseif (!Tools::getValue('JN_WASAKREDIT_CLIENTSECRET')) {
                $this->postErrors[] = $this->trans(
                    'The "CLIENTSECRET" field is required.',
                    array(),
                    'Modules.wasakredit.Admin'
                );
            }
        }
    }

    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(
                'JN_WASAKREDIT_CLIENTID',
                Tools::getValue('JN_WASAKREDIT_CLIENTID')
            );
            Configuration::updateValue(
                'JN_WASAKREDIT_CLIENTSECRET',
                Tools::getValue('JN_WASAKREDIT_CLIENTSECRET')
            );
            Configuration::updateValue(
                'JN_WASAKREDIT_TEST',
                Tools::getValue('JN_WASAKREDIT_TEST')
            );
        }
        $this->html .= $this->displayConfirmation(
            $this->trans(
                'Settings updated',
                array(),
                'Admin.Notifications.Success'
            )
        );
    }

    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->html .= $this->renderForm();

        return $this->html;
    }

    public function hookdisplayHeader($params)
    {
        $this->context->controller->addCSS($this->_path.'views/css/jn_wasakredit.css');
        $this->context->controller->addJS($this->_path.'views/js/jn_wasakredit.js');
        $this->context->controller->addJS($this->_path.'views/js/jn_wasaajax.js');

        $this->smarty->assign(array(
            'ajax' => $this->context->link->getBaseLink().'modules/jn_wasakredit/ajax/'
        ));

        return $this->fetch('module:jnwasakredit/views/templates/hook/header_productlistajax.tpl');
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if ($this->active && isset($params['hook_origin']) && $params['hook_origin'] == 'product_sheet') {
            $product_price = 1000;
            if (isset($params['product']['price_with_reduction'])) {
                $product_price = $params['product']['price_with_reduction'];
            } elseif ($params['product']['price_amount']) {
                $product_price = $params['product']['price_tax_exc'];
            }

            $product_info = array(
                          'financial_product' => 'leasing',
                          'price_ex_vat' => array(
                            'amount' => $product_price,
                            'currency' => 'SEK'
                          )
                       );

            $response = $this->_client->create_product_widget($product_info);

            $dom = new DOMDocument();
            $dom->loadHTML($response->data);
            $xpath = new DOMXPath($dom);
            $span = $xpath->query('//span[@class="wasa-kredit-product-widget__price"]/text()');
            $span = $span->item(0);
            $price = $dom->saveXML($span);

            $dom = new DOMDocument();
            $dom->loadHTML(mb_convert_encoding($response->data, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            $span = $xpath->query('//span[@class="wasa-kredit-product-widget__info"]/text()');
            $span = $span->item(0);
            $text = $dom->saveXML($span);

            $this->smarty->assign(array(
                'widget' => $response->data,
                'price' => html_entity_decode($price),
                'text' => $text
            ));

            return $this->fetch('module:jnwasakredit/views/templates/hook/displayProductPriceBlock.tpl');
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $this->context->smarty->assign(array(
            'payments' => $this->getCheckoutPaymentOptions($params),
            'logo' => $this->shop_url.'modules/jnwasakredit/logo.png',
        ));


        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name)
            ->setCallToActionText(
                $this->trans(
                    'Wasa Kredit',
                    array(),
                    'Modules.wasakredit.Admin'
                )
            )
            ->setAction(
                $this->context->link->getModuleLink(
                    $this->name,
                    'payment',
                    array(),
                    true
                )
            )
            ->setAdditionalInformation(
                $this->fetch('module:jnwasakredit/views/templates/front/payment_infos.tpl')
            );

        return array($newOption);
    }

    public function getCheckoutPaymentOptions($params)
    {
        $partner_id = $this->CLIENTID;
        $cart = new Cart($this->context->cookie->id_cart);
        $amount = $cart->getOrderTotal();

        $ch = curl_init();
        $partner_id = Configuration::get('JN_WASAKREDIT_CLIENTID');

        $url = "https://b2b.services.wasakredit.se/v2/payment-methods?total_amount=".$amount."&currency=SEK&partner_id=".$partner_id;

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          "x-test-mode: true"
        ));

        $plans = curl_exec($ch);
        curl_close($ch);

        $plans = json_decode($plans);

        return $plans->payment_methods[0]->options->contract_lengths;
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (in_array($state, array(Configuration::get('PS_OS_PREPARATION')))) {
            $this->smarty->assign(array(
                'total_to_pay' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
                    false
                ),
                'shop_name' => $this->context->shop->name,
                'status' => 'ok',
                'id_order' => $params['order']->id
            ));
            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $this->smarty->assign('reference', $params['order']->reference);
            }
        } else {
            $this->smarty->assign('status', 'failed');
        }
        return $this->fetch('module:jnwasakredit/views/templates/hook/payment_return.tpl');
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Contact details', array(), 'Modules.wasakredit.Admin'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Client ID', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'JN_WASAKREDIT_CLIENTID',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Client secret key', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'JN_WASAKREDIT_CLIENTSECRET',
                        'required' => true
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Test mode', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'JN_WASAKREDIT_TEST',
                        'values' => array(
                            array(
                                'id' => 'JN_WASAKREDIT_TEST_on',
                                'value' => 1
                            ),
                            array(
                                'id' => 'JN_WASAKREDIT_TEST_off',
                                'value' => 0
                            )
                        )
                    ),

                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                )
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).
            '&configure='.$this->name.
            '&tab_module='.$this->tab.
            '&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'JN_WASAKREDIT_CLIENTID' => Tools::getValue(
                'JN_WASAKREDIT_CLIENTID',
                Configuration::get('JN_WASAKREDIT_CLIENTID')
            ),
            'JN_WASAKREDIT_TEST' => Tools::getValue(
                'JN_WASAKREDIT_TEST',
                Configuration::get('JN_WASAKREDIT_TEST')
            ),
            'JN_WASAKREDIT_CLIENTSECRET' => Tools::getValue(
                'JN_WASAKREDIT_CLIENTSECRET',
                Configuration::get('JN_WASAKREDIT_CLIENTSECRET')
            ),
        );
    }
}
