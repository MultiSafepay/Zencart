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
 * @author      MultiSafepay <integration@multisafepay.com>
 * @copyright   Copyright (c) MultiSafepay, Inc. (https://www.multisafepay.com)
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
 * WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */

require_once('multisafepay.php');

class multisafepay_payafter extends MultiSafepay
{
    public $icon = "payafter.png";

    public function __construct()
    {
        global $order;

        $this->code = 'multisafepay_payafter';
        $this->gateway = 'PAYAFTER';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_PAYAFTER_TEXT_TITLE);
        $this->description = $this->getDescription();
        $this->enabled = MODULE_PAYMENT_MSP_PAYAFTER_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_PAYAFTER_SORT_ORDER;

        if (is_object($order)) {
            $this->update_status();
        }
    }

    /**
     *
     */
    public function update_status()
    {
        global $order, $db;
        $allowed_countries = ['NL'];

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_MSP_PAYAFTER_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . MODULE_PAYMENT_MSP_PAYAFTER_ZONE . "' AND zone_country_id = '" . $order->billing['country']['id'] . "' ORDER BY zone_id");
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
                return;
            }
        }

        if (!in_array($order->customer['country']['iso_code_2'], $allowed_countries)) {
            $this->enabled = false;
            return;
        }
        if (!in_array($order->delivery['country']['iso_code_2'], $allowed_countries)) {
            $this->enabled = false;
            return;
        }

        if ($_SESSION['currency'] != 'EUR') {
            $this->enabled = false;
            return;
        }

        if (isset($order->info['total'])) {
            $amount = (float) $order->info['total'];
            $min_amount = (float)MODULE_PAYMENT_MSP_PAYAFTER_MIN_AMOUNT;
            $max_amount = (float)MODULE_PAYMENT_MSP_PAYAFTER_MAX_AMOUNT;

            if ($amount <= $min_amount || $amount >= $max_amount) {
                $this->enabled = false;
            }
        }
    }


    /**
     * @return array
     */
    public function selection()
    {
        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        return array(
            'id' => $this->code,
            'module' => $this->title,
            'fields' => array(
                array('title' => MODULE_PAYMENT_MSP_PAYAFTER_TEXT_BIRTHDAY,
                    'field' => zen_draw_input_field('payafter_birthday', '', 'id="payafter_birthday"' . $onFocus),
                    'tag' => 'payafter_birthday'),

                array('title' => MODULE_PAYMENT_MSP_PAYAFTER_TEXT_PHONE,
                    'field' => zen_draw_input_field('payafter_phone', '', 'id="payafter_phone"' . $onFocus),
                    'tag' => 'payafter_phone'),

                array('title' => MODULE_PAYMENT_MSP_PAYAFTER_TEXT_BANK_ACCOUNT,
                    'field' => zen_draw_input_field('payafter_bank_account', '', 'id="payafter_bank_account"' . $onFocus),
                    'tag' => 'payafter_bank_account')
            )
        );
    }


    /**
     * @return string
     */
    public function process_button()
    {
        return (
            zen_draw_hidden_field('payafter_birthday', $_POST['payafter_birthday']) .
            zen_draw_hidden_field('payafter_phone', $_POST['payafter_phone']) .
            zen_draw_hidden_field('payafter_bank_account', $_POST['payafter_bank_account'])
        );
    }


    public function prepare_transaction()
    {
        $this->trans_type = 'direct';
        $this->gateway_info = array(
            "birthday" => $_POST['payafter_birthday'],
            "phone" => $_POST['payafter_phone'],
            "bank_account" => $_POST['payafter_bank_account'],
            "referrer" => $_SERVER['HTTP_REFERER'],
            "user_agent" => $_SERVER['HTTP_USER_AGENT'],
            "email" => $GLOBALS['order']->customer['email_address']
        );
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
            $status = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_STATUS'));
            $sort = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_SORT_ORDER'));
            $zone = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ZONE'));

            // Create new configuration fields with value from the old one
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay  Pay After Delivery Module', 'MODULE_PAYMENT_MSP_PAYAFTER_STATUS', '" . $status->fields['configuration_value'] . "', 'Do you want to accept Pay Afer Delivery payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'),', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_PAYAFTER_SORT_ORDER', '" . $sort->fields['configuration_value'] . "', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_PAYAFTER_ZONE', '" . $zone->fields['configuration_value'] . "', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

            // Remove old configuration if exists
            $sql = "DELETE FROM %s WHERE configuration_key IN ('%s')";
            $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $oldKeys));
        }
    }


    public function install()
    {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay Pay After Delivery Module', 'MODULE_PAYMENT_MSP_PAYAFTER_STATUS', 'True', 'Do you want to accept Pay After Delivery payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_PAYAFTER_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_PAYAFTER_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Minimum order amount', 'MODULE_PAYMENT_MSP_PAYAFTER_MIN_AMOUNT', '100.00', 'Minimum order amount to be available', '6', '23', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Maximum order amount', 'MODULE_PAYMENT_MSP_PAYAFTER_MAX_AMOUNT', '2000.00', 'Maximum order amount to be available', '6', '23', now())");
    }

    /**
     * @return string[]
     */
    public function keys()
    {
        return array(
            'MODULE_PAYMENT_MSP_PAYAFTER_STATUS',
            'MODULE_PAYMENT_MSP_PAYAFTER_SORT_ORDER',
            'MODULE_PAYMENT_MSP_PAYAFTER_ZONE',
            'MODULE_PAYMENT_MSP_PAYAFTER_MIN_AMOUNT',
            'MODULE_PAYMENT_MSP_PAYAFTER_MAX_AMOUNT'
        );
    }

    /**
     * @return string[]
     */
    public function oldKeys()
    {
        return array(
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_STATUS',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_API_SERVER',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_API_KEY',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_DIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_AUTO_REDIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_GA_ACCOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_DAYS_ACTIVE',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ZONE',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_SORT_ORDER',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_INITIALIZED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_COMPLETED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_UNCLEARED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_RESERVED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_VOID',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_DECLINED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_REVERSED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_PARTIAL_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_ORDER_STATUS_ID_EXPIRED',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_MIN_AMOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_PAYAFTER_MAX_AMOUNT'
        );
    }
}
