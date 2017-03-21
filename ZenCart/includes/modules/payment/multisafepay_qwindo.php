<?php

require( "multisafepay.php" );

class multisafepay_qwindo extends multisafepay {

    function __construct()
    {
        $this->code = 'multisafepay_qwindo';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_QWINDO_TITLE);
        $this->description = "<img src='images/icon_info.gif' border='0'>&nbsp;<b>MultiSafepay Qwindo</b><br /><br />This module isn't an actual payment method, but rather a module allowing you to configure additional webshop data for Qwindo.<br />";
        $this->enabled = false; //Hide from checkout
        $this->sort_order = 999;
    }

    /**
     * 
     * @return type
     */
    function process_button()
    {
        
    }

    /**
     * Checks whether the payment has been “installed” through the admin panel
     * 
     * @global type $db
     * @return type
     */
    function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MSP_QWINDO_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * Installs the configuration keys into the database
     * 
     * @global type $db
     */
    function install()
    {
        global $db;

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Qwindo/FCO feed', 'MODULE_PAYMENT_MSP_QWINDO_STATUS', 'False', '', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Including tax', 'MODULE_PAYMENT_MSP_QWINDO_INCL_TAX', 'True', 'Do the product prices include tax?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Shipping required', 'MODULE_PAYMENT_MSP_QWINDO_REQ_SHIP', 'True', 'Are orders placed in your webshop required to be shipped?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Webshop Base URL', 'MODULE_PAYMENT_MSP_QWINDO_URL_BASE', '', 'Your webshop\'s Base URL', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Webshop Order Push URL', 'MODULE_PAYMENT_MSP_QWINDO_URL_OP', '', 'To which URL should orders be pushed?', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Chambre of Commerce number', 'MODULE_PAYMENT_MSP_QWINDO_COC', '', 'Your store\'s Chambre of Commerce number', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('E-mailaddress', 'MODULE_PAYMENT_MSP_QWINDO_STORE_EMAIL', '', 'The webshop\'s e-mailaddress', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Phonenumber', 'MODULE_PAYMENT_MSP_QWINDO_STORE_PHONE', '', 'The webshop\'s phonenumber', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Address', 'MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS', '', 'Street and housenumber', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Zipcode', 'MODULE_PAYMENT_MSP_QWINDO_STORE_ZIP', '', '', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('City', 'MODULE_PAYMENT_MSP_QWINDO_STORE_CITY', '', '', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Country', 'MODULE_PAYMENT_MSP_QWINDO_STORE_COUNTRY', '', 'The country the store is located in', '6', '22', 'zen_get_country_name', 'zen_cfg_pull_down_country_list_none(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('VAT number', 'MODULE_PAYMENT_MSP_QWINDO_VAT', '', '', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('TOC URL', 'MODULE_PAYMENT_MSP_QWINDO_TOC', '', 'A link to your webshop\'s Terms and Conditions', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('FAQ URL', 'MODULE_PAYMENT_MSP_QWINDO_FAQ', '', 'A link to your webshop\'s Frequently Asked Questions', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Store opening time', 'MODULE_PAYMENT_MSP_QWINDO_OPEN', '', 'Your store\'s opening time', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Store closing time', 'MODULE_PAYMENT_MSP_QWINDO_CLOSED', '', 'Your store\'s closing time', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Mondays', 'MODULE_PAYMENT_MSP_QWINDO_OPENMON', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Tuesdays', 'MODULE_PAYMENT_MSP_QWINDO_OPENTUE', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Wednesdays', 'MODULE_PAYMENT_MSP_QWINDO_OPENWED', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Thursdays', 'MODULE_PAYMENT_MSP_QWINDO_OPENTHU', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Fridays', 'MODULE_PAYMENT_MSP_QWINDO_OPENFRI', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Saturdays', 'MODULE_PAYMENT_MSP_QWINDO_OPENSAT', 'Open', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Store on Sundays', 'MODULE_PAYMENT_MSP_QWINDO_OPENSUN', 'Closed', '', '6', '1', 'zen_cfg_select_option(array(\'Open\', \'Closed\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Facebook URL', 'MODULE_PAYMENT_MSP_QWINDO_URL_FB', '', 'A link to your webshop\'s Facebook page', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Twitter URL', 'MODULE_PAYMENT_MSP_QWINDO_URL_TW', '', 'A link to your webshop\'s Twitter page', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('LinkedIn URL', 'MODULE_PAYMENT_MSP_QWINDO_URL_LI', '', 'A link to your webshop\'s LinkedIn page', '6', '22', now())");
    }

    /**
     * Installed keys
     * 
     * @return type
     */
    function keys()
    {
        return array
            (
            'MODULE_PAYMENT_MSP_QWINDO_STATUS',
            'MODULE_PAYMENT_MSP_QWINDO_INCL_TAX',
            'MODULE_PAYMENT_MSP_QWINDO_REQ_SHIP',
            'MODULE_PAYMENT_MSP_QWINDO_URL_BASE',
            'MODULE_PAYMENT_MSP_QWINDO_URL_OP',
            'MODULE_PAYMENT_MSP_QWINDO_COC',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_EMAIL',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_PHONE',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_ZIP',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_CITY',
            'MODULE_PAYMENT_MSP_QWINDO_STORE_COUNTRY',
            'MODULE_PAYMENT_MSP_QWINDO_VAT',
            'MODULE_PAYMENT_MSP_QWINDO_TOC',
            'MODULE_PAYMENT_MSP_QWINDO_FAQ',
            'MODULE_PAYMENT_MSP_QWINDO_OPEN',
            'MODULE_PAYMENT_MSP_QWINDO_CLOSED',
            'MODULE_PAYMENT_MSP_QWINDO_OPENMON',
            'MODULE_PAYMENT_MSP_QWINDO_OPENTUE',
            'MODULE_PAYMENT_MSP_QWINDO_OPENWED',
            'MODULE_PAYMENT_MSP_QWINDO_OPENTHU',
            'MODULE_PAYMENT_MSP_QWINDO_OPENFRI',
            'MODULE_PAYMENT_MSP_QWINDO_OPENSAT',
            'MODULE_PAYMENT_MSP_QWINDO_OPENSUN',
            'MODULE_PAYMENT_MSP_QWINDO_URL_FB',
            'MODULE_PAYMENT_MSP_QWINDO_URL_TW',
            'MODULE_PAYMENT_MSP_QWINDO_URL_LI'
        );
    }

}

?>