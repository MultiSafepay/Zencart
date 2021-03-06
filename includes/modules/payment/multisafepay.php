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
        protected $gateway;
        var $title;
        var $description;
        var $enabled;
        var $sort_order;
        var $plugin_ver = "3.1.0";
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

        public function __construct($order_id = -1)
        {
            global $order;

            $this->code = 'multisafepay';
            $this->gateway = '';
            $this->title = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE);
            $this->description = $this->getDescription();
            $this->enabled = MODULE_PAYMENT_MULTISAFEPAY_STATUS == 'True';
            $this->sort_order = MODULE_PAYMENT_MULTISAFEPAY_SORT_ORDER;
            $this->order_status = MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED;
            $this->paymentFilters = [
                'zone' => MODULE_PAYMENT_MULTISAFEPAY_ZONE
            ];

            if (is_object($order)) {
                $this->update_status();
            }

            $this->order_id = $order_id;
            $this->status = 1;
        }

        /*
         * Check whether this payment module is allowed
         */

        public function update_status()
        {
            global $order, $currencies;

            if ($this->enabled === false) {
                return;
            }

            foreach ($this->paymentFilters as $filter => $value) {
                switch ($filter) {
                    case 'zone':
                        $geoZone = (int)$value;
                        $billing = $order->billing;
                        if ($geoZone > 0 && isset($billing['country_id'])) {
                            $this->enabled = $this->isBillingInZone($billing, $geoZone);
                        }
                        break;

                    case 'minMaxAmount':
                        $minAmount = $currencies->rateAdjusted($value['minAmount']);
                        $maxAmount = $currencies->rateAdjusted($value['maxAmount']);
                        $orderTotal = $currencies->rateAdjusted($order->info['total']);
                        $this->enabled = $this->minMaxAmount($minAmount, $maxAmount, $orderTotal);
                        break;

                    case 'customerInCountry':
                        $this->enabled = in_array($order->customer['country']['iso_code_2'], $value, true);
                        break;

                    case 'deliveryInCountry':
                        $this->enabled = in_array($order->delivery['country']['iso_code_2'], $value, true);
                        break;

                    case 'currencies':
                        $this->enabled = in_array($order->info['currency'], $value, true);
                        break;
                }

                if ($this->enabled === false) {
                    break;
                }
            }
        }

        /**
         * @param $billing
         * @param $geoZone
         * @return bool
         */
        private function isBillingInZone($billing, $geoZone)
        {
            global $db;

            $sql = 'SELECT zone_id FROM ' . TABLE_ZONES_TO_GEO_ZONES . ' WHERE geo_zone_id = :geoZone:' .
                ' AND zone_country_id = :billingCountry: AND (zone_id IS NULL OR zone_id = :billingZone:)';
            $sql = $db->bindVars($sql, ':geoZone:', $geoZone, 'integer');
            $sql = $db->bindVars($sql, ':billingCountry:', $billing['country_id'], 'integer');
            $sql = $db->bindVars($sql, ':billingZone:', $billing['zone_id'], 'integer');
            $result = $db->Execute($sql);
            return $result->RecordCount() !== 0;
        }

        public function minMaxAmount($minAmount, $maxAmount, $orderTotal)
        {
            if (($minAmount > 0 && $orderTotal < $minAmount) || ($maxAmount > 0 && $orderTotal > $maxAmount)) {
                return false;
            }
            return true;
        }


        /**
         * @return bool
         */
        public function process_button()
        {
            return false;
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
            global $insert_id, $currencies;

            $this->api_key = $this->get_api_key();
            $this->api_url = $this->get_api_url();
            $this->redirect_url = $this->get_redirect_url();
            $this->order_id = $insert_id;

            $order = $GLOBALS['order'];

            $items = "<ul>\n";
            foreach ($order->products as $product) {
                $items .= "<li>" . $product['qty'] . 'x ' . $product['name'] . "</li>\n";
            }
            $items .= "</ul>\n";

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
                    "amount" => $currencies->rateAdjusted($order->info['total'])*100,
                    "gateway" => $this->gateway,
                    "description" => "Order #" . $this->order_id . " " . MODULE_PAYMENT_MULTISAFEPAY_TEXT_AT . " " . STORE_NAME,
                    "items" => $items,
                    "manual" => false,
                    "days_active" => MODULE_PAYMENT_MULTISAFEPAY_DAYS_ACTIVE,
                    "payment_options" => array(
                        "notification_url" => zen_href_link('ext/modules/payment/multisafepay/notify_checkout.php?type=initial&', '', 'NONSSL', false, false, true),
                        "redirect_url" => $this->redirect_url,
                        "cancel_url" => $this->get_cancel_url(),
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
                switch ($this->getErrorCode($e->getMessage())) {
                    case '1024':
                        $msg = MODULE_PAYMENT_MULTISAFEPAY_TEXT_ERROR_1024;
                        break;
                    default:
                        $msg = $e->getMessage();
                        break;
                }

                $this->_error_redirect(htmlspecialchars($msg));
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
            global $currencies;

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
                    "unit_price" => $currencies->rateAdjusted($price),
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
                    "unit_price" => $currencies->rateAdjusted($order->info['shipping_cost']),
                    "quantity" => 1,
                    "merchant_item_id" => 'msp-shipping',
                    "tax_table_selector" => 'Shipping',
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
                        "unit_price" => $currencies->rateAdjusted(-$GLOBALS['ot_coupon']->deduction),
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


            $taxPercentage = 0;
            if ($order->info['shipping_cost'] != '0.00') {
                $taxPercentage = $order->info['shipping_tax'] / $order->info['shipping_cost'];
            }
            $checkoutoptions_array['tax_tables']['alternate'][] = array(
                "standalone" => false,
                "name" => 'Shipping',
                "rules" => [
                    [
                    "rate" => $taxPercentage
                    ]
                ]
            );

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
         * Split the address into street and house number with extension.
         *
         * @param string $address1
         * @param string $address2
         * @return array
         */
        public function parseAddress($address1, $address2 = '')
        {
            // Trim the addresses
            $address1 = trim($address1);
            $address2 = trim($address2);
            $fullAddress = trim("{$address1} {$address2}");
            $fullAddress = preg_replace("/[[:blank:]]+/", ' ', $fullAddress);

            // Make array of all regex matches
            $matches = [];

            /**
             * Regex part one: Add all before number.
             * If number contains whitespace, Add it also to street.
             * All after that will be added to apartment
             */
            $pattern = '/(.+?)\s?([\d]+[\S]*)(\s?[A-z]*?)$/';
            preg_match($pattern, $fullAddress, $matches);

            //Save the street and apartment and trim the result
            $street = isset($matches[1]) ? $matches[1] : '';
            $apartment = isset($matches[2]) ? $matches[2] : '';
            $extension = isset($matches[3]) ? $matches[3] : '';
            $street = trim($street);
            $apartment = trim($apartment . $extension);

            return [$street, $apartment];
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
         * Checks whether all the payment options are available in the database as indication that
         * the payment method is installed correct through the admin panel
         *
         * @return bool
         */

        public function check()
        {
            global $db;
            if (!isset($this->_check)) {
                // If exists, remove old configuration options
                if (method_exists($this, 'oldKeys')) {
                    $this->updateConfig();
                }

                $keys = $this->keys();
                $sql = "SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = :key:";
                $sql = $db->bindVars($sql, ':key:', $keys[0], 'string');

                $result = $db->Execute($sql);

                $this->_check = $result->RecordCount() !== 0;
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

            if (MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED == 'True') {
                $title = $this->generateIcon($this->getIcon()) . " " . $this->getRealTitle($title);
            }
            return $title;
        }

        /**
         * @return string|type
         */
        public function getDescription()
        {
            return (sprintf(
                "<strong>%s v%s</strong><br>%s<br>",
                $this->title,
                $this->plugin_ver,
                'The main MultiSafepay module must be installed (does not have to be active) to use this payment method.'
            ));
        }


        /**
         * @return string
         */
        public function getRealTitle($title)
        {
            // Santander needs short description if icons are enabled
            if (stripos($title, 'Santander') !== false) {
                return MODULE_PAYMENT_MSP_SANTANDER_SHORT_TEXT_TITLE;
            }
            return $title;
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
         * call setTransactionStatusToShipped if orderstatus is set to Delivered (3)
         *
         * @param $order_id
         * @param $status
         * @param $comments
         * @param $customer_notified
         * @param $check_status
         * @return bool
         */
        public function _doStatusUpdate($order_id, $status, $comments, $customer_notified, $check_status)
        {
            if ($status === 3) {
                $this->setTransactionStatusToShipped($order_id, $comments);
            }
            return true;
        }

        /**
         * @param $order_id
         * @param $comments
         */
        private function setTransactionStatusToShipped($order_id, $comments)
        {
            try {
                $msp = new MultiSafepayAPI\Client();
                $api_url = $this->get_api_url();

                $msp->setApiUrl($api_url);
                $msp->setApiKey($this->get_api_key());

                $endpoint = 'orders/' . $order_id;
                $setShipping = array(
                    'tracktrace_code' => $comments,
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
         * @return string
         */
        protected function get_cancel_url()
        {
            $sid = zen_session_name() . '=' . zen_session_id();
            return zen_href_link('ext/modules/payment/multisafepay/cancel.php?' . $sid, '', 'NONSSL', false, false, true);
        }

        /**
         * @return string|null
         */
        protected function get_redirect_url()
        {
            $sid = zen_session_name() . '=' . zen_session_id();

            if (MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT) {
                return zen_href_link('ext/modules/payment/multisafepay/success.php?' . $sid, '', 'NONSSL', false, false, true);
            } else {
                return null;
            }
        }

        /**
         * @param $error
         * @return false|string
         */
        public function getErrorCode($error)
        {
            return substr($error, 0, 4);
        }
    }
}
