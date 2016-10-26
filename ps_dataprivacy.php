<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_DataPrivacy extends Module implements WidgetInterface
{
    public function __construct()
    {
        $this->name = 'ps_dataprivacy';
        $this->tab = 'front_office_features';

        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans(
            'Customer data privacy block',
            array(),
            'Modules.Dataprivacy.Admin'
        );
        $this->description = $this->trans(
            'Adds a block displaying a message about a customer\'s' .
            ' privacy data.',
            array(),
            'Modules.Dataprivacy.Admin'
        );
        $this->ps_versions_compliancy = array(
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        );

        $this->_html = '';
    }

    public function install()
    {
        $return = (parent::install()
                    && $this->registerHook('displayCustomerAccountForm')
                    && $this->registerHook('actionSubmitAccountBefore'));

        include 'fixtures.php'; // get Fixture array
        $languages = Language::getLanguages();
        $conf_keys = array('CUSTPRIV_MSG_AUTH', 'CUSTPRIV_MSG_IDENTITY');
        foreach ($conf_keys as $conf_key) {
            foreach ($languages as $lang) {
                if (isset($fixtures[$conf_key][$lang['language_code']])) {
                    Configuration::updateValue($conf_key, array(
                        $lang['id_lang'] =>
                            $fixtures[$conf_key][$lang['language_code']],
                    ));
                } else {
                    Configuration::updateValue($conf_key, array(
                        $lang['id_lang'] =>
                            'The personal data you provide is used to answer' .
                            ' queries, process orders or allow access to' .
                            ' specific information. You have the right to' .
                            ' modify and delete all the personal information' .
                            ' found in the "My Account" page.'
                    ));
                }
            }
        }

        return $return;
    }

    public function uninstall()
    {
        return ($this->unregisterHook('displayCustomerAccountForm')
                && $this->unregisterHook('actionBeforeSubmitAccount')
                && parent::uninstall());
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitCustPrivMess')) {
            $message_trads = array('auth' => array(), 'identity' => array());
            foreach ($_POST as $key => $value) {
                if (preg_match('/CUSTPRIV_MSG_AUTH_/i', $key)) {
                    $id_lang = preg_split('/CUSTPRIV_MSG_AUTH_/i', $key);
                    $message_trads['auth'][(int)$id_lang[1]] = $value;
                } elseif (preg_match('/CUSTPRIV_MSG_IDENTITY_/i', $key)) {
                    $id_lang = preg_split('/CUSTPRIV_MSG_IDENTITY_/i', $key);
                    $message_trads['identity'][(int)$id_lang[1]] = $value;
                }
            }
            Configuration::updateValue(
                'CUSTPRIV_MSG_AUTH',
                $message_trads['auth'],
                true
            );
            Configuration::updateValue(
                'CUSTPRIV_MSG_IDENTITY',
                $message_trads['identity'],
                true
            );

            Configuration::updateValue(
                'CUSTPRIV_AUTH_PAGE',
                (int)Tools::getValue('CUSTPRIV_AUTH_PAGE')
            );
            Configuration::updateValue(
                'CUSTPRIV_IDENTITY_PAGE',
                (int)Tools::getValue('CUSTPRIV_IDENTITY_PAGE')
            );

            $this->_clearCache('*');
            $this->_html .= $this->displayConfirmation(
                $this->trans(
                    'Settings updated.',
                    array(),
                    'Admin.Global'
                )
            );
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    public function checkConfig($switch_key, $msg_key)
    {
        if (!$this->active) {
            return false;
        }

        if (!Configuration::get($switch_key)) {
            return false;
        }

        $message = Configuration::get($msg_key, $this->context->language->id);
        if (empty($message)) {
            return false;
        }

        return true;
    }

    protected function _clearCache(
        $template,
        $cache_id = null,
        $compile_id = null
    ) {
        return parent::_clearCache(
                    'module:ps_dataprivacy/ps_dataprivacy.tpl'
                 );
    }

    public function hookActionSubmitAccountBefore($params)
    {
        if (!$this->checkConfig('CUSTPRIV_AUTH_PAGE', 'CUSTPRIV_MSG_AUTH')) {
            return;
        }

        if (!Tools::getValue('customer_privacy')) {
            $this->context->controller->errors[] = $this->trans(
                'If you agree to the terms in the Customer Data Privacy' .
                ' message, please click the check box below.',
                array(),
                'Modules.Dataprivacy.Admin'
            );
            return false;
        }
        return true;
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        if ($this->context->customer->id) {
            $msgKey = 'CUSTPRIV_MSG_IDENTITY';
        } else {
            $msgKey = 'CUSTPRIV_MSG_AUTH';
        }

        return array(
            'message' => Configuration::get(
                $msgKey,
                $this->context->language->id
            ),
            'is_logged_in' => null !== $this->context->customer->id,
        );
    }

    public function renderWidget($hookName, array $configuration)
    {
        if ($this->context->customer->id) {
            $switchKey = 'CUSTPRIV_IDENTITY_PAGE';
            $msgKey = 'CUSTPRIV_MSG_IDENTITY';
        } else {
            $switchKey = 'CUSTPRIV_AUTH_PAGE';
            $msgKey = 'CUSTPRIV_MSG_AUTH';
        }

        $cacheKey = 'ps_dataprivacy|' . $msgKey;
        $isCached = $this->isCached(
            'module:ps_dataprivacy/ps_dataprivacy.tpl',
            $cacheKey
        );

        if (!$isCached) {
            if (!$this->checkConfig($switchKey, $msgKey)) {
                return;
            }

            $this->smarty->assign(
                $this->getWidgetVariables(
                    $hookName,
                    $configuration
                )
            );
        }

        return $this->fetch(
            'module:ps_dataprivacy/ps_dataprivacy.tpl',
            $cacheKey
        );
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans(
                        'Settings',
                        array(),
                        'Admin.Global'
                    ),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Display on account creation form',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                        'name' => 'CUSTPRIV_AUTH_PAGE',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans(
                                    'Enabled',
                                    array(),
                                    'Admin.Global'
                                )
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans(
                                    'Disabled',
                                    array(),
                                    'Admin.Global'
                                )
                            ),
                        ),
                    ),
                    array(
                        'type' => 'textarea',
                        'lang' => true,
                        'autoload_rte' => true,
                        'label' => $this->trans(
                            'Customer data privacy message for account' .
                            ' creation form:',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                        'name' => 'CUSTPRIV_MSG_AUTH',
                        'desc' => $this->trans('The customer data privacy' .
                            ' message will be displayed in the account' .
                            ' creation form.',
                            array(),
                            'Modules.Dataprivacy.Admin'
                            ) . '<br>' . $this->trans(
                            'Tip: If the customer privacy message is too' .
                            ' long to be written directly in the form,' .
                            ' you can add a link to one of your pages.' .
                            ' This can easily be created via the "CMS"' .
                            ' page under the "Preferences" menu.',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->trans(
                            'Display in customer area',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                        'name' => 'CUSTPRIV_IDENTITY_PAGE',
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans(
                                    'Enabled',
                                    array(),
                                    'Admin.Global'
                                ),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans(
                                    'Disabled',
                                    array(),
                                    'Admin.Global'
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'textarea',
                        'lang' => true,
                        'autoload_rte' => true,
                        'label' => $this->trans(
                            'Customer data privacy message for customer area:',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                        'name' => 'CUSTPRIV_MSG_IDENTITY',
                        'desc' => $this->trans(
                            'The customer data privacy message will be' .
                            ' displayed in the "Personal information" page,' .
                            ' in the customer area.',
                            array(),
                            'Modules.Dataprivacy.Admin'
                            ) . '<br>' . $this->trans(
                            'Tip: If the customer privacy message is too' .
                            ' long to be written directly on the page,' .
                            ' you can add a link to one of your other' .
                            ' pages. This can easily be created via the' .
                            ' "CMS" page under the "Preferences" menu.',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans(
                        'Save',
                        array(),
                        'Admin.Actions'
                    ),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table =  $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang =
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ?
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') :
            0;
        $this->fields_form = array();

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCustPrivMess';
        $helper->currentIndex = $this->context->link->getAdminLink(
                'AdminModules',
                false
            ) .
            '&configure=' . $this->name .
            '&tab_module=' . $this->tab .
            '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        $return = array();

        $return['CUSTPRIV_AUTH_PAGE'] = (int)Configuration::get(
            'CUSTPRIV_AUTH_PAGE'
        );
        $return['CUSTPRIV_IDENTITY_PAGE'] = (int)Configuration::get(
            'CUSTPRIV_IDENTITY_PAGE'
        );

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $return['CUSTPRIV_MSG_AUTH'][(int)$lang['id_lang']] =
                Tools::getValue(
                    'CUSTPRIV_MSG_AUTH_' . (int)$lang['id_lang'],
                    Configuration::get(
                        'CUSTPRIV_MSG_AUTH',
                        (int)$lang['id_lang']
                    )
                );
            $return['CUSTPRIV_MSG_IDENTITY'][(int)$lang['id_lang']] =
                Tools::getValue(
                    'CUSTPRIV_MSG_IDENTITY_' . (int)$lang['id_lang'],
                    Configuration::get(
                        'CUSTPRIV_MSG_IDENTITY',
                        (int)$lang['id_lang']
                    )
                );
        }

        return $return;
    }
}
