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

if (!class_exists('multisafepay')) {

    class multisafepay
    {
        var $code;
        var $title;
        var $description;
        var $enabled;
        var $sort_order;
        var $plugin_ver = "3.0.0";
        var $icon = "connect.png";
        var $api_url;
        var $order_id;
        var $status;
        var $order_status;
        var $shipping_methods = array();
        var $taxes = array();
        var $msp;

        /*
         * Constructor
         */

        function multisafepay($order_id = -1)
        {
            global $order;

            $this->code = 'multisafepay';
            $this->title = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE);
            $this->description = '<strong>' . $this->title . "&nbsp;&nbsp;v" . $this->plugin_ver .  '</strong><br>The main MultiSafepay module must be installed (does not have to be active) to use this payment method.<br>';
            $this->enabled = MODULE_PAYMENT_MULTISAFEPAY_STATUS == 'True';
            $this->sort_order = MODULE_PAYMENT_MULTISAFEPAY_SORT_ORDER;
            $this->order_status = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED;

            if (is_object($order)) {
                $this->update_status();
            }

            $this->order_id = $order_id;
            $this->status = 1;
        }

        /*
         * Check whether this payment module is available
         */

        function update_status()
        {
            global $order, $db;

            if (($this->enabled == true) && ((int) MODULE_PAYMENT_MULTISAFEPAY_ZONE > 0)) {
                $check_flag = false;
                $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_MULTISAFEPAY_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
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

        // ---- select payment module ----

        /*
         * Client side javascript that will verify any input fields you use in the
         * payment method selection page
         */
        function javascript_validation()
        {
            return false;
        }

        /**
         * @return array
         */
        public function selection()
        {
            return array(
                'id' => $this->code,
                'module' => $this->title
            );
        }

        /*
         * Any checks of any conditions after payment method has been selected
         */

        function pre_confirmation_check()
        {

        }

        // ---- confirm order ----

        /*
         * Any checks or processing on the order information before proceeding to
         * payment confirmation
         */
        function confirmation()
        {
            global $HTTP_POST_VARS, $order;

            return false;
        }

        /*
         * Outputs the html form hidden elements sent as POST data to the payment
         * gateway
         */

        function process_button()
        {
            return zen_draw_hidden_field('msp_paymentmethod', '');
        }

        function before_process()
        {
            $GLOBALS['order']->info['payment_method'] = trim(strip_tags($GLOBALS['order']->info['payment_method']));
        }

        function after_process()
        {
            $this->prepare_transaction();
            zen_redirect($this->start_transaction());
        }

        function output_error()
        {
            return false;
        }

        function prepare_transaction()
        {
            $this->trans_type = isset($this->trans_type) ? $this->trans_type : 'redirect';
            $this->gateway_info = null;
        }

        /**
         *
         * @return type
         */
        function start_transaction()
        {
            global $insert_id;

            $this->api_key = $this->get_api_key();
            $this->api_url = $this->get_api_url();
            $this->redirect_url = $this->get_redirect_url();
            $this->gateway = $_POST['msp_paymentmethod'];
            $this->order_id = $insert_id;

            $order = $GLOBALS['order'];

            $items = "<ul>\n";
            foreach ($order->products as $product) {
                $items .= "<li>" . $product['qty'] . 'x ' . $product['name'] . "</li>\n";
            }
            $items .= "</ul>\n";

            $amount = round($order->info['total'], 2) * 100;

            if ($_POST["msp_issuer"] && $this->gateway == 'IDEAL') {
                $this->trans_type = "direct";
                $this->gateway_info = array(
                    'issuer_id' => $_POST["msp_issuer"]
                );
            }

            if (MODULE_PAYMENT_MSP_BANKTRANS_DIRECT == 'True' && $this->gateway == 'BANKTRANS') {
                $this->trans_type = "direct";
            }

            if (isset($order->customer['firstname'])) {
                list($cust_street, $cust_housenumber) = $this->parseAddress($order->customer['street_address']);
                $locale = strtolower($order->customer['country']['iso_code_2']) . '_' . $order->customer['country']['iso_code_2'];

                $customer_data = array(
                    "locale" => $locale,
                    "ip_address" => $_SERVER['REMOTE_ADDR'],
                    "forwarded_ip" => $_SERVER['HTTP_X_FORWARDED_FOR'],
                    "first_name" => $order->customer['firstname'],
                    "last_name" => $order->customer['lastname'],
                    "address1" => $cust_street,
                    "address2" => null,
                    "house_number" => $cust_housenumber,
                    "zip_code" => $order->customer['postcode'],
                    "city" => $order->customer['city'],
                    "state" => $order->customer['state'],
                    "country" => $order->customer['country']['iso_code_2'],
                    "phone" => $order->customer['telephone'],
                    "email" => $order->customer['email_address'],
                    "disable_send_email" => false,
                    "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                    "referrer" => $_SERVER['HTTP_REFERER']
                );
            } else {
                list($billing_street, $billing_housenumber) = $this->parseAddress($order->billing['street_address']);
                $locale = strtolower($order->billing['country']['iso_code_2']) . '_' . $order->billing['country']['iso_code_2'];

                $customer_data = array(
                    "locale" => $locale,
                    "ip_address" => $_SERVER['REMOTE_ADDR'],
                    "forwarded_ip" => $_SERVER['HTTP_X_FORWARDED_FOR'],
                    "first_name" => $order->billing['firstname'],
                    "last_name" => $order->billing['lastname'],
                    "address1" => $billing_street,
                    "address2" => null,
                    "house_number" => $billing_housenumber,
                    "zip_code" => $order->billing['postcode'],
                    "city" => $order->billing['city'],
                    "state" => $order->billing['state'],
                    "country" => $order->billing['country']['iso_code_2'],
                    "phone" => $order->customer['telephone'],
                    "email" => $order->customer['email_address'],
                    "disable_send_email" => false,
                    "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                    "referrer" => $_SERVER['HTTP_REFERER']
                );
            }

            if (isset($order->delivery['firstname'])) {
                list($delivery_street, $delivery_housenumber) = $this->parseAddress($order->delivery['street_address']);

                $delivery_data = array(
                    "first_name" => $order->delivery['firstname'],
                    "last_name" => $order->delivery['lastname'],
                    "address1" => $delivery_street,
                    "address2" => null,
                    "house_number" => $delivery_housenumber,
                    "zip_code" => $order->delivery['postcode'],
                    "city" => $order->delivery['city'],
                    "state" => $order->delivery['state'],
                    "country" => $order->delivery['country']['iso_code_2'],
                );
            }

            try {
                $this->msp = new MultiSafepayAPI\Client();

                $this->api_url = $this->get_api_url();

                $this->msp->setApiUrl($this->api_url);
                $this->msp->setApiKey($this->get_api_key());

                $this->msp->orders->post(array(
                    "type" => $this->trans_type,
                    "order_id" => $this->order_id,
                    "currency" => $order->info['currency'],
                    "amount" => round($amount),
                    "gateway" => $this->gateway,
                    "description" => "Order #" . $this->order_id . " " . MODULE_PAYMENT_MULTISAFEPAY_TEXT_AT . " " . STORE_NAME,
                    "items" => $items,
                    "manual" => false,
                    "days_active" => MODULE_PAYMENT_MULTISAFEPAY_DAYS_ACTIVE,
                    "payment_options" => array(
                        "notification_url" => $this->_href_link('ext/modules/payment/multisafepay/notify_checkout.php?type=initial&', '', 'NONSSL', false, false),
                        "redirect_url" => $this->redirect_url,
                        "cancel_url" => $this->_href_link('ext/modules/payment/multisafepay/cancel.php'),
                        "close_window" => true
                    ),
                    "customer" => $customer_data,
                    "delivery" => $delivery_data,
                    "gateway_info" => $this->gateway_info,
                    "shopping_cart" => $this->getShoppingCart($order),
                    "checkout_options" => $this->getCheckoutOptions(),

                    "google_analytics" => array(
                        "account" => MODULE_PAYMENT_MULTISAFEPAY_GA
                    ),
                    "plugin" => array(
                        "shop" => PROJECT_VERSION_NAME,
                        "shop_version" => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR,
                        "plugin_version" => $this->plugin_ver,
                        "partner" => 'MultiSafepay',
                        "shop_root_url" => $_SERVER['SERVER_NAME']
                    )
                ));

                if ($this->gateway == 'BANKTRANS' && $this->trans_type == 'direct') {
                    zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
                } else {
                    return $this->msp->orders->getPaymentLink();
                }
            } catch (Exception $e) {
                $this->_error_redirect(htmlspecialchars($e->getMessage()));
                die();
            }
        }



        /**
         * Fetches the items and related data, and builds the $shoppingcart_array
         *
         * @global type $order
         * @return type array
         */
        function getShoppingCart($order)
        {
            $shoppingcart_array = array();

            foreach ($order->products as $product) {
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

            if (isset($order->info['shipping_method'])) {
                $shoppingcart_array['items'][] = array(
                    "name" => $order->info['shipping_method'],
                    "description" => $order->info['shipping_method'],
                    "unit_price" => $order->info['shipping_cost'],
                    "quantity" => 1,
                    "merchant_item_id" => 'msp-shipping',
                    "tax_table_selector" => current(array_keys($order->info['tax_groups'])),
                    "weight" => array(
                        "unit" => "KG",
                        "value" => 0
                    )
                );
            }

            if (isset($GLOBALS['ot_coupon']->deduction)) {
                if ($GLOBALS['ot_coupon']->deduction != '') {
                    if ($GLOBALS['ot_coupon']->include_tax != "true") {
                        $this->_error_redirect("The option \"Include Tax\" must be enabled under Modules > Order Total, when processing discounts.");
                    }

                    $shoppingcart_array['items'][] = array(
                        "name" => $GLOBALS['ot_coupon']->title,
                        "description" => $GLOBALS['ot_coupon']->header,
                        "unit_price" => -$GLOBALS['ot_coupon']->deduction,
                        "quantity" => 1,
                        "merchant_item_id" => $GLOBALS['ot_coupon']->code,
                        "tax_table_selector" => "BTW0",
                        "weight" => array(
                            "unit" => "KG",
                            "value" => 0
                        )
                    );
                }
            }
            return $shoppingcart_array;
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
                            "rules" => array(array
                            (
                                "rate" => 0.00
                            ))
                        );
                    }
                }
            }

            //if (!$this->in_array_recursive(key($product['tax_groups']), $checkoutoptions_array['tax_tables']['alternate'])) {
            $checkoutoptions_array['tax_tables']['alternate'][] = array(
                "standalone" => false,
                "name" => "BTW0",
                "rules" => array(array
                (
                    "rate" => 0.00
                ))
            );
            //}


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
         * @return type
         */
        function check_transaction()
        {
            try {
                $this->msp = new MultiSafepayAPI\Client();

                $this->api_url = $this->get_api_url();

                $this->msp->setApiUrl($this->api_url);
                $this->msp->setApiKey($this->get_api_key());

                $response = $this->msp->orders->get('orders', $this->order_id);

                return $response;
            } catch (Exception $e) {
                return htmlspecialchars($e->getMessage());
            }
        }

        /**
         * Checks current order status and updates the database
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

                $this->api_url = $this->get_api_url();

                $this->msp->setApiUrl($this->api_url);
                $this->msp->setApiKey($this->get_api_key());

                $response = $this->msp->orders->get('orders', $this->order_id);
                $status = $response->status;
                $pspid = $response->transaction_id;
            } catch (Exception $e) {
                echo htmlspecialchars($e->getMessage());
                die();
            }

            $order->customer['firstname'] = $response->customer->first_name;
            $order->customer['lastname'] = $response->customer->last_name;
            $reset_cart = false;

            $current_order = $db->Execute("SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . $this->order_id);

            if ($manual_status != '') {
                $status = $manual_status;
            }

            $old_order_status = $current_order->fields['orders_status'];

            $new_stat = DEFAULT_ORDERS_STATUS_ID;
            switch ($status) {
                case "initialized":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED;
                    $reset_cart = true;
                    break;
                case "completed":
                    if (in_array($old_order_status, array(MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED, DEFAULT_ORDERS_STATUS_ID, MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED))) {
                        $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED;
                        $reset_cart = true;
                        $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED;
                    } else {
                        $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED;
                    }

                    break;
                case "uncleared":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED;
                    $reset_cart = true;
                    break;
                case "reserved":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED;
                    break;
                case "void":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID;
                    if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID) {
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

                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID;

                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID;

                    if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID) {

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

                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED;
                    if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED) {
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
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED;
                    break;
                case "refunded":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED;
                    break;
                case "partial_refunded":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                    break;
                case "expired":
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED;
                    if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED) {
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

            // if we don't inform the customer about the update, check if there's a new status. If so, update the order_status_history table accordingly
            $last_osh_status_r = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS_HISTORY . " WHERE orders_id = '" . $this->order_id . "' ORDER BY date_added DESC limit 1");

            if (($last_osh_status_r->fields['orders_status_id'] != $GLOBALS['order']->info['order_status']) && (!empty($GLOBALS['order']->info['order_status']) )) {

                if (!is_null($pspid)) {
                    $comment = 'MultiSafepay ID: ' . $pspid;
                }

                $sql_data_array = array(
                    'orders_id' => $this->order_id,
                    'orders_status_id' => $GLOBALS['order']->info['order_status'],
                    'date_added' => 'now()',
                    'customer_notified' => 1,
                    'comments' => $comment,
                    'updated_by' => 'MultiSafepay'
                );

                zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            }

            // reset cart
            if ($reset_cart) {
                $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");

                $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");
            }

            return $status;
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

        /**
         * Ripped from includes/functions/general.php
         *
         * @param type $address_format_id
         * @param string $address
         * @param type $html
         * @param type $boln
         * @param type $eoln
         * @return string
         */
        function _address_format($address_format_id, $address, $html, $boln, $eoln)
        {
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

            // Add the session ID when moving from different HTTP and HTTPS servers, or when SID is defined
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

        /*
         * Checks whether the payment has been “installed” through the admin panel
         */
        function check()
        {
            global $db;
            if (!isset($this->_check)) {
                $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_STATUS'");
                $this->_check = $check_query->RecordCount();
            }
            return $this->_check;
        }

        /*
         * Installs the configuration keys into the database
         */

        function install()
        {
            global $db;

            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('MultiSafepay enabled', 'MODULE_PAYMENT_MULTISAFEPAY_STATUS', 'True', 'Enable MultiSafepay payments for this website', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Type account', 'MODULE_PAYMENT_MULTISAFEPAY_API_SERVER', 'Live account', '<a href=\'https://testmerchant.multisafepay.com/signup\' target=\'_blank\' style=\'text-decoration: underline; font-weight: bold; color:#696916; \'>Sign up for a free test account!</a>', '6', '21', 'zen_cfg_select_option(array(\'Live account\', \'Test account\'), ', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('API Key', 'MODULE_PAYMENT_MULTISAFEPAY_API_KEY', '', 'Your MultiSafepay API Key', '6', '22', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Auto Redirect', 'MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT', 'True', 'Enable auto redirect after payment', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MULTISAFEPAY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '25', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MULTISAFEPAY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Daysactive', 'MODULE_PAYMENT_MULTISAFEPAY_DAYS_ACTIVE', '', 'The number of days a paymentlink remains active.', '6', '22', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Google Analytics', 'MODULE_PAYMENT_MULTISAFEPAY_GA', '', 'Google Analytics Account ID', '6', '22', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Initialized Order Status', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED', 0, 'In progress', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Completed Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED',   0, 'Completed successfully', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Uncleared Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED',   0, 'Not yet cleared', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reserved Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED',    0, 'Reserved', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Voided Order Status',      'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID',        0, 'Cancelled', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Declined Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED',    0, 'Declined (e.g. fraud, not enough balance)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reversed Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED',    0, 'Undone', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Refunded Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED',    0, 'Refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Expired Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED',     0, 'Expired', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Partial Refunded Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED',     0, 'Partial Refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable payment method icons', 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED', 'False', 'Enable payment method icons in front of the title', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        }

        /*
         * Removes the configuration keys from the database
         */

        function remove()
        {
            global $db;
            $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
        }

        /*
         * Defines an array containing the configuration key keys that are used by
         * the payment module
         */

        function keys()
        {
            return array(
                'MODULE_PAYMENT_MULTISAFEPAY_STATUS',
                'MODULE_PAYMENT_MULTISAFEPAY_API_SERVER',
                'MODULE_PAYMENT_MULTISAFEPAY_API_KEY',
                'MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT',
                'MODULE_PAYMENT_MULTISAFEPAY_ZONE',
                'MODULE_PAYMENT_MULTISAFEPAY_SORT_ORDER',
                'MODULE_PAYMENT_MULTISAFEPAY_DAYS_ACTIVE',
                'MODULE_PAYMENT_MULTISAFEPAY_GA',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED',
                'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED',
                'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED',
            );
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
         * @param string $title
         * @return string|type
         */
        public function getTitle($title = 'MultiSafepay')
        {
            if ($this->checkView() == "admin") {
                return 'MultiSafepay - ' . $title;
            }

            $title = $this->getLangStr($title);
            if (MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED == 'True') {
                $title = $this->generateIcon($this->getIcon()) . " " . $title;
            }
            return $title;
        }

        /**
         *
         * @param type $str
         * @return type
         */
        function getLangStr($str)
        {
            switch ($str) {
                //Payment methods
                case "AfterPay":
                    return MODULE_PAYMENT_MSP_AFTERPAY_TEXT_TITLE;
                case "Santander Consumer Finance | Betaal per Maand":
                case "Santander Consumer Finance | Pay per Month":

                    if (MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED == 'False') {
                        return MODULE_PAYMENT_MSP_SANTANDER_TEXT_TITLE;
                    }else {
                        return MODULE_PAYMENT_MSP_SANTANDER_SHORT_TEXT_TITLE;
                    }

                case "title":
                    return MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE;
                case "iDEAL":
                    return MODULE_PAYMENT_MSP_IDEAL_TEXT_TITLE;
                case "Banktransfer":
                case "Virement bancaire":
                case "Bankoverboeking":
                case "Banküberweisung":
                case "Transferencia bancaria":
                case "Bonifico bancario":
                case "Transferência Bancária":
                    return MODULE_PAYMENT_MSP_BANKTRANS_TEXT_TITLE;
                case "Giropay":
                    return MODULE_PAYMENT_MSP_GIROPAY_TEXT_TITLE;
                case "VISA":
                    return MODULE_PAYMENT_MSP_VISA_TEXT_TITLE;
                case "Direct Debit":
                case "Prélèvement automatique":
                case "Eenmalige machtiging":
                case "Lastschrift":
                case "Débito Directo":
                case "Addebito diretto":
                case "Débito Direto":
                    return MODULE_PAYMENT_MSP_DIRDEB_TEXT_TITLE;
                case "Bancontact":
                    return MODULE_PAYMENT_MSP_BANCONTACT_TEXT_TITLE;
                case "MasterCard":
                    return MODULE_PAYMENT_MSP_MASTERCARD_TEXT_TITLE;
                case "PayPal":
                    return MODULE_PAYMENT_MSP_PAYPAL_TEXT_TITLE;
                case "Maestro":
                    return MODULE_PAYMENT_MSP_MAESTRO_TEXT_TITLE;
                case "SOFORT Banking":
                case "SOFORT Überweisung":
                    return MODULE_PAYMENT_MSP_SOFORT_TEXT_TITLE;
                case "American Express":
                    return MODULE_PAYMENT_MSP_AMEX_TEXT_TITLE;
                case "Dotpay":
                    return MODULE_PAYMENT_MSP_DOTPAY_TEXT_TITLE;
                case "EPS":
                    return MODULE_PAYMENT_MSP_EPS_TEXT_TITLE;
                case "PaySafeCard":
                    return MODULE_PAYMENT_MSP_PAYSAFECARD_TEXT_TITLE;
                case "Direct Bank Transfer":
                    return MODULE_PAYMENT_MSP_DIRECTBANKTRANSFER_TEXT_TITLE;
                case "Belfius":
                    return MODULE_PAYMENT_MSP_BELFIUS_TEXT_TITLE;
                case "ING Home'Pay":
                    return MODULE_PAYMENT_MSP_ING_TEXT_TITLE;
                case "KBC":
                    return MODULE_PAYMENT_MSP_KBC_TEXT_TITLE;
                case "Alipay":
                    return MODULE_PAYMENT_MSP_ALIPAY_TEXT_TITLE;
                case "Trustly":
                    return MODULE_PAYMENT_MSP_TRUSTLY_TEXT_TITLE;

                //Giftcards
                case "Beauty & Wellness Cadeau":
                    return MODULE_PAYMENT_MSP_BEAUTYANDWELLNESS_TEXT_TITLE;
                case "Boekenbon":
                    return MODULE_PAYMENT_MSP_BOEKENBON_TEXT_TITLE;
                case "FashionCheque":
                    return MODULE_PAYMENT_MSP_FASHIONCHEQUE_TEXT_TITLE;
                case "Fashion Giftcard":
                    return MODULE_PAYMENT_MSP_FASHIONGIFTCARD_TEXT_TITLE;
                case "Bloemen Cadeaubon":
                    return MODULE_PAYMENT_MSP_BLOEMENCADEAUBON_TEXT_TITLE;
                case "De Grote Speelgoedwinkel":
                    return MODULE_PAYMENT_MSP_DGSGW_TEXT_TITLE;
                case "Brouwmarkt":
                    return MODULE_PAYMENT_MSP_BROUWMARKT_TEXT_TITLE;
                case "Fietsenbon":
                    return MODULE_PAYMENT_MSP_FIETSENBON_TEXT_TITLE;
                case "GivaCard":
                    return MODULE_PAYMENT_MSP_GIVACARD_TEXT_TITLE;
                case "Good Card":
                    return MODULE_PAYMENT_MSP_GOODCARD_TEXT_TITLE;
                case "GezondheidsBon":
                    return MODULE_PAYMENT_MSP_GEZONDHEIDSBON_TEXT_TITLE;
                case "Webshop Giftcard":
                    return MODULE_PAYMENT_MSP_WEBSHOPGIFTCARD_TEXT_TITLE;
                case "Wijn Cadeaukaart":
                    return MODULE_PAYMENT_MSP_WIJNCADEAU_TEXT_TITLE;
                case "Podium":
                    return MODULE_PAYMENT_MSP_PODIUM_TEXT_TITLE;
                case "YourGift":
                    return MODULE_PAYMENT_MSP_YOURGIFT_TEXT_TITLE;
                case "Winkel Cheque":
                    return MODULE_PAYMENT_MSP_WINKELCHEQUE_TEXT_TITLE;
                case "Sport&Fit Cadeau":
                    return MODULE_PAYMENT_MSP_SPORTNFIT_TEXT_TITLE;
                case "Parfum Cadeaukaart":
                    return MODULE_PAYMENT_MSP_PARFUMCADEAUKAART_TEXT_TITLE;
                case "Jewelstore Giftcard":
                    return MODULE_PAYMENT_MSP_JEWELSTORE_TEXT_TITLE;
                case "Kelly Giftcard":
                    return MODULE_PAYMENT_MSP_KELLYGIFTCARD_TEXT_TITLE;
                case "VVV Giftcard":
                    return MODULE_PAYMENT_MSP_VVVGIFTCARD_TEXT_TITLE;
                case "Nationale Tuinbon":
                    return MODULE_PAYMENT_MSP_TUINBON_TEXT_TITLE;
                case "Wellness Giftcard":
                    return MODULE_PAYMENT_MSP_WELLNESS_TEXT_TITLE;
                case MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE:
                    return MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE;
                    break;
            }
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
         * Generate the payment method icon
         *
         * @param type $icon
         * @return type
         */
        function generateIcon($icon)
        {
            return zen_image($icon, '', 50, 30, 'style="display:inline-block;vertical-align: middle;height:100%;margin-right:10px;"');
        }

        /**
         *
         * @return string
         */
        public function getIcon()
        {
            // Get language specific logo
            $icon = DIR_WS_IMAGES . 'multisafepay/' . $this->getUserLanguage() . '/' . $this->icon;
            if (file_exists($icon)) {
                return $icon;
            }

            // Fallback to default logo's
            $icon = DIR_WS_IMAGES . 'multisafepay/en/' . $this->icon;
            return file_exists($icon) ? $icon : null;
        }

        /**
         * @return string
         */
        public function getUserLanguage()
        {
            return strtolower($_SESSION['languages_code']);
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


        /**
         * call setTransactionStatusToShipped if orderstatus is set to Delivered (3)
         *
         * @param $order_id
         * @param $status
         * @param $comments
         * @param $customer_notified
         * @param $check_status
         */
        public function _doStatusUpdate($order_id, $status, $comments, $customer_notified, $check_status)
        {
            if ($status === 3) {
                $this->setTransactionStatusToShipped($order_id);
            }
            return;
        }

        /**
         * @param $order_id
         */
        private function setTransactionStatusToShipped ($order_id){
            try {
                $msp = new MultiSafepayAPI\Client();
                $api_url = $this->get_api_url();

                $msp->setApiUrl($api_url);
                $msp->setApiKey($this->get_api_key());

                $endpoint = 'orders/' . $order_id;
                $setShipping = array(
                    'tracktrace_code' => null,
                    'carrier'         => null,
                    'status'          => 'shipped',
                    'ship_date'       => date('Y-m-d H:i:s'),
                    'reason'          => 'Shipped');

                $msp->orders->patch($setShipping, $endpoint);
            } catch (Exception $e) {
                $this->_error_redirect(htmlspecialchars($e->getMessage()));
                die;
            }
        }


        /**
         * @return mixed
         */
        public function get_api_key()
        {
            return MODULE_PAYMENT_MULTISAFEPAY_API_KEY;
        }

        /**
         * @return string
         */
        public function get_api_url()
        {
            if (strncmp(MODULE_PAYMENT_MULTISAFEPAY_API_SERVER, 'Live', 4) === 0) {
                return 'https://api.multisafepay.com/v1/json/';
            } else {
                return 'https://testapi.multisafepay.com/v1/json/';
            }
        }

        /**
         * @return string|null
         */
        protected function get_redirect_url()
        {
            $sid = zen_session_name() . '=' . zen_session_id();

            if (MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT) {
                return $this->_href_link('ext/modules/payment/multisafepay/success.php?' . $sid, '', 'NONSSL', false, false);
            } else {
                return null;
            }
        }
    }
}
