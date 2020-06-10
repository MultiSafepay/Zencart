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

class multisafepay_fastcheckout extends MultiSafepay
{

    var $code;
    var $title;
    var $description;
    var $enabled;
    var $sort_order;
    var $plugin_ver = "3.0.0";
    var $api_url;
    var $order_id;
    var $status;
    var $order_status;
    var $shipping_methods = array();
    var $taxes = array();
    var $msp;
    var $_customer_id = 0;

    /*
     * Constructor
     */

    function __construct($order_id = -1)
    {
        global $order;

        $this->code = 'multisafepay_fastcheckout';
        $this->title = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_FCO_TEXT_TITLE);
        $this->description = $this->getDescription();

        $this->enabled = MODULE_PAYMENT_MULTISAFEPAY_FCO_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MULTISAFEPAY_FCO_SORT_ORDER;
        $this->order_status = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED;

        if (is_object($order)) {
            $this->update_status();
        }

        if (MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live' || MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live account') {
            $this->api_url = 'https://api.multisafepay.com/v1/json/';
        } else {
            $this->api_url = 'https://testapi.multisafepay.com/v1/json/';
        }

        $this->order_id = $order_id;
        $this->status = null;
        $this->enabled = false; //Disabled for normal checkout
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
     * 
     * @param type $icon
     * @return type
     */
    function generateIcon($icon)
    {
        return zen_image($icon);
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
     * This is a copy from process.php
     * 
     * @global type $shipping_weight
     * @global type $shipping_num_boxes
     * @global type $db
     * @global type $total_count
     * @global type $language
     * @global type $currencies
     * @param type $weight
     * @return type
     */
    function get_shipping_methods($weight)
    {
        global $shipping_weight, $shipping_num_boxes, $db, $total_count;

        if (!empty($GLOBALS['_SESSION']['language'])) {
            require_once('includes/languages/' . $GLOBALS['_SESSION']['language'] . '/modules/payment/multisafepay_fastcheckout.php');
        }

        $total_weight = $weight;
        if (isset($_GET['items_count'])) {
            $total_count = $_GET['items_count'];
        } else {
            $total_count = 1;
        }

        $shipping_num_boxes = 1;
        $shipping_weight = $total_weight;

        if (SHIPPING_BOX_WEIGHT >= $shipping_weight * SHIPPING_BOX_PADDING / 100) {
            $shipping_weight = $shipping_weight + SHIPPING_BOX_WEIGHT;
        } else {
            $shipping_weight = $shipping_weight + ($shipping_weight * SHIPPING_BOX_PADDING / 100);
        }

        if ($shipping_weight > SHIPPING_MAX_WEIGHT) {
            $shipping_num_boxes = ceil($shipping_weight / SHIPPING_MAX_WEIGHT);
            $shipping_weight = $shipping_weight / $shipping_num_boxes;
        }

        $tax_class = array();
        $shipping_arr = array();
        $tax_class_unique = array();

        /*
         * Load shipping modules
         */
        $module_directory = dirname(dirname(__FILE__)) . '/' . 'shipping/';
        if (!file_exists($module_directory)) {
            echo 'Error: ' . $module_directory;
        }

        //Find module files
        $file_extension = substr(__FILE__, strrpos(__FILE__, '.'));
        $directory_array = array();
        if ($dir = @ dir($module_directory)) {
            while ($file = $dir->read()) {
                if (!is_dir($module_directory . $file)) {
                    if (substr($file, strrpos($file, '.')) == $file_extension) {
                        $directory_array[] = $file;
                    }
                }
            }
            sort($directory_array);
            $dir->close();
        }

        $check_query = $db->Execute("select countries_iso_code_2 from " . TABLE_COUNTRIES . " where countries_id = '" . SHIPPING_ORIGIN_COUNTRY . "'");

        $shipping_origin_iso_code_2 = $check_query->fields['countries_iso_code_2'];

        // load modules
        $module_info = array();
        $module_info_enabled = array();
        $shipping_modules = array();
        for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++) {
            $file = $directory_array[$i];

            global $language;

            include_once (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/shipping/' . $file);
            include_once ($module_directory . $file);

            $class = substr($file, 0, strrpos($file, '.'));
            $module = new $class;
            $curr_ship = strtoupper($module->code);

            switch ($curr_ship) {
                case 'FEDEXGROUND':
                    $curr_ship = 'FEDEX_GROUND';
                    break;
                case 'FEDEXEXPRESS':
                    $curr_ship = 'FEDEX_EXPRESS';
                    break;
                case 'UPSXML':
                    $curr_ship = 'UPSXML_RATES';
                    break;
                case 'DHLAIRBORNE':
                    $curr_ship = 'AIRBORNE';
                    break;
                default:
                    break;
            }


            if (@constant('MODULE_SHIPPING_' . $curr_ship . '_STATUS') == 'True') {
                $module_info_enabled[$module->code] = array('enabled' => true);
            }
            if ($module->check() == true) {
                $module_info[$module->code] = array(
                    'code' => $module->code,
                    'title' => $module->title,
                    'description' => $module->description,
                    'status' => $module->check());
            }

            if (!empty($module_info_enabled[$module->code]['enabled'])) {
                $shipping_modules[$module->code] = $module;
            }
        }

        /*
         * Get shipping prices
         */
        $shipping_methods = array();

        foreach ($module_info as $key => $value) {
            // check if active
            $module_name = $module_info[$key]['code'];

            if (!$module_info_enabled[$module_name]) {
                continue;
            }

            $curr_ship = strtoupper($module_name);

            // calculate price
            $module = $shipping_modules[$module_name];
            $quote = $module->quote($method);

            $price = $quote['methods'][0]['cost'];

            global $currencies;
            $shipping_price = $currencies->get_value(DEFAULT_CURRENCY) * ($price >= 0 ? $price : 0);

            $common_string = "MODULE_SHIPPING_" . $curr_ship . "_";
            @$zone = constant($common_string . "ZONE");
            @$enable = constant($common_string . "STATUS");
            @$curr_tax_class = constant($common_string . "TAX_CLASS");
            @$price = constant($common_string . "COST");
            @$handling = constant($common_string . "HANDLING");
            @$table_mode = constant($common_string . "MODE");

            // allowed countries - zones	
            if ($zone != '') {
                $zone_result = $db->Execute("select countries_name, coalesce(zone_code, 'All Areas') zone_code, countries_iso_code_2
                                            from " . TABLE_GEO_ZONES . " as gz " .
                        " inner join " . TABLE_ZONES_TO_GEO_ZONES . " as ztgz on gz.geo_zone_id = ztgz.geo_zone_id " .
                        " inner join " . TABLE_COUNTRIES . " as c on ztgz.zone_country_id = c.countries_id " .
                        " left join " . TABLE_ZONES . " as z on ztgz.zone_id=z.zone_id
                                            where gz.geo_zone_id= '" . $zone . "'");
                while (!$zone_result->EOF) {
                    $allowed_restriction_state[] = $zone_result->fields['zone_code'];
                    $allowed_restriction_country[] = array($zone_result->fields['countries_name'], $zone_result->fields['countries_iso_code_2']);
                    $zone_result->MoveNext();
                }
            }

            if ($curr_tax_class != 0 && $curr_tax_class != '') {
                $tax_class[] = $curr_tax_class;

                if (!in_array($curr_tax_class, $tax_class_unique)) {
                    $tax_class_unique[] = $curr_tax_class;
                }
            }


            if (empty($quote['error']) && $quote['id'] != 'zones') {
                foreach ($quote['methods'] as $method) {
                    $shipping_methods[] = array
                        (
                        'id' => $quote['id'],
                        'module' => $quote['module'],
                        'title' => $quote['methods'][0]['title'],
                        'price' => $shipping_price,
                        'allowed' => $allowed_restriction_country,
                        'tax_class' => $curr_tax_class,
                        'zone' => $zone,
                        'code' => $module_name
                    );
                }
            } elseif ($quote['id'] == 'zones') {
                for ($cur_zone = 1; $cur_zone <= $module->num_zones; $cur_zone++) {
                    $countries_table = constant('MODULE_SHIPPING_ZONES_COUNTRIES_' . $cur_zone);
                    $country_zones = split("[,]", $countries_table);

                    if (count($country_zones) > 1 || !empty($country_zones[0])) {
                        $shipping = -1;
                        $zones_cost = constant('MODULE_SHIPPING_ZONES_COST_' . $cur_zone);

                        $zones_table = split("[:,]", $zones_cost);
                        $size = sizeof($zones_table);
                        for ($i = 0; $i < $size; $i+=2) {
                            if ($shipping_weight <= $zones_table[$i]) {
                                $shipping = $zones_table[$i + 1];
                                $shipping_method = $shipping_weight . ' ' . MODULE_SHIPPING_ZONES_TEXT_UNITS;
                                break;
                            }
                        }

                        if ($shipping == -1) {
                            $shipping_cost = 0;
                            $shipping_method = MODULE_SHIPPING_ZONES_UNDEFINED_RATE;
                        } else {
                            $shipping_cost = ($shipping * $shipping_num_boxes) + constant('MODULE_SHIPPING_ZONES_HANDLING_' . $cur_zone);

                            $shipping_methods[] = array
                                (
                                'id' => $quote['id'],
                                'module' => $quote['module'],
                                'title' => $shipping_method,
                                'price' => $shipping_cost,
                                'allowed' => $country_zones,
                            );
                        }
                    }
                }
            }
        }
        return $shipping_methods;
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

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_MULTISAFEPAY_FCO_ZONE > 0)) {
            $check_flag = false;
            $check_query = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_MULTISAFEPAY_FCO_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
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

    /**
     * Any checks or processing on the order information before proceeding to
     * 
     * payment confirmation
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
        return false;
    }

    function before_process()
    {
        $GLOBALS['order']->info['payment_method'] = trim(strip_tags($GLOBALS['order']->info['payment_method']));
    }

    function after_process()
    {
        zen_redirect($this->_start_fastcheckout());
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
     * @return boolean
     */
    function isNewAddressQuery()
    {
        $country = $_GET['country'];
        $countryCode = $_GET['countrycode'];
        $transactionId = $_GET['transactionid'];

        if ($_GET['country'] == '') {
            $country = $_GET['countrycode'];
        }

        if (empty($country) || empty($countryCode) || empty($transactionId)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Handles new shipping costs request
     */
    function handleShippingMethodsNotification()
    {
        $country = $_GET['country'];
        $countryCode = $_GET['countrycode'];
        $transactionId = $_GET['transactionid'];
        $weight = $_GET['weight'];
        $size = $_GET['size'];

        header("Content-Type:text/xml");
        print($this->getShippingMethodsFilteredXML($country, $countryCode, $weight, $size, $transactionId));
    }

    /**
     * Returns XML with new shipping costs
     * 
     * @param type $country
     * @param type $countryCode
     * @param type $weight
     * @param type $size
     * @param type $transactionId
     * @return string
     */
    function getShippingMethodsFilteredXML($country, $countryCode, $weight, $size, $transactionId)
    {
        $outxml = '<?xml version="1.0" encoding="UTF-8"?><shipping-info>';
        $methods = $this->getShippingMethodsFiltered($country, $countryCode, $weight, $size, $transactionId);

        foreach ($methods as $method) {
            $outxml .= '<shipping>';
            $outxml .= '<shipping-name>';
            $outxml .= $method['name'];
            $outxml .= '</shipping-name>';
            $outxml .= '<shipping-cost currency="' . $method['currency'] . '">';
            $outxml .= $method['cost'];
            $outxml .= '</shipping-cost>';
            $outxml .= '</shipping>';
        }

        $outxml .= '</shipping-info>';
        return $outxml;
    }

    /**
     * Get shipping methods for given parameters
     * Result as an array:
     * 'name' => 'test-name'
     * 'cost' => '123'
     * 'currency' => 'EUR' (currently only this supported) 
     * 
     * @param type $country
     * @param type $countryCode
     * @param type $weight
     * @param type $size
     * @param type $transactionId
     * @return type
     */
    function getShippingMethodsFiltered($country, $countryCode, $weight, $size, $transactionId)
    {

        $out = array();
        $shipping_methods = $this->get_shipping_methods($weight);

        foreach ($shipping_methods as $shipping_method) {
            $shipping = array();
            $shipping['name'] = $shipping_method['module'] . ' - ' . $shipping_method['title'];
            $shipping['cost'] = $shipping_method['price'];
            $shipping['currency'] = $GLOBALS['order']->info['currency'];
            $out[] = $shipping;
        }

        return $out;
    }

    /**
     * 
     * @return type
     */
    function _start_fastcheckout()
    {
        global $insert_id;
        $this->order_id = $insert_id;

        $items = "<ul>\n";
        foreach ($GLOBALS['order']->products as $product) {
            $items .= "<li>" . $product['qty'] . 'x ' . $product['name'] . "</li>\n";
        }
        $items .= "</ul>\n";

        $amount = round($GLOBALS['order']->info['total'], 2) * 100;

        if (MODULE_PAYMENT_MULTISAFEPAY_FCO_AUTO_REDIRECT == "True") {
            $redirect_url = $this->_href_link('ext/modules/payment/multisafepay/success.php', '', 'NONSSL', false, false);
        } else {
            $redirect_url = null;
        }

        if (is_null($GLOBALS['order']->customer['firstname'])) {
            $data = 'delivery';
        } else {
            $data = 'customer';
        }

        list($cust_street, $cust_housenumber) = $this->parseAddress($GLOBALS['order']->$data['street_address']);

        try {
            $msp = new MultiSafepayAPI\Client();

            if (MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live' || MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live account') {
                $this->api_url = 'https://api.multisafepay.com/v1/json/';
            } else {
                $this->api_url = 'https://testapi.multisafepay.com/v1/json/';
            }

            $msp->setApiUrl($this->api_url);
            $msp->setApiKey(MODULE_PAYMENT_MULTISAFEPAY_FCO_API_KEY);

            $msp->orders->post(array(
                "type" => "checkout",
                "gateway" => null,
                "order_id" => $this->order_id,
                "currency" => $GLOBALS['order']->info['currency'],
                "amount" => $amount,
                "description" => 'Order #' . $this->order_id . ' at ' . STORE_NAME,
                "var1" => $GLOBALS['customer_id'],
                "var2" => null,
                "var3" => null,
                "items" => $items,
                "manual" => false,
                "payment_options" => array(
                    "notification_url" => $this->_href_link('ext/modules/payment/multisafepay/notify_checkout.php?type=initial', '', 'NONSSL', false, false),
                    "cancel_url" => $this->_href_link('ext/modules/payment/multisafepay/cancel.php', '', 'NONSSL', false, false),
                    "redirect_url" => $redirect_url,
                    "close_window" => null
                ),
                "customer" => array(
                    "locale" => strtolower($GLOBALS['order']->$data['country']['iso_code_2']) . '_' . $GLOBALS['order']->$data['country']['iso_code_2'],
                    "ip_address" => $_SERVER['REMOTE_ADDR'],
                    "forwarded_ip" => $_SERVER['HTTP_X_FORWARDED_FOR'],
                    "first_name" => $GLOBALS['order']->$data['firstname'],
                    "last_name" => $GLOBALS['order']->$data['lastname'],
                    "address1" => $cust_street,
                    "address2" => null,
                    "house_number" => $cust_housenumber,
                    "zip_code" => $GLOBALS['order']->$data['postcode'],
                    "city" => $GLOBALS['order']->$data['city'],
                    "state" => null,
                    "country" => $GLOBALS['order']->$data['country']['iso_code_2'],
                    "phone" => $GLOBALS['order']->$data['telephone'],
                    "email" => $GLOBALS['order']->$data['email_address'],
                    "disable_send_email" => false,
                    "user_agent" => $_SERVER['HTTP_USER_AGENT'],
                    "referrer" => $_SERVER['HTTP_REFERER']
                ),
                "delivery" => array(
                    "first_name" => $GLOBALS['order']->$data['firstname'],
                    "last_name" => $GLOBALS['order']->$data['lastname'],
                    "address1" => $cust_street,
                    "address2" => null,
                    "house_number" => $cust_housenumber,
                    "zip_code" => $GLOBALS['order']->$data['postcode'],
                    "city" => $GLOBALS['order']->$data['city'],
                    "state" => null,
                    "country" => $GLOBALS['order']->$data['country']['iso_code_2']
                ),
                "gateway_info" => array(
                    "birthday" => null,
                    "bank_account" => null,
                    "phone" => null,
                    "email" => null,
                    "referrer" => null,
                    "user_agent" => null
                ),
                "shopping_cart" => $this->getShoppingCart(),
                "checkout_options" => $this->getCheckoutOptions(),
                "google_analytics" => array(
                    "account" => MODULE_PAYMENT_MULTISAFEPAY_FCO_GA_ACCOUNT,
                ),
                "plugin" => array(
                    "shop" => PROJECT_VERSION_NAME,
                    "shop_version" => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR,
                    "plugin_version" => $this->plugin_ver,
                    "partner" => 'MultiSafepay',
                    "shop_root_url" => $_SERVER['SERVER_NAME']
                )
            ));

            return $msp->orders->getPaymentLink();
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

        return $shoppingcart_array;
    }

    /**
     * 
     * @return array
     */
    function getCheckoutOptions()
    {
        $checkoutoptions_array = array();

        $checkoutoptions_array['rounding_policy'] = array
            (
            "mode" => null,
            "rule" => null
        );

        //Retrieve shipping methods from ZenCart, if shipping Notification URL disabled in config.

        /* if (MODULE_PAYMENT_MULTISAFEPAY_FCO_USE_SNU == 'False') {
          foreach ($this->shipping_methods as $shippingmethod)
          {
          if ($shippingmethod['id'] == 'spu') {
          $checkoutoptions_array['shipping_methods']['pickup'][] = array
          (
          "name" => $shippingmethod['module'] . ' - ' . $shippingmethod['title'],
          "price" => $shippingmethod['price'],
          "allowed_areas" => $shippingmethod['allowed']
          );
          } else {
          $checkoutoptions_array['shipping_methods']['flat_rate_shipping'][] = array
          (
          "name" => $shippingmethod['module'] . ' - ' . $shippingmethod['title'],
          "price" => $shippingmethod['price'],
          "allowed_areas" => $shippingmethod['allowed']
          );
          var_dump(json_encode($shippingmethod, JSON_PRETTY_PRINT));exit;
          }
          }
          } else { */
        $checkoutoptions_array['use_shipping_notification'] = true;
        //}

        $checkoutoptions_array['tax_tables']['default'] = array
            (
            "shipping_taxed" => null,
            "rate" => null
        );

        $checkoutoptions_array['tax_tables']['alternate'][] = array
            (
            "name" => key($this->taxes['alternate']),
            "rules" => array(array
                    (
                    "rate" => $this->taxes['alternate'][key($this->taxes['alternate'])],
                    "country" => null
                ))
        );

        return $checkoutoptions_array;
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
     * @global type $currencies
     * @global type $db
     * @global type $order
     * @global type $currency
     * @return type
     */
    function checkout_notify()
    {
        global $currencies, $db, $order, $currency;

        $this->order_id = $_GET['transactionid'];

        include(DIR_WS_LANGUAGES . $_SESSION['language'] . "/checkout_process.php");

        if ($this->isNewAddressQuery()) {
            $this->handleShippingMethodsNotification();
            exit(0);
        }

        try {
            $msp = new MultiSafepayAPI\Client();

            if (MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live' || MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER == 'Live account') {
                $api_url = 'https://api.multisafepay.com/v1/json/';
            } else {
                $api_url = 'https://testapi.multisafepay.com/v1/json/';
            }

            $msp->setApiUrl($api_url);
            $msp->setApiKey(MODULE_PAYMENT_MULTISAFEPAY_FCO_API_KEY);

            $response = $msp->orders->get('orders', $this->order_id);
            $status = $response->status;
            $pspid = $response->transaction_id;
        } catch (Exception $e) {
            return htmlspecialchars($e->getMessage());
        }

        if (!$response->var1) {
            $customer_id = $this->get_customer($response);
        } else {
            $customer_id = $response->var1;
        }

        $this->_customer_id = $customer_id;
        $_SESSION['customer_id'] = $customer_id;

        $customer_country = $this->get_country_from_code($response->customer->country);
        $delivery_country = $this->get_country_from_code($response->delivery->country);

        $shippers = $this->get_shipping_methods(0);
        $shipper = explode('-', $response->order_adjustment->shipping->flat_rate_shipping->name);

        foreach ($shippers as $key => $value) {
            if ($value['module'] == trim($shipper[0])) {
                $shipping_module_code = $value['code'];
            }
        }

        $sql_data_array = array
            (
            'customers_name' => $response->customer->first_name . ' ' . $response->customer->last_name,
            'customers_company' => '',
            'customers_street_address' => $response->customer->address1 . ' ' . $response->customer->house_number,
            'customers_suburb' => '',
            'customers_city' => $response->customer->city,
            'customers_postcode' => $response->customer->zip_code,
            'customers_state' => $response->customer->state,
            'customers_country' => $customer_country->fields['countries_name'],
            'customers_telephone' => $response->customer->phone1,
            'customers_email_address' => $response->customer->email,
            'customers_address_format_id' => '1',
            'delivery_name' => $response->delivery->first_name . ' ' . $response->delivery->last_name,
            'delivery_company' => '',
            'delivery_street_address' => $response->delivery->address1 . ' ' . $response->delivery->house_number,
            'delivery_suburb' => '',
            'delivery_city' => $response->delivery->city,
            'delivery_postcode' => $response->delivery->zip_code,
            'delivery_state' => $response->delivery->state,
            'delivery_country' => $delivery_country->fields['countries_name'],
            'delivery_address_format_id' => '1',
            'billing_name' => $response->customer->first_name . ' ' . $response->customer->last_name,
            'billing_company' => '',
            'billing_street_address' => $response->customer->address1 . ' ' . $response->customer->house_number,
            'billing_suburb' => '',
            'billing_city' => $response->customer->city,
            'billing_postcode' => $response->customer->zip_code,
            'billing_state' => $response->customer->state,
            'billing_country' => $customer_country->fields['countries_name'],
            'billing_address_format_id' => '1',
            'payment_method' => 'MultiSafepay FastCheckout (' . $response->payment_details->type . ')',
            'payment_module_code' => 'multisafepay_fastcheckout',
            'order_tax' => $response->order_adjustment->total_tax,
            'order_total' => $response->order_total,
            'shipping_method' => $response->order_adjustment->shipping->flat_rate_shipping->name,
            'shipping_module_code' => $shipping_module_code
        );

        $order->customer['firstname'] = $response->customer->first_name;
        $order->customer['lastname'] = $response->customer->last_name;

        if ($customer_id) {
            $sql_data_array['customers_id'] = $customer_id;
        }

        $query = "UPDATE " . TABLE_ORDERS . " SET ";
        foreach ($sql_data_array as $key => $val) {
            $query .= $key . " = '" . $val . "',";
        }
        $query = substr($query, 0, -1);
        $query .= " WHERE orders_id = '" . $this->order_id . "'";
        $db->Execute($query);

        //Update or add order total
        $value = $response->order_total;
        $text = '<b>' . $currencies->format($value, false, $GLOBALS['order']->info['currency'], $currencies->currencies[$currency]['value']) . '</b>';
        $query = "UPDATE " . TABLE_ORDERS_TOTAL . " SET value = '" . $value . "', text = '" . $text . "' WHERE class = 'ot_total' AND orders_id = '" . $this->order_id . "'";
        $db->Execute($query);

        //Update or add tax
        $value = $response->order_adjustment->total_tax;
        $text = $currencies->format($value, false, $GLOBALS['order']->info['currency'], $currencies->currencies[$currency]['value']);
        $query = "UPDATE " . TABLE_ORDERS_TOTAL . " SET value = '" . $value . "', text = '" . $text . "' WHERE class = 'ot_tax' AND orders_id = '" . $this->order_id . "'";
        $db->Execute($query);

        //Update or add shipping
        $check_shipping = $db->Execute("SELECT count(1) as count FROM " . TABLE_ORDERS_TOTAL . " WHERE class = 'ot_shipping' AND orders_id = '" . $this->order_id . "'");
        $check_shipping = $check_shipping->fields['count'];
        $value = $response->order_adjustment->shipping->flat_rate_shipping->cost;
        $title = $response->order_adjustment->shipping->flat_rate_shipping->name;
        $text = $currencies->format($value, false, $GLOBALS['order']->info['currency'], $currencies->currencies[$currency]['value']);

        if ($check_shipping) {
            $query = "UPDATE " . TABLE_ORDERS_TOTAL . " SET title = '" . $title . "', value = '" . $value . "', text = '" . $text . "' WHERE class = 'ot_shipping' AND orders_id = '" . $this->order_id . "'";
            $db->Execute($query);
        } else {
            $query = "INSERT INTO " . TABLE_ORDERS_TOTAL . "(orders_id, title, text, value, class, sort_order) VALUES ('" . $this->order_id . "','" . $title . "','" . $text . "','" . $value . "','ot_shipping','2')";
            $db->Execute($query);
        }

        //Get the current order status for the concerning order
        $current_order = $db->Execute("SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . $this->order_id);
        $old_order_status = $current_order->fields['orders_status'];

        //Determine new ZenCart order status
        $reset_cart = false;
        $notify_customer = false;
        $new_order_status = null;

        switch ($status) {
            case "initialized":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED;
                $reset_cart = true;
                break;
            case "completed":
                if (in_array($old_order_status, array(MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED, DEFAULT_ORDERS_STATUS_ID, MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_UNCLEARED))) {
                    $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_COMPLETED;
                    $reset_cart = true;
                    $notify_customer = true;
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_COMPLETED;
                } else {
                    $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_COMPLETED;
                }
                break;
            case "uncleared":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_UNCLEARED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_UNCLEARED;
                break;
            case "reserved":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_RESERVED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_RESERVED;
                break;
            case "void":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID) {
                    $order_query = $db->Execute("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("UPDATE " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "cancelled":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID;

                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID) {
                    $order_query = $db->Execute("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("UPDATE " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "declined":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_DECLINED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_DECLINED;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_DECLINED) {
                    $order_query = $db->Execute("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("UPDATE " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            case "reversed":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REVERSED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REVERSED;
                break;
            case "refunded":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REFUNDED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REFUNDED;
                break;
            case "partial_refunded":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_PARTIAL_REFUNDED;
                break;
            case "expired":
                $GLOBALS['order']->info['order_status'] = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_EXPIRED;
                $new_stat = MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_EXPIRED;
                if ($old_order_status != MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_EXPIRED) {
                    $order_query = $db->Execute("SELECT products_id, products_quantity from " . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . $this->order_id . "'");

                    if ($order_query->RecordCount() > 0) {
                        while (!$order_query->EOF) {
                            $order_fields = $order_query;
                            $db->Execute("UPDATE " . TABLE_PRODUCTS . " set products_quantity = products_quantity + " . $order_fields->fields['products_quantity'] . ", products_ordered = products_ordered - " . $order_fields->fields['products_quantity'] . " where products_id = '" . (int) $order_fields->fields['products_id'] . "'");
                            $order_query->MoveNext();
                        }
                    }
                }
                break;
            default:
                $GLOBALS['order']->info['order_status'] = DEFAULT_ORDERS_STATUS_ID;
        }

        $order_status_query = $db->Execute("SELECT orders_status_name FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_id = '" . $GLOBALS['order']->info['order_status'] . "' AND language_id = '" . $GLOBALS['languages_id'] . "'");
        $order_status = $order_status_query;

        if ($new_stat == 0){
            $new_stat = DEFAULT_ORDERS_STATUS_ID;
        }

        $GLOBALS['order']->info['orders_status'] = $order_status->fields['orders_status_name'];

        if ($old_order_status != $new_stat) {
            $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . $new_stat . " WHERE orders_id = " . $this->order_id);
        }

        if ($notify_customer) {
            $order->send_order_email($this->order_id, 2);
            unset($_SESSION['customer_id']);
            unset($_SESSION['billto']);
            unset($_SESSION['sendto']);
        }

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

        if ($reset_cart) {
            $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");
            $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET_ATTRIBUTES . " WHERE customers_id = '" . (int) $GLOBALS['order']->customer['id'] . "'");
        }

        return $status;
    }

    /**
     * 
     * @global type $db
     * @param type $details
     * @return \type
     */
    function get_customer($details)
    {
        global $db;

        $email = $details->customer->email;
        $customer_exists = $db->Execute("SELECT customers_id FROM " . TABLE_CUSTOMERS . " WHERE customers_email_address = '" . $email . "'");

        $new_user = false;
        if ($customer_exists->fields['customers_id'] != '') {
            $customer_id = $customer_exists->fields['customers_id'];
        } else {
            $sql_data_array = array
                (
                'customers_firstname' => $details->customer->first_name,
                'customers_lastname' => $details->customer->last_name,
                'customers_email_address' => $details->customer->email,
                'customers_telephone' => $details->customer->phone1,
                'customers_fax' => '',
                'customers_default_address_id' => 0,
                'customers_password' => zen_encrypt_password('test123'),
                'customers_newsletter' => 0
            );

            if (ACCOUNT_DOB == 'true') {
                $sql_data_array['customers_dob'] = 'now()';
            }

            zen_db_perform(TABLE_CUSTOMERS, $sql_data_array);
            $customer_id = $db->Insert_ID();

            $db->Execute("insert into " . TABLE_CUSTOMERS_INFO . " (customers_info_id, customers_info_number_of_logons, customers_info_date_account_created) values ('" . (int) $customer_id . "', '0', now())");
            $new_user = true;
        }


        $address_book = $db->Execute("select address_book_id, entry_country_id, entry_zone_id from " . TABLE_ADDRESS_BOOK . "
                                         where  customers_id = '" . $customer_id . "'
                                         and entry_street_address = '" . $details->customer->address1 . ' ' . $details->customer->house_number . "'
                                         and entry_suburb = '" . '' . "'
                                         and entry_postcode = '" . $details->customer->zipcode . "'
                                         and entry_city = '" . $details->customer->city . "'");

        if (!$address_book->RecordCount()) {
            $country = $this->get_country_from_code($details->customer->country);
            $sql_data_array = array(
                'customers_id' => $customer_id,
                'entry_gender' => '',
                'entry_company' => '',
                'entry_firstname' => $details->customer->first_name,
                'entry_lastname' => $details->customer->last_name,
                'entry_street_address' => $details->customer->address1 . ' ' . $details->customer->house_number,
                'entry_suburb' => '',
                'entry_postcode' => $details->customer->zip_code,
                'entry_city' => $details->customer->city,
                'entry_state' => '',
                'entry_country_id' => $country->fields['countries_id'],
                'entry_zone_id' => ''
            );

            zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);

            $address_id = $db->Insert_ID();
            $_SESSION['billto'] = $address_id;
        } else {
            $_SESSION['billto'] = $address_book->fields['address_book_id'];
        }

        $address_book = $db->Execute("select address_book_id, entry_country_id, entry_zone_id from " . TABLE_ADDRESS_BOOK . "
                                        where  customers_id = '" . $customer_id . "'
                                        and entry_street_address = '" . $details->delivery->address1 . ' ' . $details->delivery->house_number . "'
                                        and entry_suburb = '" . '' . "'
                                        and entry_postcode = '" . $details->delivery->zip_code . "'
                                        and entry_city = '" . $details->delivery->city . "'");

        if (!$address_book->RecordCount()) {
            $country = $this->get_country_from_code($details->delivery->country);
            $sql_data_array = array
                (
                'customers_id' => $customer_id,
                'entry_gender' => '',
                'entry_company' => '',
                'entry_firstname' => $details->delivery->first_name,
                'entry_lastname' => $details->delivery->last_name,
                'entry_street_address' => $details->delivery->address1 . ' ' . $details->delivery->house_number,
                'entry_suburb' => '',
                'entry_postcode' => $details->delivery->zip_code,
                'entry_city' => $details->delivery->city,
                'entry_state' => '',
                'entry_country_id' => $country->fields['countries_id'],
                'entry_zone_id' => ''
            );

            zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);
            $address_id = $db->Insert_ID();
            $_SESSION['sendto'] = $address_id;
        } else {
            $_SESSION['sendto'] = $address_book->fields['address_book_id'];
        }

        return $customer_id;
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

        $messageStack->add_session('shopping_cart', $error, 'error');
        zen_redirect('../index.php?main_page=' . FILENAME_SHOPPING_CART);
    }

    /**
     * Store the order in the database, and set $this->order_id
     * 
     * @global type $customer_id
     * @global type $languages_id
     * @global type $order
     * @global type $order_totals
     * @global type $order_products_id
     * @global type $db
     * @return type
     */
    function _save_order()
    {
        global $customer_id;
        global $languages_id;
        global $order;
        global $order_totals;
        global $order_products_id;
        global $db;

        if (empty($order_totals)) {
            require(DIR_WS_CLASSES . 'order_total.php');
            $order_total_modules = new order_total();
            $order_totals = $order_total_modules->process();
        }

        if (!empty($this->order_id) && $this->order_id > 0) {
            return;
        }

        $sql_data_array = array(
            'customers_id' => $customer_id,
            'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
            'customers_company' => $order->customer['company'],
            'customers_street_address' => $order->customer['street_address'],
            'customers_suburb' => $order->customer['suburb'],
            'customers_city' => $order->customer['city'],
            'customers_postcode' => $order->customer['postcode'],
            'customers_state' => $order->customer['state'],
            'customers_country' => $order->customer['country']['title'],
            'customers_telephone' => $order->customer['telephone'],
            'customers_email_address' => $order->customer['email_address'],
            'customers_address_format_id' => $order->customer['format_id'],
            'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
            'delivery_company' => $order->delivery['company'],
            'delivery_street_address' => $order->delivery['street_address'],
            'delivery_suburb' => $order->delivery['suburb'],
            'delivery_city' => $order->delivery['city'],
            'delivery_postcode' => $order->delivery['postcode'],
            'delivery_state' => $order->delivery['state'],
            'delivery_country' => $order->delivery['country']['title'],
            'delivery_address_format_id' => $order->delivery['format_id'],
            'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
            'billing_company' => $order->billing['company'],
            'billing_street_address' => $order->billing['street_address'],
            'billing_suburb' => $order->billing['suburb'],
            'billing_city' => $order->billing['city'],
            'billing_postcode' => $order->billing['postcode'],
            'billing_state' => $order->billing['state'],
            'billing_country' => $order->billing['country']['title'],
            'billing_address_format_id' => $order->billing['format_id'],
            'payment_method' => $order->info['payment_method'],
            'cc_type' => $order->info['cc_type'],
            'cc_owner' => $order->info['cc_owner'],
            'cc_number' => $order->info['cc_number'],
            'cc_expires' => $order->info['cc_expires'],
            'date_purchased' => 'now()',
            'orders_status' => MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED,
            'currency' => $order->info['currency'],
            'currency_value' => $order->info['currency_value'],
            'payment_method' => 'MultiSafepay FastCheckout',
            'payment_module_code' => 'multisafepay_fastcheckout'
        );

        zen_db_perform(TABLE_ORDERS, $sql_data_array);
        $insert_id = $db->Insert_ID();

        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            $sql_data_array = array
                (
                'orders_id' => $insert_id,
                'title' => $order_totals[$i]['title'],
                'text' => $order_totals[$i]['text'],
                'value' => $order_totals[$i]['value'],
                'class' => $order_totals[$i]['code'],
                'sort_order' => $order_totals[$i]['sort_order']
            );

            zen_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
        }

        $sql_data_array = array
            (
            'orders_id' => $insert_id,
            'orders_status_id' => $order->info['order_status'],
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => $order->info['comments'],
            'updated_by' => 'MultiSafepay'
        );

        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $custom_insertable_text = '';
            if (STOCK_LIMITED == 'true') {
                if (DOWNLOAD_ENABLED == 'true') {
                    $stock_query_raw = "select p.products_quantity, pad.products_attributes_filename, p.product_is_always_free_shipping
                                        from " . TABLE_PRODUCTS . " p
                                        left join " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                         on p.products_id=pa.products_id
                                        left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                         on pa.products_attributes_id=pad.products_attributes_id
                                        WHERE p.products_id = '" . zen_get_prid($order->products[$i]['id']) . "'";

                    $products_attributes = $order->products[$i]['attributes'];
                    if (is_array($products_attributes)) {
                        $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                    }
                    $stock_values = $db->Execute($stock_query_raw);
                } else {
                    $stock_values = $db->Execute("select * from " . TABLE_PRODUCTS . " where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");
                }

                if ($stock_values->RecordCount() > 0) {
                    if ((DOWNLOAD_ENABLED != 'true') || $stock_values->fields['product_is_always_free_shipping'] == 2 || (!$stock_values->fields['products_attributes_filename'])) {
                        $stock_left = $stock_values->fields['products_quantity'] - $order->products[$i]['qty'];
                        $this->products[$i]['stock_reduce'] = $order->products[$i]['qty'];
                    } else {
                        $stock_left = $stock_values->fields['products_quantity'];
                    }

                    $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");

                    if ($stock_left <= 0) {
                        if (SHOW_PRODUCTS_SOLD_OUT == '0') {
                            $db->Execute("update " . TABLE_PRODUCTS . " set products_status = 0 where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");
                        }
                    }
                }
            }

            //Update products_ordered (for bestsellers list)
            $db->Execute("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");

            $sql_data_array = array
                (
                'orders_id' => $insert_id,
                'products_id' => zen_get_prid($order->products[$i]['id']),
                'products_model' => $order->products[$i]['model'],
                'products_name' => $order->products[$i]['name'],
                'products_price' => $order->products[$i]['price'],
                'final_price' => $order->products[$i]['final_price'],
                'products_tax' => $order->products[$i]['tax'],
                'products_quantity' => $order->products[$i]['qty']
            );
            zen_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
            $order_products_id = $db->Insert_ID();

            $attributes_exist = '0';

            if (isset($order->products[$i]['attributes'])) {
                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    if (DOWNLOAD_ENABLED == 'true') {
                        $attributes_query = "select popt.products_options_name, poval.products_options_values_name,
                                 pa.options_values_price, pa.price_prefix,
                                 pa.product_attribute_is_free, pa.products_attributes_weight, pa.products_attributes_weight_prefix,
                                 pa.attributes_discounted, pa.attributes_price_base_included, pa.attributes_price_onetime,
                                 pa.attributes_price_factor, pa.attributes_price_factor_offset,
                                 pa.attributes_price_factor_onetime, pa.attributes_price_factor_onetime_offset,
                                 pa.attributes_qty_prices, pa.attributes_qty_prices_onetime,
                                 pa.attributes_price_words, pa.attributes_price_words_free,
                                 pa.attributes_price_letters, pa.attributes_price_letters_free,
                                 pad.products_attributes_maxdays, pad.products_attributes_maxcount, pad.products_attributes_filename
                                 from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " .
                                TABLE_PRODUCTS_ATTRIBUTES . " pa
                                  left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                  on pa.products_attributes_id=pad.products_attributes_id
                                 where pa.products_id = '" . zen_db_input($order->products[$i]['id']) . "'
                                  and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                                  and pa.options_id = popt.products_options_id
                                  and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                                  and pa.options_values_id = poval.products_options_values_id
                                  and popt.language_id = '" . $_SESSION['languages_id'] . "'
                                  and poval.language_id = '" . $_SESSION['languages_id'] . "'";

                        $attributes_values = $db->Execute($attributes_query);
                    } else {
                        $attributes_values = $db->Execute("select popt.products_options_name, poval.products_options_values_name,
                                 pa.options_values_price, pa.price_prefix,
                                 pa.product_attribute_is_free, pa.products_attributes_weight, pa.products_attributes_weight_prefix,
                                 pa.attributes_discounted, pa.attributes_price_base_included, pa.attributes_price_onetime,
                                 pa.attributes_price_factor, pa.attributes_price_factor_offset,
                                 pa.attributes_price_factor_onetime, pa.attributes_price_factor_onetime_offset,
                                 pa.attributes_qty_prices, pa.attributes_qty_prices_onetime,
                                 pa.attributes_price_words, pa.attributes_price_words_free,
                                 pa.attributes_price_letters, pa.attributes_price_letters_free
                                 from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                                 where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $_SESSION['languages_id'] . "' and poval.language_id = '" . $_SESSION['languages_id'] . "'");
                    }

                    $sql_data_array = array(
                        'orders_id' => $insert_id,
                        'orders_products_id' => $order_products_id,
                        'products_options' => $attributes_values->fields['products_options_name'],
                        'products_options_values' => $order->products[$i]['attributes'][$j]['value'],
                        'options_values_price' => $attributes_values->fields['options_values_price'],
                        'price_prefix' => $attributes_values->fields['price_prefix'],
                        'product_attribute_is_free' => $attributes_values->fields['product_attribute_is_free'],
                        'products_attributes_weight' => $attributes_values->fields['products_attributes_weight'],
                        'products_attributes_weight_prefix' => $attributes_values->fields['products_attributes_weight_prefix'],
                        'attributes_discounted' => $attributes_values->fields['attributes_discounted'],
                        'attributes_price_base_included' => $attributes_values->fields['attributes_price_base_included'],
                        'attributes_price_onetime' => $attributes_values->fields['attributes_price_onetime'],
                        'attributes_price_factor' => $attributes_values->fields['attributes_price_factor'],
                        'attributes_price_factor_offset' => $attributes_values->fields['attributes_price_factor_offset'],
                        'attributes_price_factor_onetime' => $attributes_values->fields['attributes_price_factor_onetime'],
                        'attributes_price_factor_onetime_offset' => $attributes_values->fields['attributes_price_factor_onetime_offset'],
                        'attributes_qty_prices' => $attributes_values->fields['attributes_qty_prices'],
                        'attributes_qty_prices_onetime' => $attributes_values->fields['attributes_qty_prices_onetime'],
                        'attributes_price_words' => $attributes_values->fields['attributes_price_words'],
                        'attributes_price_words_free' => $attributes_values->fields['attributes_price_words_free'],
                        'attributes_price_letters' => $attributes_values->fields['attributes_price_letters'],
                        'attributes_price_letters_free' => $attributes_values->fields['attributes_price_letters_free'],
                        'products_options_id' => (int) $order->products[$i]['attributes'][$j]['option_id'],
                        'products_options_values_id' => (int) $order->products[$i]['attributes'][$j]['value_id'],
                        'products_prid' => $order->products[$i]['id']
                    );

                    zen_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

                    if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values->fields['products_attributes_filename']) && zen_not_null($attributes_values->fields['products_attributes_filename'])) {
                        $sql_data_array = array(
                            'orders_id' => $insert_id,
                            'orders_products_id' => $order_products_id,
                            'orders_products_filename' => $attributes_values->fields['products_attributes_filename'],
                            'download_maxdays' => $attributes_values->fields['products_attributes_maxdays'],
                            'download_count' => $attributes_values->fields['products_attributes_maxcount'],
                            'products_prid' => $order->products[$i]['id']
                        );

                        zen_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                    }

                    $this->products_ordered_attributes .= "\n\t" . $attributes_values->fields['products_options_name'] . ' ' . zen_decode_specialchars($this->products[$i]['attributes'][$j]['value']);
                }
            }
        }

        $this->order_id = $insert_id;
    }

    /**
     * 
     * @global type $db
     * @param type $address_format_id
     * @param string $address
     * @param type $html
     * @param type $boln
     * @param type $eoln
     * @return string
     */
    function _address_format($address_format_id, $address, $html, $boln, $eoln)
    {
        global $db;

        $address_format_query = $db->Execute("SELECT address_format AS format FROM " . TABLE_ADDRESS_FORMAT . " WHERE address_format_id = '" . (int) $address_format_id . "'");
        $address_format = $address_format_query;

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
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_FCO_STATUS'");
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

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('MultiSafepay enabled', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_STATUS', 'True', 'Enable MultiSafepay payments for this website', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Account type', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER', 'Live account', '<a href=\'https://www.multisafepay.com/signup/\' target=\'_blank\' style=\'text-decoration: underline; font-weight: bold; color:#696916; \'>Click here to signup for an account.</a>', '6', '21', 'zen_cfg_select_option(array(\'Live account\', \'Test account\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('API Key', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_API_KEY', '', 'Your MultiSafepay API Key', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Automatically redirect the customer back to the webshop.', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_AUTO_REDIRECT', 'True', 'Enable auto redirect after payment', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Google Analytics', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_GA_ACCOUNT', '', 'Google Analytics Account ID', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Days Active.', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_DAYS_ACTIVE', '', 'Allow MultiSafepay to attempt to close unpaid orders after X number of days.', '6', '22', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Payment Zone', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '25', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('FastCheckout button color', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_BTN_COLOR', 'Orange', 'Select the color of the FastCheckout button.', '6', '0', 'zen_cfg_select_option(array(\'Orange\', \'Black\'), ', now())");
        //$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Use Shipping Notification URL', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_USE_SNU', 'False', 'Use the Notification URL for the retrieval of shipping methods.', '6', '20', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Initialized Order Status', 'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED', 0, 'In progress', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Completed Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_COMPLETED',   0, 'Completed successfully', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Uncleared Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_UNCLEARED',   0, 'Not yet cleared', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reserved Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_RESERVED',    0, 'Reserved', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Voided Order Status',      'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID',        0, 'Cancelled', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Declined Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_DECLINED',    0, 'Declined (e.g. fraud, not enough balance)', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reversed Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REVERSED',    0, 'Undone', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Refunded Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REFUNDED',    0, '(Partially) refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Expired Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_EXPIRED',     0, 'Expired', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
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
        return array
            (
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_STATUS',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_API_SERVER',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_API_KEY',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_AUTO_REDIRECT',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_GA_ACCOUNT',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_DAYS_ACTIVE',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ZONE',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_SORT_ORDER',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_BTN_COLOR',
            //'MODULE_PAYMENT_MULTISAFEPAY_FCO_USE_SNU',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_INITIALIZED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_COMPLETED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_UNCLEARED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_RESERVED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_VOID',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_DECLINED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REVERSED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_REFUNDED',
            'MODULE_PAYMENT_MULTISAFEPAY_FCO_ORDER_STATUS_ID_EXPIRED'
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

    /**
     *
     * @param type $string
     * @return type
     */
    function _output_string_protected($string)
    {
        return zen_output_string($string, false, true);
    }
}

?>
