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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Dataprivacy extends Module
{
    protected $templateFile;

    public function __construct()
    {
        $this->name = 'ps_dataprivacy';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Customer data privacy block', array(), 'Modules.Dataprivacy.Admin');
        $this->description = $this->trans(
            'Adds a block displaying a message about a customer\'s privacy data.',
            array(),
            'Modules.Dataprivacy.Admin'
        );
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        $this->_html = '';
    }

    public function install()
    {
        $return = (parent::install()
                    && $this->registerHook('additionalCustomerFormFields')
                    && $this->registerHook('actionSubmitAccountBefore'));

        $this->installFixtures();

        return $return;
    }

    public function uninstall()
    {
        return ($this->unregisterHook('additionalCustomerFormFields')
                && $this->unregisterHook('actionBeforeSubmitAccount')
                && parent::uninstall());
    }

    public function getContent()
    {
        if (Tools::isSubmit('submitCustPrivMess')) {
            $message_trads = array('auth' => array());
            foreach ($_POST as $key => $value) {
                if (preg_match('/CUSTPRIV_MSG_AUTH_/i', $key)) {
                    $id_lang = preg_split('/CUSTPRIV_MSG_AUTH_/i', $key);
                    $message_trads['auth'][(int)$id_lang[1]] = $value;
                }
            }

            Configuration::updateValue('CUSTPRIV_MSG_AUTH', $message_trads['auth'], true);

            $this->_clearCache('*');
            $this->_html .= $this->displayConfirmation(
                $this->trans(
                    'The settings have been updated.',
                    array(),
                    'Admin.Notifications.Success'
                )
            );
        }

        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    protected function _clearCache($template, $cache_id = null, $compile_id = null) {
        return parent::_clearCache($this->templateFile);
    }

    /**
     * Add an extra FormField to ask for data privacy consent.
     *
     * @param $params
     *
     * @return bool
     */
    public function hookAdditionalCustomerFormFields($params)
    {
        $label = $this->trans(
            'Customer data privacy[1][2]%message%[/2]',
            array(
                '[1]' => '<br>',
                '[2]' => '<em>',
                '%message%' => Configuration::get('CUSTPRIV_MSG_AUTH', $this->context->language->id),
                '[/2]' => '</em>',
            ),
            'Modules.Dataprivacy.Shop'
        );

        return array(
            (new FormField())
                ->setName('customer_privacy')
                ->setType('checkbox')
                ->setLabel($label)
                ->setRequired(true));
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->trans('Settings', array(), 'Admin.Global'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'textarea',
                        'lang' => true,
                        'autoload_rte' => true,
                        'label' => $this->trans(
                            'Customer data privacy message for customer form:',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                        'name' => 'CUSTPRIV_MSG_AUTH',
                        'desc' => $this->trans('The customer data privacy' .
                            ' message will be displayed in the customer form',
                            array(),
                            'Modules.Dataprivacy.Admin'
                            ) . '<br>' . $this->trans(
                            'Tip: If the customer privacy message is too' .
                            ' long to be written directly in the form,' .
                            ' you can add a link to one of your pages.' .
                            ' This can easily be created via the "Pages"' .
                            ' page under the "Design" menu.',
                            array(),
                            'Modules.Dataprivacy.Admin'
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
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

        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $return['CUSTPRIV_MSG_AUTH'][(int)$lang['id_lang']] =
                Tools::getValue(
                    'CUSTPRIV_MSG_AUTH_'.(int)$lang['id_lang'],
                    Configuration::get('CUSTPRIV_MSG_AUTH', (int)$lang['id_lang'])
                );
        }

        return $return;
    }

    private function installFixtures()
    {
        $fixtures = array(
            "CUSTPRIV_MSG_AUTH" => array(
                'fr-fr' => "Conformément aux dispositions de la loi du n°78-17 du 6 janvier 1978, vous disposez d'un droit d'accès, de rectification et d'opposition sur les données nominatives vous concernant.",
            ),
        );
        $languages = Language::getLanguages();
        $conf_keys = array('CUSTPRIV_MSG_AUTH');
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
    }
}
