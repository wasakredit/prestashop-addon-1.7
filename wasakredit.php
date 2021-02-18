<?php
/**
 * 2008 - 2021 Wasa Kredit B2B
 *
 * MODULE Wasa Kredit
 *
 * @author    Wasa Kredit AB
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
use Sdk\ClientFactory;
use Sdk\Response;

require _PS_MODULE_DIR_.'wasakredit/vendor/wasa/client-php-sdk/Wasa.php';
require_once _PS_MODULE_DIR_.'wasakredit/utility/SdkHelper.php';

class WasaKredit extends PaymentModule
{
    private $html = '';
    private $postErrors = array();

    public $LEASING_ENABLED;
    public $INVOICE_ENABLED;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'wasakredit';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Wasa Kredit AB';
        $this->controllers = array('leasingpayment', 'invoicepayment', 'validation', 'ajax');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->module_key = 'cbeeaf12d953737cdfc75636d737286a';
        $this->bootstrap = true;

        $config = Configuration::getMultiple(array(
            'WASAKREDIT_LEASING_ENABLED',
            'WASAKREDIT_INVOICE_ENABLED'
        ));

        if (isset($config['WASAKREDIT_LEASING_ENABLED'])) {
            $this->LEASING_ENABLED = $config['WASAKREDIT_LEASING_ENABLED'];
        }
        if (isset($config['WASAKREDIT_INVOICE_ENABLED'])) {
            $this->INVOICE_ENABLED = $config['WASAKREDIT_INVOICE_ENABLED'];
        }
        
        $this->_client = Wasa_Kredit_Checkout_SdkHelper::CreateClient();

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
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'wasakredit` (
            `id_cart` int unsigned NOT NULL,
            `id_wasakredit` varchar(36) COLLATE "utf8_general_ci" NOT NULL
            ) ENGINE='._MYSQL_ENGINE_.' COLLATE "utf8_general_ci"');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('displayProductPriceBlock')
            && $this->registerHook('displayHeader')
            && $this->registerHook('paymentReturn')
        ;
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
            && parent::uninstall()
        ;
    }

    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('WASAKREDIT_CLIENTID')) {
                $this->postErrors[] = $this->trans(
                    'The "CLIENTID" field is required.',
                    array(),
                    'Modules.wasakredit.Admin'
                );
            } elseif (!Tools::getValue('WASAKREDIT_CLIENTSECRET')) {
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
                'WASAKREDIT_CLIENTID',
                Tools::getValue('WASAKREDIT_CLIENTID')
            );
            Configuration::updateValue(
                'WASAKREDIT_CLIENTSECRET',
                Tools::getValue('WASAKREDIT_CLIENTSECRET')
            );
            Configuration::updateValue(
                'WASAKREDIT_TEST',
                Tools::getValue('WASAKREDIT_TEST')
            );
            Configuration::updateValue(
                'WASAKREDIT_TEST_CLIENTID',
                Tools::getValue('WASAKREDIT_TEST_CLIENTID')
            );
            Configuration::updateValue(
                'WASAKREDIT_TEST_CLIENTSECRET',
                Tools::getValue('WASAKREDIT_TEST_CLIENTSECRET')
            );
            Configuration::updateValue(
                'WASAKREDIT_LEASING_ENABLED',
                Tools::getValue('WASAKREDIT_LEASING_ENABLED')
            );
            Configuration::updateValue(
                'WASAKREDIT_INVOICE_ENABLED',
                Tools::getValue('WASAKREDIT_INVOICE_ENABLED')
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
        $this->context->controller->addCSS($this->_path.'views/css/wasakredit.css');
        $this->context->controller->addJS($this->_path.'views/js/wasakredit.js');
        $this->context->controller->addJS($this->_path.'views/js/wasaajax.js');

        $this->smarty->assign(array(
            'ajax' => $this->context->link->getBaseLink().'modules/wasakredit/ajax/'
        ));

        return $this->fetch('module:wasakredit/views/templates/hook/header_productlistajax.tpl');
    }
    
    public function hookDisplayProductPriceBlock($params)
        {
            if (!$this->active || !$this->LEASING_ENABLED || !isset($params['hook_origin']) || $params['hook_origin'] != 'product_sheet') {
                return;
            }
    
            $product_price = 1000;
            if (isset($params['product']['price_with_reduction'])) {
                $product_price = $params['product']['price_with_reduction'];
            } elseif ($params['product']['price_amount']) {
                $product_price = $params['product']['price_tax_exc'];
            }
    
            $response = $this->_client->get_monthly_cost_widget($product_price);
    
            if ($response -> statusCode == '200') {
                    $this->smarty->assign(array('widget' => $response->data));
                    return $this->fetch('module:wasakredit/views/templates/hook/displayProductPriceBlock.tpl');
            } else {
                echo '';
            }
        }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        
        $payment_methods = [];
        if ($this->LEASING_ENABLED){
          $leasing = new PaymentOption();
          $leasing->setModuleName($this->name)
              ->setCallToActionText(
                  $this->trans(
                      'Wasa Kredit Leasing',
                      array(),
                      'Modules.wasakredit.Admin'
                  )
              )
              ->setAction(
                  $this->context->link->getModuleLink(
                      $this->name,
                      'leasingpayment',
                      array(),
                      true
                  )
              )
              ->setAdditionalInformation(
                  $this->fetch('module:wasakredit/views/templates/front/payment_infos.tpl')
              );
          array_push($payment_methods, $leasing);
        }
           
        if($this->INVOICE_ENABLED) {
          $invoice = new PaymentOption();
          $invoice->setModuleName($this->name)
              ->setCallToActionText(
                  $this->trans(
                      'Wasa Kredit Faktura',
                      array(),
                      'Modules.wasakredit.Admin'
                  )
              )
              ->setAction(
                  $this->context->link->getModuleLink(
                      $this->name,
                      'invoicepayment',
                      array(),
                      true
                  )
              )
              ->setAdditionalInformation(
                  $this->fetch('module:wasakredit/views/templates/front/payment_infos.tpl')
              );
          array_push($payment_methods, $invoice);
        }
        
        return !empty($payment_methods) ? $payment_methods : NULL;
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
        return $this->fetch('module:wasakredit/views/templates/hook/payment_return.tpl');
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
                        'name' => 'WASAKREDIT_CLIENTID',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Client secret key', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_CLIENTSECRET',
                        'required' => true
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test Client ID', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST_CLIENTID',
                        'required' => false
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->trans('Test Client secret key', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST_CLIENTSECRET',
                        'required' => false
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Test mode', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_TEST',
                        'values' => array(
                            array(
                                'id' => 'WASAKREDIT_TEST_on',
                                'value' => 1
                            ),
                            array(
                                'id' => 'WASAKREDIT_TEST_off',
                                'value' => 0
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Aktivera leasing', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_LEASING_ENABLED',
                        'values' => array(
                            array(
                                'id' => 'WASAKREDIT_LEASING_ENABLED_on',
                                'value' => 1
                            ),
                            array(
                                'id' => 'WASAKREDIT_LEASING_ENABLED_off',
                                'value' => 0
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans('Aktivera faktura', array(), 'Modules.wasakredit.Admin'),
                        'name' => 'WASAKREDIT_INVOICE_ENABLED',
                        'values' => array(
                            array(
                                'id' => 'WASAKREDIT_INVOICE_ENABLED_on',
                                'value' => 1
                            ),
                            array(
                                'id' => 'WASAKREDIT_INVOICE_ENABLED_off',
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
        );
    }
}
