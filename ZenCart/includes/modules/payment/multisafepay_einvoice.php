<?php

$dir = dirname(dirname(dirname(dirname(__FILE__))));

require_once($dir . "/mspcheckout/API/Autoloader.php");

class multisafepay_einvoice {

    var $code;
    var $title;
    var $description;
    var $enabled;
    var $sort_order;
    var $plugin_name;
    var $icon = "einvoice.png";
    var $api_url;
    var $order_id;
    var $public_title;
    var $status;
    var $shipping_methods = array();
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
        $this->description = $this->getTitle(MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_TITLE);
        $this->enabled = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_STATUS == 'True';
        $this->sort_order = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_SORT_ORDER;
        $this->plugin_name = 'ZenCart 3.0.0';
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
     * @param type $admin
     * @return type
     */
    function getTitle($admin = 'title')
    {
        $title = ($this->checkView() == "frontend") ? $this->generateIcon($this->getIcon()) . " " : "";

        $title .= ($this->checkView() == "admin") ? "MultiSafepay " : "";
        if ($admin && $this->checkView() == "admin") {
            $title .= $admin;
        } else {

            $title .= $this->getLangStr($admin);
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
    function getIcon()
    {
        $icon = DIR_WS_IMAGES . "multisafepay/en/" . $this->icon;

        if (file_exists(DIR_WS_IMAGES . "multisafepay/" . strtolower($this->getUserLanguage("DETECT")) . "/" . $this->icon)) {
            $icon = DIR_WS_IMAGES . "multisafepay/" . strtolower($this->getUserLanguage("DETECT")) . "/" . $this->icon;
            return $icon;
        }
    }

    /**
     * 
     * @global type $db
     * @global type $languages_id
     * @param type $savedSetting
     * @return string
     */
    function getUserLanguage($savedSetting)
    {
        global $db;
        if ($savedSetting != "DETECT") {
            return $savedSetting;
        }

        global $languages_id;

        $query = $db->Execute("select languages_id, name, code, image, directory from " . TABLE_LANGUAGES . " where languages_id = " . (int) $languages_id . " limit 1");

        if ($languages == $query) {//changed loop
            return strtolower($languages['code']);
        }

        return "en";
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

    // This is a copy from process.php	
    // This is a copy from process.php	
    function get_shipping_methods($weight)
    {
        global $shipping_weight, $shipping_num_boxes, $db, $total_count;




        //require_once("includes/application_top.php");
        if (!empty($GLOBALS['_SESSION']['language'])) {
            require_once('includes/languages/' . $GLOBALS['_SESSION']['language'] . '/modules/payment/multisafepay_fastcheckout.php');
        }
        //require_once(DIR_WS_CLASSES . 'order.php');
        //require_once(DIR_WS_CLASSES . 'shipping.php');

        $total_weight = $weight;
        $total_count = 1;

        // from shipping.php:
        $shipping_num_boxes = 1;
        $shipping_weight = $total_weight;

        if (SHIPPING_BOX_WEIGHT >= $shipping_weight * SHIPPING_BOX_PADDING / 100) {
            $shipping_weight = $shipping_weight + SHIPPING_BOX_WEIGHT;
        } else {
            $shipping_weight = $shipping_weight + ($shipping_weight * SHIPPING_BOX_PADDING / 100);
        }

        if ($shipping_weight > SHIPPING_MAX_WEIGHT) { // Split into many boxes
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

        // find module files
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

        $check_query = $db->Execute("select countries_iso_code_2
                             from " . TABLE_COUNTRIES . "
                             where countries_id =
                             '" . SHIPPING_ORIGIN_COUNTRY . "'");


        $shipping_origin_iso_code_2 = $check_query->fields['countries_iso_code_2'];

        // load modules
        $module_info = array();
        $module_info_enabled = array();
        $shipping_modules = array();
        for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++)
        {
            $file = $directory_array[$i];
            global $language;
            include_once (DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/shipping/' . $file);
            include_once ($module_directory . $file);

            $class = substr($file, 0, strrpos($file, '.'));
            $module = new $class;
            $curr_ship = strtoupper($module->code);

            switch ($curr_ship)
            {
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


        foreach ($module_info as $key => $value)
        {
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

            // need this?
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
                // i get all the alowed shipping zones
                while (!$zone_result->EOF) {
                    $allowed_restriction_state[] = $zone_result->fields['zone_code'];
                    $allowed_restriction_country[] = array($zone_result->fields['countries_name'],
                        $zone_result->fields['countries_iso_code_2']);
                    $zone_result->MoveNext();
                }
            }

            if ($curr_tax_class != 0 && $curr_tax_class != '') {
                $tax_class[] = $curr_tax_class;

                if (!in_array($curr_tax_class, $tax_class_unique))
                    $tax_class_unique[] = $curr_tax_class;
            }


            if (empty($quote['error']) && $quote['id'] != 'zones') {
                foreach ($quote['methods'] as $method)
                {
                    $shipping_methods[] = array(
                        'id' => $quote['id'],
                        'module' => $quote['module'],
                        'title' => $quote['methods'][0]['title'],
                        'price' => $shipping_price,
                        'allowed' => $allowed_restriction_country,
                        'tax_class' => $curr_tax_class,
                        'zone' => $zone,
                    );
                }
            } elseif ($quote['id'] == 'zones') {
                for ($cur_zone = 1; $cur_zone <= $module->num_zones; $cur_zone++)
                {
                    $countries_table = constant('MODULE_SHIPPING_ZONES_COUNTRIES_' . $cur_zone);
                    $country_zones = split("[,]", $countries_table);

                    if (count($country_zones) > 1 || !empty($country_zones[0])) {
                        $shipping = -1;
                        $zones_cost = constant('MODULE_SHIPPING_ZONES_COST_' . $cur_zone);

                        $zones_table = split("[:,]", $zones_cost);
                        $size = sizeof($zones_table);
                        for ($i = 0; $i < $size; $i+=2)
                        {
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

                            $shipping_methods[] = array(
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

    /*
     * Check whether this payment module is available
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

    // ---- select payment module ----

    /*
     * Client side javascript that will verify any input fields you use in the
     * payment method selection page
     */
    function javascript_validation()
    {
        return false;
    }

    /*
     * Outputs the payment method title/text and if required, the input fields
     */

    function selection()
    {
        global $customer_id;
        global $languages_id;
        global $order;
        global $order_totals;
        global $order_products_id;

        // check if transaction is possible
        if (empty($this->api_url)) {
            return;
        }

        return array(
            'id' => $this->code,
            'module' => $this->public_title
        );
    }

    /*
     * Any checks of any conditions after payment method has been selected
     */

    function pre_confirmation_check()
    {
        return false;
    }

    // ---- confirm order ----

    /*
     * Any checks or processing on the order information before proceeding to
     * payment confirmation
     */
    function confirmation()
    {
        return false;
    }

    /*
     * Outputs the html form hidden elements sent as POST data to the payment
     * gateway
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

        $output = '<div class="idealbox" style="padding:20px;border:1px solid #d50172; margin-top:20px;text-align:center">';
        $output .= '<label>Bankaccount: </label><input type="text" name="pad_bankaccount"style="width:200px; padding: 0px; margin-left: 7px;"><br/>';
        $output .= '<label>Birthday: </label><input type="text" name="pad_birthday" placeholder="DD-MM-YYYY" style="width:200px; padding: 0px; margin-left: 35px;">';
        $output .= '<div style="clear:both;"></div></div><br/>';

        return ($output);
    }

    // ---- process payment ----

    /*
     * Payment verification
     */
    function before_process()
    {
        $this->_save_order();

        zen_redirect($this->_start_einvoice());
    }

    /*
     * Post-processing of the payment/order after the order has been finalised
     */

    function after_process()
    {
        return false;
    }

    // ---- error handling ----

    /*
     * Advanced error handling
     */
    function output_error()
    {
        return false;
    }

    /**
     * 
     * @return type
     */
    function get_error()
    {
        $error = array(
            'title' => MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_ERROR,
            'error' => $this->_get_error_message($_GET['error'])
        );

        return $error;
    }

    // ---- MultiSafepay ----
    function isNewAddressQuery()
    {
        // Check for mandatory parameters
        $country = $_GET['country'];
        $countryCode = $_GET['countrycode'];
        $transactionId = $_GET['transactionid'];

        if (empty($country) || empty($countryCode) || empty($transactionId))
            return false;
        else
            return true;
    }

    // Handles new shipping costs request
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

    // Returns XML with new shipping costs
    function getShippingMethodsFilteredXML($country, $countryCode, $weight, $size, $transactionId)
    {
        $outxml = '<shipping-info>';
        $methods = $this->getShippingMethodsFiltered($country, $countryCode, $weight, $size, $transactionId);

        foreach ($methods as $method)
        {
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

    // Get shipping methods for given parameters
    // Result as an array:
    // 'name' => 'test-name'
    // 'cost' => '123'
    // 'currency' => 'EUR' (currently only this supported)

    function getShippingMethodsFiltered($country, $countryCode, $weight, $size, $transactionId)
    {
        $out = array();
        $shipping_methods = $this->get_shipping_methods($weight);

        foreach ($shipping_methods as $shipping_method)
        {
            // ISO codes match - add to output list
            $shipping = array();
            $shipping['name'] = $shipping_method['module'] . ' - ' . $shipping_method['title'];
            $shipping['cost'] = $shipping_method['price'];
            $shipping['currency'] = $GLOBALS['order']->info['currency']; // Currently Euro is supported
            $out[] = $shipping;
        }

        return $out;
    }

    /**
     * 
     * @return type
     */
    function _start_einvoice()
    {
        $items_list = "<ul>\n";
        foreach ($GLOBALS['order']->products as $product)
        {
            $items_list .= "<li>" . $product['name'] . "</li>\n";
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

        if ($_POST['pad_bankaccount'] == "" || $_POST['pad_birthday'] == "") {
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
                    "birthday" => $_POST['pad_birthday'],
                    "bank_account" => $_POST['pad_bankaccount'],
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
                    "plugin_version" => 'ZenCart 3.0.0',
                    "partner" => 'MultiSafepay',
                    "shop_root_url" => $_SERVER['SERVER_NAME']
                )
            ));

            return $msp->orders->getPaymentLink();
        } catch (Exception $e) {
            echo '(MultiSafepay) ' . htmlspecialchars($e->getMessage());
            exit;
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

        foreach ($order->products as $product)
        {
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

        foreach ($GLOBALS['order']->products as $product)
        {
            $price = $product['price'];
            if (isset($product['final_price'])) {
                $price = $product['final_price'];
            }

            $attributeString = '';
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attribute)
                {
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
        foreach ($haystack as $item)
        {
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

    function checkout_notify($manual_status = '')
    {
        global $db, $order, $currencies;
        $this->msp = new MultiSafepayAPI();
        $this->msp->plugin_name = $this->plugin_name;
        $this->msp->test = (MODULE_PAYMENT_MULTISAFEPAY_API_SERVER != 'Live' && MODULE_PAYMENT_MULTISAFEPAY_API_SERVER != 'Live account');
        $this->msp->merchant['account_id'] = MODULE_PAYMENT_MULTISAFEPAY_ACCOUNT_ID;
        $this->msp->merchant['site_id'] = MODULE_PAYMENT_MULTISAFEPAY_SITE_ID;
        $this->msp->merchant['site_code'] = MODULE_PAYMENT_MULTISAFEPAY_SITE_SECURE_CODE;
        $this->msp->transaction['id'] = $this->order_id;
        $status = $this->msp->getStatus();


        $order->customer['firstname'] = $this->msp->details['customer']['firstname'];
        $order->customer['lastname'] = $this->msp->details['customer']['lastname'];
        $_SESSION['customer_id'] = $this->msp->details['transaction']['var1'];
        $_SESSION['billto'] = $this->msp->details['transaction']['var2'];
        $_SESSION['sendto'] = $this->msp->details['transaction']['var3'];

        include(DIR_WS_LANGUAGES . $_SESSION['language'] . "/checkout_process.php");

        if ($this->msp->error) {
            return $this->msp->error_code;
        }

        // determine status
        $reset_cart = false;
        $notify_customer = false;

        $current_order = $db->Execute("SELECT orders_status FROM " . TABLE_ORDERS . " WHERE orders_id = " . $this->order_id);

        if ($manual_status != '') {
            $status = $manual_status;
        }



        $old_order_status = $current_order->fields['orders_status'];

        switch ($status)
        {
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

        $GLOBALS['order']->info['orders_status'] = $order_status->fields['orders_status_name'];



        if ($old_order_status != $new_stat) {
            // update order
            $db->Execute("UPDATE " . TABLE_ORDERS . " SET orders_status = " . $new_stat . " WHERE orders_id = " . $this->order_id);
        }



        $order->products_ordered = '';

        foreach ($order->products as $product)
        {


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
            $sql_data_array = array('orders_id' => $this->order_id,
                'orders_status_id' => $GLOBALS['order']->info['order_status'],
                'date_added' => 'now()',
                'customer_notified' => 0,
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

    function get_customer($details)
    {
        global $db;
        $email = $details['customer']['email'];
        //    Check if the email exists
        $customer_exists = $db->Execute("select customers_id from " .
                TABLE_CUSTOMERS . " where customers_email_address = '" . $email . "'");



        $new_user = false;
        if ($customer_exists->fields['customers_id'] != '') {
            $customer_id = $customer_exists->fields['customers_id'];
            //zen_session_register('customer_id');
        } else {
            $sql_data_array = array(
                'customers_firstname' => $details['customer']['firstname'],
                'customers_lastname' => $details['customer']['lastname'],
                'customers_email_address' => $details['customer']['email'],
                'customers_telephone' => $details['customer']['phone1'],
                'customers_fax' => '',
                'customers_default_address_id' => 0,
                'customers_password' => zen_encrypt_password('test123'),
                'customers_newsletter' => 1
            );

            if (ACCOUNT_DOB == 'true') {
                $sql_data_array['customers_dob'] = 'now()';
            }
            zen_db_perform(TABLE_CUSTOMERS, $sql_data_array);
            $customer_id = $db->Insert_ID();
            //zen_session_register('customer_id');
            $db->Execute("insert into " . TABLE_CUSTOMERS_INFO . "
                                    (customers_info_id, customers_info_number_of_logons,
                                     customers_info_date_account_created)
                               values ('" . (int) $customer_id . "', '0', now())");

            $new_user = true;
        }


        //      The user exists and is logged in
        //      Check database to see if the address exist.
        $address_book = $db->Execute("select address_book_id, entry_country_id, entry_zone_id from " . TABLE_ADDRESS_BOOK . "
										where  customers_id = '" . $customer_id . "'
										and entry_street_address = '" . $details['customer']['address1'] . ' ' . $details['customer']['housenumber'] . "'
										and entry_suburb = '" . '' . "'
										and entry_postcode = '" . $details['customer']['zipcode'] . "'
										and entry_city = '" . $details['customer']['city'] . "'
									");



        //      If not, add the addr as default one
        if (!$address_book->RecordCount()) {

            $country = $this->get_country_from_code($details['customer']['country']);


            $sql_data_array = array(
                'customers_id' => $customer_id,
                'entry_gender' => '',
                'entry_company' => '',
                'entry_firstname' => $details['customer']['firstname'],
                'entry_lastname' => $details['customer']['lastname'],
                'entry_street_address' => $details['customer']['address1'] . ' ' . $details['customer']['housenumber'],
                'entry_suburb' => '',
                'entry_postcode' => $details['customer']['zipcode'],
                'entry_city' => $details['customer']['city'],
                'entry_state' => '',
                'entry_country_id' => $country->fields['countries_id'],
                'entry_zone_id' => ''
            );


            zen_db_perform(TABLE_ADDRESS_BOOK, $sql_data_array);


            $address_id = $db->Insert_ID();




            $db->Execute("update " . TABLE_CUSTOMERS . "
                                set customers_default_address_id = '" . (int) $address_id . "'
                                where customers_id = '" . (int) $customer_id . "'");
            $customer_default_address_id = $address_id;
            $customer_country_id = $country['countries_id'];
            //$customer_zone_id = $zone_answer['zone_id'];
        } else {


            $customer_default_address_id = $address_book->fields['address_book_id'];
            $customer_country_id = $address_book->fields['entry_country_id'];
            $customer_zone_id = $address_book->fields['entry_zone_id'];
        }

        $customer_first_name = $details['customer']['firstname'];
        //zen_session_register('customer_default_address_id');
        //zen_session_register('customer_country_id');
        //zen_session_register('customer_zone_id');
        //zen_session_register('customer_first_name');

        return $customer_id;
        //  Customer exists, is logged and address book is up to date
    }

    function get_country_from_code($code)
    {
        global $db;
        //countries_iso_code_2
        $country = $db->Execute("select * from " . TABLE_COUNTRIES . " where countries_iso_code_2 = '" . $code . "'");


        return $country;
    }

    function get_hash($order_id, $customer_id)
    {
        return md5($order_id . $customer_id);
    }

    function _get_error_message($code)
    {
        if (is_numeric($code)) {
            $message = constant(sprintf("MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_ERROR_%04d", $code));

            if (!$message) {
                $message = MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_ERROR_UNKNOWN;
            }
        } else {
            $const = sprintf("MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_TEXT_ERROR_%s", strtoupper($code));

            if (defined($const)) {
                $message = constant($const);
            } else {
                $message = $code;
            }
        }
        return $message;
    }

    function _error_redirect($error)
    {
        zen_redirect($this->_href_link(
                        FILENAME_SHOPPING_CART, 'payment_error=' . $this->code . '&error=' . $error, 'NONSSL', true, false, false
        ));
    }

    // ---- Ripped from checkout_process.php ----

    /*
     * Store the order in the database, and set $this->order_id
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

        if (MODULE_PAYMENT_MULTISAFEPAY_FCO_DISPLAY_CHECKOUT_ORDERS == 'False') {
            $order->info['order_status'] = 0;
        }

        $sql_data_array = array('customers_id' => $customer_id,
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
            'payment_method' => 'MultiSafepay Einvoice',
            'payment_module_code' => $order->info['payment_module_code'],
            'coupon_code' => $order->info['coupon_code'],
            'cc_type' => $order->info['cc_type'],
            'cc_owner' => $order->info['cc_owner'],
            'cc_number' => $order->info['cc_number'],
            'cc_expires' => $order->info['cc_expires'],
            'date_purchased' => 'now()',
            'orders_status' => $order->info['order_status'],
            'shipping_module_code' => $order->info['shipping_module_code'],
            'shipping_method' => $order->info['shipping_method'],
            //'orders_status' 					=> 	MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED,
            'currency' => $GLOBALS['order']->info['currency'],
            'currency_value' => $order->info['currency_value'],
            'order_total' => $order->info['total'],
            'order_tax' => $order->info['tax'],
            'ip_address' => $_SESSION['customers_ip_address'] . ' - ' . $_SERVER['REMOTE_ADDR']
        );

        zen_db_perform(TABLE_ORDERS, $sql_data_array);
        $insert_id = $db->Insert_ID();
        $zf_insert_id = $insert_id;

        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++)
        {
            $sql_data_array = array('orders_id' => $insert_id,
                'title' => $order_totals[$i]['title'],
                'text' => $order_totals[$i]['text'],
                'value' => $order_totals[$i]['value'],
                'class' => $order_totals[$i]['code'],
                'sort_order' => $order_totals[$i]['sort_order']);
            zen_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
        }

        $sql_data_array = array('orders_id' => $insert_id,
            'orders_status_id' => $order->info['order_status'],
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => $order->info['comments']);
        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++)
        {
            // Stock Update - Joao Correia
            if (STOCK_LIMITED == 'true') {
                if (DOWNLOAD_ENABLED == 'true') {
                    $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename
											FROM " . TABLE_PRODUCTS . " p
											LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
											 ON p.products_id=pa.products_id
											LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
											 ON pa.products_attributes_id=pad.products_attributes_id
											WHERE p.products_id = '" . zen_get_prid($order->products[$i]['id']) . "'";
                    // Will work with only one option for downloadable products
                    // otherwise, we have to build the query dynamically with a loop
                    $products_attributes = $order->products[$i]['attributes'];
                    if (is_array($products_attributes)) {
                        $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
                    }
                    $stock_query = $db->Execute($stock_query_raw);
                } else {
                    $stock_query = $db->Execute("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");
                }
                if ($stock_query->RecordCount() > 0) {
                    $stock_values = $stock_query; //zen_db_fetch_array($stock_query);
                    // do not decrement quantities if products_attributes_filename exists
                    if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values->products_attributes_filename)) {
                        $stock_left = $stock_values->fields['products_quantity'] - $order->products[$i]['qty'];
                    } else {
                        $stock_left = $stock_values->fields['products_quantity'];
                    }
                    $db->Execute("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");
                    if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                        $db->Execute("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");
                    }
                }
            }

            // Update products_ordered (for bestsellers list)
            $db->Execute("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . zen_get_prid($order->products[$i]['id']) . "'");



            $sql_data_array = array('orders_id' => $zf_insert_id,
                'products_id' => zen_get_prid($order->products[$i]['id']),
                'products_model' => $order->products[$i]['model'],
                'products_name' => $order->products[$i]['name'],
                'products_price' => $order->products[$i]['price'],
                'final_price' => $order->products[$i]['final_price'],
                'onetime_charges' => $order->products[$i]['onetime_charges'],
                'products_tax' => $order->products[$i]['tax'],
                'products_quantity' => $order->products[$i]['qty'],
                'products_priced_by_attribute' => $order->products[$i]['products_priced_by_attribute'],
                'product_is_free' => $order->products[$i]['product_is_free'],
                'products_discount_type' => $order->products[$i]['products_discount_type'],
                'products_discount_type_from' => $order->products[$i]['products_discount_type_from'],
                'products_prid' => $order->products[$i]['id']);




            zen_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

            $order_products_id = $db->Insert_ID();

            // $this->notify('NOTIFY_ORDER_DURING_CREATE_ADDED_PRODUCT_LINE_ITEM', array_merge(array('orders_products_id' => $order_products_id), $sql_data_array));
            // $this->notify('NOTIFY_ORDER_PROCESSING_CREDIT_ACCOUNT_UPDATE_BEGIN');
            // $order_total_modules->update_credit_account($i);//ICW ADDED FOR CREDIT CLASS SYSTEM
            // $this->notify('NOTIFY_ORDER_PROCESSING_ATTRIBUTES_BEGIN');
            //------ bof: insert customer-chosen options to order--------
            $attributes_exist = '0';
            //$order->products_ordered_attributes = '';
            if (isset($order->products[$i]['attributes'])) {
                $attributes_exist = '1';
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++)
                {
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



                    //clr 030714 update insert query.  changing to use values form $order->products for products_options_values.
                    $sql_data_array = array('orders_id' => $zf_insert_id,
                        'orders_products_id' => $order_products_id,
                        'products_options' => $attributes_values->fields['products_options_name'],
                        //                                 'products_options_values' => $attributes_values->fields['products_options_values_name'],
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

                    //  $this->notify('NOTIFY_ORDER_DURING_CREATE_ADDED_ATTRIBUTE_LINE_ITEM', $sql_data_array);

                    if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values->fields['products_attributes_filename']) && zen_not_null($attributes_values->fields['products_attributes_filename'])) {
                        $sql_data_array = array('orders_id' => $zf_insert_id,
                            'orders_products_id' => $order_products_id,
                            'orders_products_filename' => $attributes_values->fields['products_attributes_filename'],
                            'download_maxdays' => $attributes_values->fields['products_attributes_maxdays'],
                            'download_count' => $attributes_values->fields['products_attributes_maxcount'],
                            'products_prid' => $order->products[$i]['id']
                        );

                        zen_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);

                        // $this->notify('NOTIFY_ORDER_DURING_CREATE_ADDED_ATTRIBUTE_DOWNLOAD_LINE_ITEM', $sql_data_array);
                    }
                    $this->products_ordered_attributes .= "\n\t" . $attributes_values->fields['products_options_name'] . ' ' . zen_decode_specialchars($this->products[$i]['attributes'][$j]['value']);
                }
            }
            //------eof: insert customer-chosen options ----
        }

        $this->order_id = $insert_id;
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

    function _output_string_protected($string)
    {
        return $this->_output_string($string, false, true);
    }

    function _parse_input_field_data($data, $parse)
    {
        return strtr(trim($data), $parse);
    }

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

        while ((substr($link, -1) == '&') || (substr($link, -1) == '?'))
            $link = substr($link, 0, -1);




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
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Minimum transaction amount', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MIN_AMOUNT', '', 'Minimum amount for E-Invoice in cents', '6', '23', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Maximum transaction amount', 'MODULE_PAYMENT_MULTISAFEPAY_EINVOICE_MAX_AMOUNT', '', 'Maximum amount for E-Invoice in cents', '6', '23', now())");
    }

    /*
     * Removes the configuration keys from the database
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
        switch ($lang)
        {
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