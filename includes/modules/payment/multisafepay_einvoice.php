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

require_once(DIR_FS_CATALOG . "mspcheckout/API/Autoloader.php");
require_once('multisafepay.php');

class multisafepay_einvoice extends MultiSafepay
{

    var $code;
    var $title;
    var $description;
    var $enabled;
    var $sort_order;
    var $plugin_ver = "3.0.0";
    var $icon = "einvoice.png";
    var $api_url;
    var $order_id;
    var $public_title;
    var $status;
    var $taxes = array();
    var $_customer_id = 0;

    /*
     * Constructor
     */

    function __construct($order_id = -1)
    {
        global $order;

        $this->code = 'multisafepay_einvoice';
        $this->title = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_TITLE);
        $this->description = '<strong>' . $this->title . "&nbsp;&nbsp;v" . $this->plugin_ver .  '</strong><br>The main MultiSafepay module must be installed (does not have to be active) to use this payment method.<br>';
        $this->enabled = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_SORT_ORDER;
        $this->order_status = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED;

        if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER == 'Live' || MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER == 'Live account') {
            $this->api_url = 'https://api.multisafepay.com/v1/json/';
        } else {
            $this->api_url = 'https://testapi.multisafepay.com/v1/json/';
        }

        if (is_object($order)) {
            $this->update_status();
        }

        $this->order_id = $order_id;
        $this->public_title = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_TITLE);
        $this->status = null;

        if ($_SESSION['currency'] != 'EUR') {
            $this->enabled = false;
        }

        if (isset($GLOBALS['order']->info['total'])) {
            $amount = $GLOBALS['order']->info['total'] * 100;
            if ($amount <= MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MIN_AMOUNT || $amount >= MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MAX_AMOUNT) {
                $this->enabled = false;
            }
        }
    }

    /**
     * 
     * @param type $str
     * @return type
     */
    function getLangStr($str)
    {
        $holder = $str;
        return $holder;
    }

    /**
     * Generate the payment method icon
     * 
     * @param type $icon
     * @return type
     */
    function generateIcon($icon)
    {
        return zen_image($icon, '', 50, 23, 'style="display:inline-block;vertical-align: middle;height:100%;margin-right:10px;"');
    }

    /**
     * 
     * @global type $PHP_SELF
     * @return type
     */
    function getScriptName()
    {
        global $PHP_SELF;
        return basename($PHP_SELF);
    }

    /**
     * 
     * @return string
     */
    function checkView()
    {
        $view = "admin";

        if (!IS_ADMIN_FLAG) {
            if ($this->getScriptName() == FILENAME_CHECKOUT_PAYMENT) {
                $view = "checkout";
            } else {
                $view = "frontend";
            }
        }
        return $view;
    }

    /**
     * Check whether this payment module is available
     * 
     * @global type $order
     * @global type $db
     */
    function update_status()
    {
        global $order, $db;

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
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

    /**
     * Client side javascript that will verify any input fields you use in the payment method selection page
     * 
     * @return boolean
     */
    function javascript_validation()
    {
        return false;
    }

    /**
     * Any checks of any conditions after payment method has been selected
     * 
     * @return boolean
     */
    function pre_confirmation_check()
    {
        return false;
    }

    // ---- confirm order ----

    /**
     * Any checks or processing on the order information before proceeding to payment confirmation
     * 
     * @return boolean
     */
    function confirmation()
    {
        return false;
    }

    /**
     * Outputs the html form hidden elements sent as POST data to the payment gateway
     * 
     * @return boolean
     */
    function process_button()
    {
        if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DIRECT === 'True') {
            return $this->create_einvoiceinput();
        } else {
            return false;
        }
    }

    /**
     * Set the bankaccount and birthday input for direct E-Invoice
     * 
     * @return type
     */
    function create_einvoiceinput()
    {
        $output = '<div class="einvoicebox" style="padding:20px;border:1px solid #d50172; margin-top:20px;text-align:center">';
        $output .= '<img src="images/multisafepay/en/einvoice-big.png" border="0" width="175" /><br /><br />';
        $output .= '<label>Bankaccount: </label><input type="text" name="einv_bankaccount"style="width:200px; padding: 0px; margin-left: 7px;"><br/>';
        $output .= '<label>Birthday: </label><input type="text" name="einv_birthday" placeholder="DD-MM-YYYY" style="width:200px; padding: 0px; margin-left: 35px;">';
        $output .= '<div style="clear:both;"></div></div><br/>';

        return ($output);
    }

    function before_process()
    {
        $GLOBALS['order']->info['payment_method'] = trim(strip_tags($GLOBALS['order']->info['payment_method']));
    }

    function after_process()
    {
        zen_redirect($this->_start_einvoice());
    }

    /**
     * Advanced error handling
     * 
     * @return boolean
     */
    function output_error()
    {
        return false;
    }

    /**
     * 
     * @return type
     */
    function _start_einvoice()
    {
        global $insert_id;
        $this->order_id = $insert_id;

        $items_list = "<ul>\n";
        foreach ($GLOBALS['order']->products as $product) {
            $items_list .= "<li>" . $product['qty'] . 'x ' . $product['name'] . "</li>\n";
        }
        $items_list .= "</ul>\n";

        $amount = round($GLOBALS['order']->info['total'], 2);
        $amount = $amount * 100;

        $sid = zen_session_name() . '=' . zen_session_id();

        if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_AUTO_REDIRECT === 'True') {
            $redirect_url = $this->_href_link('ext/modules/payment/multisafepay/success.php?' . $sid, '', 'NONSSL', false, false);
        } else {
            $redirect_url = null;
        }

        if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DIRECT === 'True') {
            $trans_type = 'direct';
        } else {
            $trans_type = 'redirect';
        }

        if ($_POST['einv_bankaccount'] == "" || $_POST['einv_birthday'] == "") {
            $trans_type = 'redirect';
        }

        if ($_SESSION['sendto'] == '') {
            $extra_var3 = $_SESSION['billto'];
        } else {
            $extra_var3 = $_SESSION['sendto'];
        }

        try {
            $msp = new MultiSafepayAPI\Client();

            if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER == 'Live account') {
                $api_url = 'https://api.multisafepay.com/v1/json/';
            } else {
                $api_url = 'https://testapi.multisafepay.com/v1/json/';
            }

            $msp->setApiUrl($api_url);
            $msp->setApiKey(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_KEY);

            list($cust_street, $cust_housenumber) = $this->parseAddress($GLOBALS['order']->customer['street_address']);
            list($del_street, $del_housenumber) = $this->parseAddress($GLOBALS['order']->customer['street_address']);

            $msp->orders->post(array(
                "type" => $trans_type,
                "order_id" => $this->order_id,
                "currency" => $GLOBALS['order']->info['currency'],
                "amount" => $amount,
                "description" => 'Order #' . $this->order_id . ' at ' . STORE_NAME,
                "var1" => $_SESSION['customer_id'],
                "var2" => $_SESSION['billto'],
                "var3" => $extra_var3,
                "items" => $items_list,
                "manual" => "false",
                "gateway" => "EINVOICE",
                "days_active" => MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DAYS_ACTIVE,
                "payment_options" => array(
                    "notification_url" => $this->_href_link('ext/modules/payment/multisafepay/notify_checkout.php?type=initial&' . $sid, '', 'NONSSL', false, false),
                    "redirect_url" => $redirect_url,
                    "cancel_url" => zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'),
                    "close_window" => "true"
                ),
                "customer" => array(
                    "locale" => strtolower($GLOBALS['order']->delivery['country']['iso_code_2']) . '_' . $GLOBALS['order']->delivery['country']['iso_code_2'],
                    "ip_address" => $_SERVER['REMOTE_ADDR'],
                    "forwarded_ip" => $_SERVER['HTTP_X_FORWARDED_FOR'],
                    "first_name" => $GLOBALS['order']->customer['firstname'],
                    "last_name" => $GLOBALS['order']->customer['lastname'],
                    "address1" => $cust_street,
                    "address2" => null,
                    "house_number" => $cust_housenumber,
                    "zip_code" => $GLOBALS['order']->customer['postcode'],
                    "city" => $GLOBALS['order']->customer['city'],
                    "state" => $GLOBALS['order']->customer['state'],
                    "country" => $GLOBALS['order']->customer['country']['iso_code_2'],
                    "phone" => $GLOBALS['order']->customer['telephone'],
                    "email" => $GLOBALS['order']->customer['email_address'],
                    "referrer" => $_SERVER['HTTP_REFERER'],
                    "user_agent" => $_SERVER['HTTP_USER_AGENT']
                ),
                "delivery" => array(
                    "first_name" => $GLOBALS['order']->delivery['firstname'],
                    "last_name" => $GLOBALS['order']->delivery['lastname'],
                    "address1" => $del_street,
                    "address2" => null,
                    "house_number" => $del_housenumber,
                    "zip_code" => $GLOBALS['order']->delivery['postcode'],
                    "city" => $GLOBALS['order']->delivery['city'],
                    "state" => null,
                    "country" => $GLOBALS['order']->delivery['country']['iso_code_2'],
                    "phone" => $GLOBALS['order']->customer['telephone'],
                    "email" => $GLOBALS['order']->customer['email_address']
                ),
                "gateway_info" => array(
                    "birthday" => $_POST['einv_birthday'],
                    "bank_account" => $_POST['einv_bankaccount'],
                    "phone" => $GLOBALS['order']->customer['telephone'],
                    "referrer" => $_SERVER['HTTP_REFERER'],
                    "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                    "email" => $GLOBALS['order']->customer['email_address']
                ),
                "shopping_cart" => $this->getShoppingCart(),
                "checkout_options" => $this->getCheckoutOptions(),
                "google_analytics" => array(
                    "account" => MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_GA_ACCOUNT,
                ),
                "plugin" => array(
                    "shop" => PROJECT_VERSION_NAME,
                    "shop_version" => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR,
                    "plugin_version" => $this->plugin_ver,
                    "partner" => 'MultiSafepay',
                    "shop_root_url" => $_SERVER['SERVER_NAME']
                )
            ));

            if ($trans_type == 'direct') {
                $payment_url = $msp->orders->getPaymentLink() . "&transactionid=" . $this->order_id;
                return $payment_url;
            } else {
                return $msp->orders->getPaymentLink();
            }
        } catch (Exception $e) {
            if ($this->getErrorcode($e->getMessage()) == "1024") {
                $this->_error_redirect(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_ERROR_1024);
                die();
            } else {
                $this->_error_redirect(htmlspecialchars($e->getMessage()));
                die();
            }
        }
    }

    /**
     * Fetches the items and related data, and builds the $checkoutoptions_array
     * 
     * @global type $order
     * @return array
     */
    function getCheckoutOptions()
    {
        global $order;

        $checkoutoptions_array = array();
        $checkoutoptions_array['use_shipping_notification'] = false;

        $checkoutoptions_array['tax_tables'] = array(
            "alternate" => array()
        );

        foreach ($order->products as $product) {
            if ($product['tax_description'] != 'Unknown tax rate' || $product['tax_description'] != 'Sales Tax') {
                if (!$this->in_array_recursive(key($product['tax_groups']), $checkoutoptions_array['tax_tables']['alternate'])) {
                    $checkoutoptions_array['tax_tables']['alternate'][] = array(
                        "standalone" => false,
                        "name" => $product['tax_description'],
                        "rules" => array(array
                                (
                                "rate" => current($product['tax_groups']) / 100
                            ))
                    );
                }
            } else {
                if (!$this->in_array_recursive(key($product['tax_groups']), $checkoutoptions_array['tax_tables']['alternate'])) {
                    $checkoutoptions_array['tax_tables']['alternate'][] = array(
                        "standalone" => false,
                        "name" => "BTW0",
                        "rules" => array
                            (
                            "rate" => 0.00
                        )
                    );
                }
            }
        }

        if ($order->info['shipping_cost'] != '0.00') {
            if ($this->in_array_recursive(current(array_keys($order->info['tax_groups'])), $checkoutoptions_array['tax_tables']['alternate'])) {
                $tax_percentage = $order->info['shipping_tax'] / ( $order->info['shipping_cost'] / 100);
                $tax_percentage = $tax_percentage / 100;

                $checkoutoptions_array['tax_tables']['alternate'][] = array(
                    "standalone" => false,
                    "name" => current(array_keys($order->info['tax_groups'])),
                    "rules" => array(array
                            (
                            "rate" => $tax_percentage
                        ))
                );
            }
        }

        return $checkoutoptions_array;
    }

    /**
     * Fetches the items and related data, and builds the $shoppingcart_array
     * 
     * @global type $order
     * @return type array
     */
    function getShoppingCart()
    {
        $shoppingcart_array = array();

        foreach ($GLOBALS['order']->products as $product) {
            $price = $product['price'];
            if (isset($product['final_price'])) {
                $price = $product['final_price'];
            }

            $attributeString = '';
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attribute) {
                    $attributeString .= $attribute['option'] . ' ' . $attribute['value'] . ', ';
                }
                $attributeString = substr($attributeString, 0, -2);
                $attributeString = ' (' . $attributeString . ')';
            }

            $shoppingcart_array['items'][] = array(
                "name" => $product['name'] . $attributeString,
                "description" => $product['model'],
                "unit_price" => $price,
                "quantity" => $product['qty'],
                "merchant_item_id" => $product['id'],
                "tax_table_selector" => $product['tax_description'],
                "weight" => array(
                    "unit" => "KG",
                    "value" => $product['weight']
                )
            );
        }

        if (isset($GLOBALS['order']->info['shipping_method'])) {
            $shoppingcart_array['items'][] = array(
                "name" => $GLOBALS['order']->info['shipping_method'],
                "description" => $GLOBALS['order']->info['shipping_method'],
                "unit_price" => $GLOBALS['order']->info['shipping_cost'],
                "quantity" => 1,
                "merchant_item_id" => 'msp-shipping',
                "tax_table_selector" => current(array_keys($GLOBALS['order']->info['tax_groups'])),
                "weight" => array(
                    "unit" => "KG",
                    "value" => 0
                )
            );
        }

        /**
          //Fee

          if (isset($GLOBALS['surcharge_cost']))
          {

          $fee_inc_tax = $GLOBALS['surcharge_cost'];

          if ($fee_inc_tax > 2.95)
          {
          $this->_error_redirect('Multisafepay fee to high!');
          exit();
          }

          $tax = ($fee_inc_tax / 121) * 21;
          $fee = $fee_inc_tax - $tax;
          $fee = number_format($fee, 4, '.', '');

          $c_item = new MspItem('Fee', 'Fee', 1, $fee, 'KG', 0); // Todo adjust the amount to cents, and round it up.
          $c_item->SetMerchantItemId('Fee');
          $c_item->SetTaxTableSelector('BTW21');
          $msp->cart->AddItem($c_item);
          }

          //add coupon

          if (isset($GLOBALS['ot_coupon']->deduction))
          {
          if ($GLOBALS['ot_coupon']->deduction != '')
          {
          $c_item = new MspItem('Discount' . " " . $GLOBALS['order']->info['currency'], 'Shipping', '1', -$GLOBALS['ot_coupon']->deduction, '0', '0');
          $msp->cart->AddItem($c_item);
          $c_item->SetMerchantItemId('discount');
          $c_item->SetTaxTableSelector('BTW0');
          }
          }

         */
        return $shoppingcart_array;
    }

    /**
     * 
     * @param type $needle
     * @param type $haystack
     * @param type $strict
     * @return boolean
     */
    function in_array_recursive($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $item) {
            if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_recursive($needle, $item, $strict))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 
     * @param type $street_address
     * @return type
     */
    public function parseAddress($street_address)
    {
        $address = $street_address;
        $apartment = "";

        $offset = strlen($street_address);

        while (($offset = $this->rstrpos($street_address, ' ', $offset)) !== false) {
            if ($offset < strlen($street_address) - 1 && is_numeric($street_address[$offset + 1])) {
                $address = trim(substr($street_address, 0, $offset));
                $apartment = trim(substr($street_address, $offset + 1));
                break;
            }
        }

        if (empty($apartment) && strlen($street_address) > 0 && is_numeric($street_address[0])) {
            $pos = strpos($street_address, ' ');

            if ($pos !== false) {
                $apartment = trim(substr($street_address, 0, $pos), ", \t\n\r\0\x0B");
                $address = trim(substr($street_address, $pos + 1));
            }
        }

        return array($address, $apartment);
    }

    /**
     * 
     * @param type $haystack
     * @param type $needle
     * @param type $offset
     * @return boolean
     */
    public function rstrpos($haystack, $needle, $offset = null)
    {
        $size = strlen($haystack);

        if (is_null($offset)) {
            $offset = $size;
        }

        $pos = strpos(strrev($haystack), strrev($needle), $size - $offset);

        if ($pos === false) {
            return false;
        }

        return $size - $pos - strlen($needle);
    }

    /**
     * 
     * @param type $error
     * @return type
     */
    public function getErrorcode($error)
    {
        return substr($error, 0, 4);
    }

    /**
     * 
     * @global type $db
     * @global type $order
     * @global type $currencies
     * @param type $manual_status
     * @return type
     */
    function checkout_notify($manual_status = '')
    {
        global $db, $order, $currencies;

        include(DIR_WS_LANGUAGES . $_SESSION['language'] . "/checkout_process.php");

        try {
            $this->msp = new MultiSafepayAPI\Client();

            if (MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER == 'Live' || MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER == 'Live account') {
                $this->api_url = 'https://api.multisafepay.com/v1/json/';
            } else {
                $this->api_url = 'https://testapi.multisafepay.com/v1/json/';
            }

            $this->msp->setApiUrl($this->api_url);
            $this->msp->setApiKey(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_KEY);

            $response = $this->msp->orders->get('orders', $this->order_id);
            $status = $response->status;
            $pspid = $response->transaction_id;
        } catch (Exception $e) {
            echo htmlspecialchars($e->getMessage());
            die();
        }

        $order->customer['firstname'] = $response->customer->first_name;
        $order->customer['lastname'] = $response->customer->last_name;
        $_SESSION['customer_id'] = $response->var1;
        $_SESSION['billto'] = $response->var2;
        $_SESSION['sendto'] = $response->var3;
        $reset_cart = false;
        $notify_customer = false;

        $current_order = $db->Execute("SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . $this->order_id);

        if ($manual_status != '') {
            $status = $manual_status;
        }

        $old_order_status = $current_order->fields['orders_status'];

        switch ($status) {
            case "initialized":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED;
                $reset_cart = true;
                break;
            case "completed":

                if (in_array($old_order_status, array(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED, DEFAULT_ORDERS_STATUS_ID, 0, MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_UNCLEARED))) {
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_COMPLETED;
                    $reset_cart = true;
                    $notify_customer = true;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_COMPLETED;
                } else {
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_COMPLETED;
                }


                break;
            case "uncleared":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_UNCLEARED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_UNCLEARED;
                break;
            case "reserved":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED;
                break;
            case "void":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID) {
                    $order_query = $db->Execute("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "cancelled":

                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID) {
                    $order_query = $db->Execute("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "declined":

                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_DECLINED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_DECLINED;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_DECLINED) {
                    $order_query = $db->Execute("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "reversed":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED;
                break;
            case "refunded":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REFUNDED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REFUNDED;
                break;
            case "partial_refunded":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                break;
            case "expired":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_EXPIRED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_EXPIRED;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_EXPIRED) {
                    $order_query = $db->Execute("select products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            default:
                $GLOBALS['order']->info['order_status'] = DEFAULT_ORDERS_STATUS_ID;
        }

        $order_status_query = $db->Execute("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = '" . $GLOBALS['order']->info['order_status'] . "' AND language_id = '" . $GLOBALS['languages_id'] . "'");
        $order_status = $order_status_query; //zen_db_fetch_array($order_status_query);

        if ($new_stat == 0){
            $new_stat = DEFAULT_ORDERS_STATUS_ID;
        }

        $GLOBALS['order']->info['orders_status'] = $order_status->fields['orders_status_name'];

        if ($old_order_status != $new_stat) {
            // update order
            $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . $new_stat . " WHERE orders_id = " . $this->order_id);
        }

        $order->products_ordered = '';

        foreach ($order->products as $product) {
            $order->products_ordered .= $product['qty'] . ' x ' . $product['name'] . ($product['model'] != '' ? ' (' . $product['model'] . ') ' : '') . ' = ' .
                    $currencies->display_price($product['final_price'], $product['tax'], $product['qty']) .
                    ($product['onetime_charges'] != 0 ? "\n" . TEXT_ONETIME_CHARGES_EMAIL . $currencies->display_price($product['onetime_charges'], $product['tax'], 1) : '') .
                    $order->products_ordered_attributes . "\n";
            $i++;
        }

        if ($notify_customer) {
            $order->send_order_email($this->order_id, 2);
            unset($_SESSION['customer_id']);
            unset($_SESSION['billto']);
            unset($_SESSION['sendto']);
        }

        // if we don't inform the customer about the update, check if there's a new status. If so, update the order_status_history table accordingly
        $last_osh_status_r = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = '" . $this->order_id . "' ORDER BY date_added DESC limit 1");

        if (($last_osh_status_r->fields['orders_status_id'] != $GLOBALS['order']->info['order_status'])) {
            if (!is_null($pspid)) {
                $comment = 'MultiSafepay ID: ' . $pspid;
            }

            $sql_data_array = array('orders_id' => $this->order_id,
                'orders_status_id' => $GLOBALS['order']->info['order_status'],
                'date_added' => 'now()',
                'customer_notified' => 1,
                'comments' => $comment,
                'updated_by' => 'MultiSafepay'
            );

            zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
        }

        if ($reset_cart) {
            $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");
            $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");
        }

        return $status;
    }

    /**
     * 
     * @global type $db
     * @param type $code
     * @return type
     */
    function get_country_from_code($code)
    {
        global $db;
        //countries_iso_code_2
        $country = $db->Execute("select * from " . TABLE_COUNTRIES . " where countries_iso_code_2 = '" . $code . "'");

        return $country;
    }

    /**
     * 
     * @param type $order_id
     * @param type $customer_id
     * @return type
     */
    function get_hash($order_id, $customer_id)
    {
        return md5($order_id . $customer_id);
    }

    /**
     * 
     * @param type $error
     */
    function _error_redirect($error)
    {
        global $messageStack;

        $messageStack->add_session('checkout_payment', $error, 'error');
        zen_redirect('index.php?main_page=' . FILENAME_CHECKOUT_PAYMENT);
    }

    // ---- Ripped from includes/functions/general.php ----

    function _address_format($address_format_id, $address, $html, $boln, $eoln)
    {
        global $db;
        $address_format_query = $db->Execute("SELECT address_format AS format FROM " . TABLE_ADDRESS_FORMAT . " WHERE address_format_id = '" . (int) $address_format_id . "'");
        $address_format = $address_format_query; //zen_db_fetch_array($address_format_query);

        $company = $this->_output_string_protected($address['company']);
        if (isset($address['firstname']) && zen_not_null($address['firstname'])) {
            $firstname = $this->_output_string_protected($address['firstname']);
            $lastname = $this->_output_string_protected($address['lastname']);
        } elseif (isset($address['name']) && zen_not_null($address['name'])) {
            $firstname = $this->_output_string_protected($address['name']);
            $lastname = '';
        } else {
            $firstname = '';
            $lastname = '';
        }
        $street = $this->_output_string_protected($address['street_address']);
        $suburb = $this->_output_string_protected($address['suburb']);
        $city = $this->_output_string_protected($address['city']);
        $state = $this->_output_string_protected($address['state']);
        if (isset($address['country_id']) && zen_not_null($address['country_id'])) {
            $country = zen_get_country_name($address['country_id']);
            if (isset($address['zone_id']) && zen_not_null($address['zone_id'])) {
                $state = zen_get_zone_code($address['country_id'], $address['zone_id'], $state);
            }
        } elseif (isset($address['country']) && zen_not_null($address['country'])) {
            if (is_array($address['country'])) {
                $country = $this->_output_string_protected($address['country']['title']);
            } else {
                $country = $this->_output_string_protected($address['country']);
            }
        } else {
            $country = '';
        }
        $postcode = $this->_output_string_protected($address['postcode']);
        $zip = $postcode;

        if ($html) {
            // HTML Mode
            $HR = '<hr>';
            $hr = '<hr>';
            if (($boln == '') && ($eoln == "\n")) { // Values not specified, use rational defaults
                $CR = '<br>';
                $cr = '<br>';
                $eoln = $cr;
            } else { // Use values supplied
                $CR = $eoln . $boln;
                $cr = $CR;
            }
        } else {
            // Text Mode
            $CR = $eoln;
            $cr = $CR;
            $HR = '----------------------------------------';
            $hr = '----------------------------------------';
        }

        $statecomma = '';
        $streets = $street;
        if ($suburb != '')
            $streets = $street . $cr . $suburb;
        if ($state != '')
            $statecomma = $state . ', ';

        $fmt = $address_format['format'];
        eval("\$address = \"$fmt\";");

        if ((ACCOUNT_COMPANY == 'true') && (zen_not_null($company))) {
            $address = $company . $cr . $address;
        }
        return $address;
    }

    /**
     * 
     * @param type $string
     * @param type $translate
     * @param type $protected
     * @return type
     */
    function _output_string($string, $translate = false, $protected = false)
    {
        if ($protected == true) {
            return htmlspecialchars($string);
        } else {
            if ($translate == false) {
                return $this->_parse_input_field_data($string, array('"' => '&quot;'));
            } else {
                return $this->_parse_input_field_data($string, $translate);
            }
        }
    }

    /**
     * 
     * @param type $string
     * @return type
     */
    function _output_string_protected($string)
    {
        return $this->_output_string($string, false, true);
    }

    /**
     * 
     * @param type $data
     * @param type $parse
     * @return type
     */
    function _parse_input_field_data($data, $parse)
    {
        return strtr(trim($data), $parse);
    }

    /**
     * 
     * @global type $request_type
     * @global type $session_started
     * @global type $SID
     * @param type $page
     * @param type $parameters
     * @param type $connection
     * @param type $add_session_id
     * @param type $unused
     * @param type $escape_html
     * @return string
     */
    function _href_link($page = '', $parameters = '', $connection = 'NONSSL', $add_session_id = true, $unused = true, $escape_html = true)
    {
        global $request_type, $session_started, $SID;

        unset($unused);

        if (!zen_not_null($page)) {
            die('</td></tr></table></td></tr></table><br><br><font color="#ff0000"><b>Error!</b></font><br><br><b>Unable to determine the page link!<br><br>');
        }

        if ($connection == 'NONSSL') {
            $link = HTTP_SERVER . DIR_WS_HTTPS_CATALOG;
        } elseif ($connection == 'SSL') {

            if (ENABLE_SSL == true) {
                $link = HTTPS_SERVER . DIR_WS_HTTPS_CATALOG;
            } else {
                $link = HTTP_SERVER . DIR_WS_HTTPS_CATALOG;
            }
        } else {
            die('</td></tr></table></td></tr></table><br><br><font color="#ff0000"><b>Error!</b></font><br><br><b>Unable to determine connection method on a link!<br><br>Known methods: NONSSL SSL</b><br><br>');
        }

        if (zen_not_null($parameters)) {
            if ($escape_html) {
                $link .= $page . '?' . $this->_output_string($parameters);
            } else {
                $link .= $page . '?' . $parameters;
            }
            $separator = '&';
        } else {
            $link .= $page;
            $separator = '?';
        }

        while ((substr($link, -1) == '&') || (substr($link, -1) == '?')) {
            $link = substr($link, 0, -1);
        }

        if (($add_session_id == true) && ($session_started == true) && (SESSION_FORCE_COOKIE_USE == 'False')) {
            if (zen_not_null($SID)) {
                $_sid = $SID;
            } elseif (( ($request_type == 'NONSSL') && ($connection == 'SSL') && (ENABLE_SSL == true) ) || ( ($request_type == 'SSL') && ($connection == 'NONSSL') )) {
                if (HTTP_COOKIE_DOMAIN != HTTPS_COOKIE_DOMAIN) {

                    $_sid = zen_session_name() . '=' . zen_session_id();
                }
            }
        }

        if (isset($_sid)) {
            if ($escape_html) {
                $link .= $separator . $this->_output_string($_sid);
            } else {
                $link .= $separator . $_sid;
            }
        }


        return $link;
    }

    // ---- installation & configuration ----

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
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS'");
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

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('MultiSafepay E-Invoice enabled', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS', 'True', 'Enable MultiSafepay E-Invoice module for this website', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Account type', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_SERVER', 'Live account', '<a href=\'https://www.multisafepay.com/signup/\' target=\'_blank\' style=\'text-decoration: underline; font-weight: bold; color:#696916; \'>Click here to signup for an account.</a>', '6', '21', 'zen_cfg_select_option(array(\'Live account\', \'Test account\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('API Key', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_API_KEY', '', 'Your MultiSafepay API Key', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Direct E-Invoice', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DIRECT', 'False', 'Allow customers to enter their birthday and bankaccount info during checkout.', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Automatically redirect the customer back to the webshop.', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_AUTO_REDIRECT', 'True', 'Enable auto redirect after payment', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Google Analytics', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_GA_ACCOUNT', '', 'Google Analytics Account ID', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Days Active.', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_DAYS_ACTIVE', '', 'Allow MultiSafepay to attempt to close unpaid orders after X number of days.', '6', '22', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '25', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Initialized Order Status', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_INITIALIZED', 0, 'In progress', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Completed Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_COMPLETED',   0, 'Completed successfully', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Uncleared Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_UNCLEARED',   0, 'Not yet cleared', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reserved Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_RESERVED',    0, 'Reserved', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Voided Order Status',      'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_VOID',        0, 'Cancelled', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Declined Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_DECLINED',    0, 'Declined (e.g. fraud, not enough balance)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reversed Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REVERSED',    0, 'Undone', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Refunded Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_REFUNDED',    0, 'Refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Expired Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_EXPIRED',     0, 'Expired', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Partial Refunded Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_ORDER_STATUS_ID_PARTIAL_REFUNDED',     0, 'Partial Refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Minimum transaction amount', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MIN_AMOUNT', '1500', 'Minimum amount for E-Invoice in cents', '6', '23', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Maximum transaction amount', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MAX_AMOUNT', '30000', 'Maximum amount for E-Invoice in cents', '6', '23', now())");
    }

    /**
     * Removes the configuration keys from the database
     * 
     * @global type $db
     */
    function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    /**
     * Defines an array containing the configuration key keys that are used by the payment module
     * 
     * @return type
     */
    function keys()
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

    /**
     * Return locale language code based on $lang provided
     * 
     * @param type $lang
     * @return string
     */
    function getlocale($lang)
    {
        switch ($lang) {
            case "dutch":
                $lang = 'nl_NL';
                break;
            case "spanish":
                $lang = 'es_ES';
                break;
            case "french":
                $lang = 'fr_FR';
                break;
            case "italian":
            case "italiano":
                $lang = 'it_IT';
                break;
            case "portuguese":
                $lang = 'pt_PT';
                break;
            case "german":
                $lang = 'de_DE';
                break;
            case "english":
                $lang = 'en_GB';
                break;
            default:
                $lang = 'en_GB';
                break;
        }

        return $lang;
    }

    /**
     * 
     * @param type $country
     * @return type
     */
    function getcountry($country)
    {
        if (empty($country)) {
            $langcode = explode(";", $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $langcode = explode(",", $langcode['0']);
            return strtoupper($langcode['1']);
        } else {
            return strtoupper($country);
        }
    }

}

?>
