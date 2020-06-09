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
 * @author      TechSupport <integration@multisafepay.com>
 * @copyright   Copyright (c) 2020 MultiSafepay, Inc. (https://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

require("multisafepay.php");

class multisafepay_klarna extends multisafepay
{
    public $icon = "klarna.png";

    public function __construct()
    {
        global $order;

        $this->code = 'multisafepay_klarna';
        $this->gateway = 'KLARNA';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_KLARNA_TEXT_TITLE);
        $this->description = $this->getDescription();
        $this->enabled = MODULE_PAYMENT_MSP_KLARNA_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_KLARNA_SORT_ORDER;

        if (is_object($order)) {
            $this->update_status();
        }
    }

    public function update_status()
    {
        global $order, $db;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_MSP_KLARNA_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_MSP_KLARNA_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
            while (!$check_query->EOF) {
                if ($check_query->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check_query->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check_query->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    public function updateConfig()
    {
        global $db;

        $oldKeys = implode("', '", $this->oldKeys());
        $keys = implode("', '", $this->keys());

        $sql = "SELECT configuration_value FROM %s WHERE configuration_key IN ('%s')";
        $count = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $oldKeys));

        if ($count->RecordCount() > 0) {
            // Remove new configuration if exists
            $sql = "DELETE FROM %s WHERE configuration_key IN ('%s')";
            $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $keys));

            // Get relevant values from the old configuration
            $sql = "SELECT configuration_value FROM %s WHERE configuration_key = '%s'";
            $status = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_STATUS'));
            $sort = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_SORT_ORDER'));
            $zone = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ZONE'));

            // Create new configuration fields with value from the old one
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay Klarna Module', 'MODULE_PAYMENT_MSP_KLARNA_STATUS', '" . $status->fields['configuration_value'] . "', 'Do you want to accept Klarna payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'),', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_KLARNA_SORT_ORDER', '" . $sort->fields['configuration_value'] . "', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_KLARNA_ZONE', '" . $zone->fields['configuration_value'] . "', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

            // Remove old configuration if exists
            $sql = "DELETE FROM %s WHERE configuration_key IN ('%s')";
            $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $oldKeys));
        }
    }


    public function install()
    {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay Klarna Module', 'MODULE_PAYMENT_MSP_KLARNA_STATUS', 'True', 'Do you want to accept Klarna payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_KLARNA_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_KLARNA_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    }

    /**
     * @return string[]
     */
    public function keys()
    {
        return [
            'MODULE_PAYMENT_MSP_KLARNA_STATUS',
            'MODULE_PAYMENT_MSP_KLARNA_SORT_ORDER',
            'MODULE_PAYMENT_MSP_KLARNA_ZONE',
        ];
    }

    /**
     * @return string[]
     */
    public function oldKeys()
    {
        return [
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_STATUS',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_API_SERVER',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_API_KEY',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_DIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_AUTO_REDIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_GA_ACCOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_DAYS_ACTIVE',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ZONE',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_SORT_ORDER',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_INITIALIZED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_COMPLETED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_UNCLEARED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_RESERVED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_VOID',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_DECLINED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_REVERSED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_PARTIAL_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_ORDER_STATUS_ID_EXPIRED',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_MIN_AMOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_KLARNA_MAX_AMOUNT'
        ];
    }
}
