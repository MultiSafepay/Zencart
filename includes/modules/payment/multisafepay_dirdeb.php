<?php

/**
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade the MultiSafepay plugin
 * to newer versions in the future. If you wish to customize the plugin for your
 * needs please document your changes and make backups before you update.
 *
 * @category    MultiSafepay
 * @package     Connect
 * @author      TechSupport <techsupport@multisafepay.com>
 * @copyright   Copyright (c) 2017 MultiSafepay, Inc. (http://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

require( "multisafepay.php" );

class multisafepay_dirdeb extends multisafepay
{

    var $icon = "dirdeb.png";

    /*
     * Constructor
     */

    function __construct()
    {
        global $order;

        $this->code = 'multisafepay_dirdeb';
        $this->gateway = 'DIRDEB';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_DIRDEB_TEXT_TITLE);
        $this->description = $this->getDescription();
        $this->enabled = MODULE_PAYMENT_MSP_DIRDEB_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_DIRDEB_SORT_ORDER;
        $this->paymentFilters = [
            'zone' => MODULE_PAYMENT_MSP_DIRDEB_ZONE
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

        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable MultiSafepay Direct Debit Module', 'MODULE_PAYMENT_MSP_DIRDEB_STATUS', 'True', 'Do you want to accept Direct Debit payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_MSP_DIRDEB_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_MSP_DIRDEB_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    }

    function keys()
    {
        return array
            (
            'MODULE_PAYMENT_MSP_DIRDEB_STATUS',
            'MODULE_PAYMENT_MSP_DIRDEB_SORT_ORDER',
            'MODULE_PAYMENT_MSP_DIRDEB_ZONE'
        );
    }

}

?>
