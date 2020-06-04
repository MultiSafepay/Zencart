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

class multisafepay_ideal extends MultiSafepay
{
    public $icon = "ideal.png";

    public function __construct()
    {
        global $order;

        $this->code = 'multisafepay_ideal';
        $this->title = $this->getTitle(MODULE_PAYMENT_MSP_IDEAL_TEXT_TITLE);
        $this->description = '<strong>' . $this->title . "&nbsp;&nbsp;" . $this->plugin_ver . '</strong><br>The main MultiSafepay module must be installed (does not have to be active) to use this payment method.<br>';
        $this->enabled = MODULE_PAYMENT_MSP_IDEAL_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MSP_IDEAL_SORT_ORDER;

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

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_MSP_IDEAL_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . MODULE_PAYMENT_MSP_IDEAL_ZONE . "' AND zone_country_id = '" . $order->billing['country']['id'] . "' ORDER BY zone_id");
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

        if ($_SESSION['currency'] != 'EUR') {
            $this->enabled = false;
            return;
        }
    }


    /**
     * @return array
     */
    public function selection()
    {
        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

        try {
            $msp = new MultiSafepayAPI\Client();
            $api_url = $this->get_api_url();

            $msp->setApiUrl($api_url);
            $msp->setApiKey($this->get_api_key());
            $issuersObj = $msp->issuers->get();

        } catch (Exception $e) {
            $this->_error_redirect(htmlspecialchars($e->getMessage()));
            die;
        }

        $issuers[] = ['id' => '', 'text'=> MODULE_PAYMENT_MSP_IDEAL_CHOOSE_BANK];

        foreach ($issuersObj as $issuer) {
            $issuers[] = ['id' => $issuer->code, 'text' => $issuer->description];
        }

        return array(
            'id' => $this->code,
            'module' => $this->title,
            'fields' => array(
                array('title' => MODULE_PAYMENT_MSP_IDEAL_CHOOSE_BANK,
                    'field' => zen_draw_pull_down_menu('ideal_issuer', $issuers, '', 'id="ideal_issuer"' . $onFocus),
                    'tag' => 'ideal_issuer')
            )
        );
    }

    /**
     * @return string
     */
    public function process_button()
    {
        return (
            zen_draw_hidden_field('msp_paymentmethod', 'IDEAL') .
            zen_draw_hidden_field('ideal_issuer', $_POST['ideal_issuer'])
        );
    }

    /**
     * @return int
     */
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $checkQuery = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MSP_IDEAL_STATUS'");
            $this->_check = $checkQuery->RecordCount();
        }
        return $this->_check;
    }


    public function prepare_transaction()
    {
        $this->trans_type = 'direct';
        $this->gateway_info = array(
            "issuer_id" => $_POST['ideal_issuer'],
            "referrer" => $_SERVER['HTTP_REFERER'],
            "user_agent" => $_SERVER['HTTP_USER_AGENT'],
            "email" => $GLOBALS['order']->customer['email_address']
        );
    }

    public function updateConfig()
    {
        global $db;

        $oldKeys = implode("', '", $this->oldKeys());

        // Remove old configuration if exists
        $sql = "DELETE FROM %s WHERE configuration_key IN ('%s')";
        $db->Execute(sprintf($sql, TABLE_CONFIGURATION, $oldKeys));
    }


    public function install()
    {
        global $db;
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable MultiSafepay iDEAL', 'MODULE_PAYMENT_MSP_IDEAL_STATUS', 'True', 'Do you want to accept iDEAL payments?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MSP_IDEAL_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MSP_IDEAL_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '3', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
    }

    /**
     * @return string[]
     */
    public function keys()
    {
        return array(
            'MODULE_PAYMENT_MSP_IDEAL_STATUS',
            'MODULE_PAYMENT_MSP_IDEAL_SORT_ORDER',
            'MODULE_PAYMENT_MSP_IDEAL_ZONE'
        );
    }

    /**
     * @return string[]
     */
    public function oldKeys()
    {
        return array(
            'MODULE_PAYMENT_MSP_IDEAL_DIRECT',
        );
    }
}
