<?php

require( "multisafepay.php" );

class multisafepay_giropay extends multisafepay
{

    var $icon = "giropay.png";

    /*
     * Constructor
     */

    function __construct()
    {
        global $order;
        $this->code = 'multisafepay_giropay';
        $this->gateway = 'GIROPAY';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_GIROPAY_TEXT_TITLE);
        $this->description = $this->getDescription();
        $this->enabled = MODULE_PAYMENT_MSP_GIROPAY_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_GIROPAY_SORT_ORDER;
        $this->paymentFilters = [
            'zone' => MODULE_PAYMENT_MSP_GIROPAY_ZONE
        ];

        if (is_object($order)) {
            $this->update_status();
        }
    }

    /*
     * Installs the configuration keys into the database
     */

    function install()
    {
        global $db;

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable MultiSafepay Giropay Module', 'MODULE_PAYMENT_MSP_GIROPAY_STATUS', 'True', 'Do you want to accept GiroPay payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_MSP_GIROPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_MSP_GIROPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    }

    function keys()
    {
        return array
            (
            'MODULE_PAYMENT_MSP_GIROPAY_STATUS',
            'MODULE_PAYMENT_MSP_GIROPAY_SORT_ORDER',
            'MODULE_PAYMENT_MSP_GIROPAY_ZONE'
        );
    }

}

?>
