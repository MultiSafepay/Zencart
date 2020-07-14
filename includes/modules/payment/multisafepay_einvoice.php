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

class multisafepay_einvoice extends MultiSafepay
{
    public $icon = "einvoice.png";

    public function __construct()
    {
        global $order;

        $this->code = 'multisafepay_einvoice';
        $this->gateway = 'EINVOICE';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_EINVOICE_TEXT_TITLE);
        $this->description = $this->getDescription();
        $this->enabled = MODULE_PAYMENT_MSP_EINVOICE_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_EINVOICE_SORT_ORDER;
        $this->paymentFilters = [
            'zone' => MODULE_PAYMENT_MSP_EINVOICE_ZONE,
            'minMaxAmount' => ['minAmount' => MODULE_PAYMENT_MSP_EINVOICE_MIN_AMOUNT,
                               'maxAmount' => MODULE_PAYMENT_MSP_EINVOICE_MAX_AMOUNT],
            'customerInCountry' => ['NL'],
            'deliveryInCountry' => ['NL'],
            'currencies' => ['EUR']
        ];
        if (is_object($order)) {
            $this->update_status();
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
                array('title' => MODULE_PAYMENT_MSP_EINVOICE_TEXT_BIRTHDAY,
                    'field' => zen_draw_input_field('einvoice_birthday', '', 'id="einvoice_birthday"' . $onFocus),
                    'tag' => 'einvoice_birthday'),

                array('title' => MODULE_PAYMENT_MSP_EINVOICE_TEXT_PHONE,
                    'field' => zen_draw_input_field('einvoice_phone', '', 'id="einvoice_phone"' . $onFocus),
                    'tag' => 'einvoice_phone'),

                array('title' => MODULE_PAYMENT_MSP_EINVOICE_TEXT_BANK_ACCOUNT,
                    'field' => zen_draw_input_field('einvoice_bank_account', '', 'id="einvoice_bank_account"' . $onFocus),
                    'tag' => 'einvoice_bank_account')
            )
        );
    }


    /**
     * @return string
     */
    public function process_button()
    {
        return (
            zen_draw_hidden_field('einvoice_birthday', $_POST['einvoice_birthday']) .
            zen_draw_hidden_field('einvoice_phone', $_POST['einvoice_phone']) .
            zen_draw_hidden_field('einvoice_bank_account', $_POST['einvoice_bank_account'])
        );
    }

    public function prepare_transaction()
    {
        if ($_POST['einvoice_birthday'] && $_POST['einvoice_phone'] && $_POST['einvoice_bank_account']) {
            $this->trans_type = 'direct';
        } else {
            $this->trans_type = 'redirect';
        }

        $this->gateway_info = array(
            "birthday" => $_POST['einvoice_birthday'],
            "phone" => $_POST['einvoice_phone'],
            "bank_account" => $_POST['einvoice_bank_account'],
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
            $status = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS'));
            $sort = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_SORT_ORDER'));
            $zone = $db->Execute(sprintf($sql, TABLE_CONFIGURATION, 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ZONE'));

            // Create new configuration fields with value from the old one
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay E-Invoicing Module', 'MODULE_PAYMENT_MSP_EINVOICE_STATUS', '" . $status->fields['configuration_value'] . "', 'Do you want to accept E-Invoicing payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'),', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order,  date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_EINVOICE_SORT_ORDER', '" . $sort->fields['configuration_value'] . "', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_EINVOICE_ZONE', '" . $zone->fields['configuration_value'] . "', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

            // Remove old configuration if exists
            $sql = "DELETE FROM %s WHERE configuration_key IN ('%s')";
            $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $oldKeys));
        }
    }


    public function install()
    {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay E-Invoicing Module', 'MODULE_PAYMENT_MSP_EINVOICE_STATUS', 'True', 'Do you want to accept E-Invoicing payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_EINVOICE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_EINVOICE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Minimum order amount', 'MODULE_PAYMENT_MSP_EINVOICE_MIN_AMOUNT', '100.00', 'Minimum order amount to be available', '6', '23', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Maximum order amount', 'MODULE_PAYMENT_MSP_EINVOICE_MAX_AMOUNT', '2000.00', 'Maximum order amount to be available', '6', '23', now())");
    }

    /**
     * @return string[]
     */
    public function keys()
    {
        return array(
            'MODULE_PAYMENT_MSP_EINVOICE_STATUS',
            'MODULE_PAYMENT_MSP_EINVOICE_SORT_ORDER',
            'MODULE_PAYMENT_MSP_EINVOICE_ZONE',
            'MODULE_PAYMENT_MSP_EINVOICE_MIN_AMOUNT',
            'MODULE_PAYMENT_MSP_EINVOICE_MAX_AMOUNT'
        );
    }

    /**
     * @return string[]
     */
    public function oldKeys()
    {
        return array(
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_KEY',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_AUTO_REDIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_GA_ACCOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DAYS_ACTIVE',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ZONE',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_SORT_ORDER',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_COMPLETED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_UNCLEARED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_DECLINED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REVERSED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_PARTIAL_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_EXPIRED',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MIN_AMOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MAX_AMOUNT'
        );
    }
}
