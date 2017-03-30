<?php

/**
 *  Qwindo Shop Feed for ZenCart
 */

chdir("../../../../");

require("includes/application_top.php");
require("qwindo_functions.php");

$q  =   new Qwindo();

echo $q->switchIdentifier($_GET['identifier']);


/**
 * Qwindo code overview
 * 
 * 1. Categories
 * 2. Products
 * 3. Stock
 * 4. Shipping methods
 * 5. Store info
 */


/**
 * Qwindo Feed: 1. getCategories
 * 
 * @global type $db
 * @global Qwindo $q
 * @return type
 */

function getCategories()
{
    global $db;
    global $q;

    $categories_array   =   array();
 
    $category_query     =   "SELECT * FROM " . TABLE_CATEGORIES
                        .   " AS cat INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION
                        .   " AS cd on cat.parent_id=cd.categories_id WHERE cat.categories_status='1'";
    $category_result    =   $db->Execute($category_query);
    
    if($category_result->RecordCount() > 0)
    {
        while(!$category_result->EOF)
        {
            $category_ids[]    =   $category_result->fields['categories_id'];
            $category_result->MoveNext();
        }
    }
    
    $category_ids   =   array_unique($category_ids);
    
    foreach ($category_ids as $cid)
    {
        $query = "SELECT * FROM " . TABLE_CATEGORIES
                . " AS cat INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION
                . " AS cd on cat.categories_id=cd.categories_id WHERE cat.parent_id={$cid}";

        $result = $db->Execute($query);

        if ($result->RecordCount() > 0) {
            while (!$result->EOF) {
                //$parent = zen_get_categories_parent_name($result->fields['categories_id']);
                $result->MoveNext();
            }
        }
        
        $data   =   zen_get_categories('', $cid, '', '1');
        $children   =   array();
        
        foreach($data as $d)
        {
            $children[]   =   array(
                "id"    =>  $d['id'],
                "title" =>  array($q->getLocale($result->fields['language_id'])  =>  $d['text'])
            );
        }
        
        $categories_array[] = array(
            "id" => $cid,
            "title" => array(
                $q->getLocale($result->fields['language_id']) => zen_get_categories_parent_name($result->fields['categories_id'])
            ),
            "children" => $children //zen_get_categories('', $cid, '', '1')
            /*
            "children" => array(
                "id"    => $result->fields['categories_id'],
                "title" =>  array(
                    $q->getLocale($result->fields['language_id']) =>  $data['text']
                )
            )*/
        );
    }

    //var_dump($categories_array);exit;
    
    return $categories_array;
}



/**
 * Qwindo Feed: 2. getProduct
 * 
 * @global type $db
 * @param type $product_id
 * @return array json
 */

function getProduct($product_id)
{
    if ($product_id == "") {
        die("No Product ID supplied.");
    }
    
    global $db;

    $product_query  = "SELECT * FROM " . TABLE_PRODUCTS
                    . " AS p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION
                    . " AS pdesc on p.products_id=pdesc.products_id"
                    . " INNER JOIN " . TABLE_MANUFACTURERS
                    . " AS m on p.manufacturers_id=m.manufacturers_id"
                    . " INNER JOIN " . TABLE_CATEGORIES
                    . " AS c on p.master_categories_id=c.categories_id"
                    . " INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION
                    . " AS cd on c.categories_id=cd.categories_id"
                    . " INNER JOIN " . TABLE_TAX_CLASS
                    . " AS tc on p.products_tax_class_id=tc.tax_class_id"
                    . " INNER JOIN " . TABLE_TAX_RATES
                    . " AS tr on tc.tax_class_id=tr.tax_rates_id"
                    . " INNER JOIN " . TABLE_GEO_ZONES
                    . " AS gz on tr.tax_zone_id =gz.geo_zone_id"
                    . " INNER JOIN " . TABLE_ZONES_TO_GEO_ZONES
                    . " AS ztgz on gz.geo_zone_id=ztgz.geo_zone_id"
                    . " INNER JOIN " . TABLE_COUNTRIES
                    . " AS co on ztgz.zone_country_id=co.countries_id"
                    . " WHERE p.products_id = {$product_id}";

    $product_result = $db->Execute($product_query);

    //Retrieve the product's main category

    $cat_id_query = "SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = {$product_id}";
    $cat_id_result = $db->Execute($cat_id_query)->fields['master_categories_id'];
    $main_cat_query = "SELECT parent_id FROM " . TABLE_CATEGORIES . " WHERE categories_id  =   {$cat_id_result}";
    $main_cat_result = $db->Execute($main_cat_query)->fields['parent_id'];
    $main_query = "SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = {$main_cat_result} ";
    $main_category = $db->Execute($main_query)->fields['categories_name'];

    //Determine whether or not SSL has been enabled

    if (!ENABLE_SSL) {
        $protocol = HTTP_SERVER;
    } else {
        $protocol = HTTPS_SERVER;
    }

    //Retrieve product attributes (product "options" in ZenCart)

    $product_attributes_array = array(
        
    );

    while(!$product_result->EOF)
    {
        $test[] = $product_result->fields;
        $product_result->MoveNext();
    }
    
    var_dump($test);
    exit;

    $product_array = array
    (
        "product_id"                =>  (int) $product_result->fields['products_id'],
        "product_name"              =>  $product_result->fields['products_name'],
        "brand"                     =>  $product_result->fields['manufacturers_name'],
        "sku_number"                =>  $product_result->fields['products_model'],
        "primary_category"          =>  $main_category,
        "secondary_category"        =>  $product_result->fields['categories_name'],
        "product_url"               =>  $product_result->fields['products_url'],
        "short_product_description" =>  substr($product_result->fields['products_description'], 0, 255),
        "long_product_description"  =>  $product_result->fields['products_description'],
        "sale_price"                =>  (double) $product_result->fields['products_price_sorter'],
        "retail_price"              =>  (double) $product_result->fields['products_price'],
        "tax"   =>  array
        (
            "id"        =>  (int) $product_result->fields['tax_class_id'],
            "name"      =>  $product_result->fields['tax_class_title'],
            "rules"     =>  array
            (
                $product_result->fields['countries_iso_code_2'] =>  $product_result->fields['tax_rate']
            )
        ),
        "gtin"                      =>  null,
        "mpn"                       =>  null,
        "unique_identifier"         =>  true,
        "stock"                     =>  (int) $product_result->fields['products_quantity'],
        "metadata"  =>  array
        (
            "key"   =>  'value', //NA
            "key1"  =>  'value1' //NA
        ),
        "created"                   =>  $product_result->fields['products_date_added'],
        "updated"                   =>  $product_result->fields['products_last_modified'],
        "downloadable"              =>  (boolean) $product_result->fields['products_virtual'],
        "package_dimensions"        =>  "",
        "dimension_unit"            =>  "",
        "weight"                    =>  (double) $product_result->fields['products_weight'],
        "weight_unit"               =>  TEXT_PRODUCT_WEIGHT_UNIT,
        "product_image_urls" => array
        (
            array
            (
                "url"   =>  $protocol . DIR_WS_CATALOG . DIR_WS_IMAGES . $product_result->fields['products_image'],
                "main"  =>  true
            )
        ),
        "attributes"    =>  array(),
        "options"       =>  array(),
        "varients"      =>  array(),
    );

    return $product_array;
}



/**
 * Qwindo Feed: 3. getStock
 * 
 * @global type $db
 * @param type $product_id
 * @return array json
 */

function getStock($product_id)
{
    if ($product_id == "") {
        die("No Product ID supplied.");
    }

    global $db;

    $stock_query    =   "SELECT products_quantity, products_id FROM " . TABLE_PRODUCTS . " as p WHERE p.products_id = {$product_id}";
    $stock_result   =   $db->Execute($stock_query);

    if($stock_result->RecordCount() > 0)
    {
        $stock_array = array
        (
            "product_id"    =>  $stock_result->fields['products_id'],
            "stock"         =>  $stock_result->fields['products_quantity']
        );
    }
        
    return $stock_array;
}



/**
 * Qwindo Feed: 4. getShipping
 * 
 * @global type $db
 * @global Qwindo $q
 * @global type $language
 * @global type $currencies
 * @param type $countrycode
 * @return type array
 */

function getShipping($countrycode)
{
    if ($countrycode == "") {
        die("No Countrycode supplied.");
    }

    global $db;
    global $q;

    $shipping_array = array();

    $module_directory = DIR_WS_MODULES . 'shipping/';

    if (!file_exists($module_directory)) {
        die('Unable to load shipping modules.');
    }

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
    
    $module_info = array();
    $module_info_enabled = array();
    $shipping_modules = array();
    for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++)
    {
        $file = $directory_array[$i];

        global $language;

        include_once (DIR_FS_CATALOG . DIR_WS_LANGUAGES . 'english/modules/shipping/' . $file);
        include_once ($module_directory . $file);

        $class = substr($file, 0, strrpos($file, '.'));
        $module = new $class;
        $curr_ship = strtoupper($module->code);
        //var_dump($curr_ship);
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
                'status' => $module->check()
            );
        }



        if (!empty($module_info_enabled[$module->code]['enabled'])) {
            $shipping_modules[$module->code] = $module;
        }
    }

    foreach ($module_info as $key => $value)
    {
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
        @$price = constant($common_string . "COST");

        if (empty($quote['error']) && $quote['id'] != 'zones') 
        {
            foreach ($quote['methods'] as $method)
            {
                if ($zone != '') {
                    $zone_result = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_GEO_ZONES . " AS gz " .
                                                " INNER JOIN " . TABLE_ZONES_TO_GEO_ZONES . " AS ztgz on gz.geo_zone_id = ztgz.geo_zone_id " .
                                                " INNER JOIN " . TABLE_COUNTRIES . " AS c on ztgz.zone_country_id = c.countries_id " .
                                                " LEFT JOIN " . TABLE_ZONES . " AS z on ztgz.zone_id=z.zone_id WHERE gz.geo_zone_id= '" . $zone . "'");
                    
                    if ($zone_result->RecordCount() > 0) {
                        while (!$zone_result->EOF) {
                            //var_dump($zone_result->fields);
                            $allowed_zones[] = $zone_result->fields['countries_iso_code_2'];
                            
                            $zone_result->MoveNext();
                        }
                    }
                    
                    $shipping_array[] = array
                        (
                        'id' => $quote['id'],
                        'type' => $module_name,
                        'provider' => $quote['module'],
                        'name' => $quote['methods'][0]['title'],
                        'price' => $shipping_price,
                        'zone' => $allowed_zones
                    );
                }
                /*
                $shipping_array[] = array
                (
                    'id'        =>  $quote['id'],
                    'type'      =>  $module_name,
                    'provider'  =>  $quote['module'],
                    'name'      =>  $quote['methods'][0]['title'],
                    'price'     =>  $shipping_price,
                    'zone'      =>  $zone //
                );*/
            }
        }
    }

    return $shipping_array;
}

/**
 * Qwindo Feed: 5. getStore
 * 
 * @global type $db
 * @global Qwindo $q
 * @return type array
 */

function getStore()
{
    global $db;
    global $q;

    /**
     * Retrieve countries to which the webshop ships to.
     */
    
    /*
    $shipping_countries_array   =   array();
    $shipping_countries_query   =   "BLABLA";
    $result                     =   $db->Execute($shipping_countries_query);
    
    if ($result->RecordCount() > 0) {
        while (!$result->EOF) {
            $shipping_countries_array[] = $result->fields;
            $result->MoveNext();
        }
    }*/
    
    /**
     * Retrieve allowed/enabled countries
     * The returned data is equal to the coutries selectable during an order placement in the webshop
     */
    
    $allowed_countries_array    =   array();
    $allowed_countries_query    =   "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES;
    $result = $db->Execute($allowed_countries_query);

    if ($result->RecordCount() > 0) {
        while (!$result->EOF) {
            $allowed_countries_array[] = $result->fields['countries_iso_code_2'];
            $result->MoveNext();
        }
    }

    /**
     *  Retrieve supported languages
     */
    
    $supported_languages_array  =   array();
    $language_query             =   "SELECT * FROM " . TABLE_LANGUAGES;
    $supported_language_result  =   $db->Execute($language_query);

    if ($supported_language_result->RecordCount() > 0) {
        while (!$supported_language_result->EOF) {
            $supported_languages_array[$q->getLocaleFromLanguageCode($supported_language_result->fields['code'])] = array
            (
                "title" => $supported_language_result->fields['name']
            );

            $supported_language_result->MoveNext();
        }
    }

    $supported_currencies_array =   array();
    $currencies_query           =   "SELECT * FROM " . TABLE_CURRENCIES;
    $result                     =   $db->Execute($currencies_query);
    
    if($result->RecordCount() > 0)
    {
        while(!$result->EOF)
        {
            $supported_currencies_array[]   =   $result->fields['code'];
            $result->MoveNext();
        }
    }
    
    /**
     *  Split street and housenumber
     */
    
    if (MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS) {
        list($street, $housenumber) = $q->parseAddress(MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS);
    } else {
        $street      = null;
        $housenumber = null;
    }

    /**
     *  Retrieve stock update info.
     */
    
    $stock_query            =   "SELECT * FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'STOCK_CHECK'";
    $stock_result           =   $db->Execute($stock_query);
    $stock_update_enabled   =   (boolean) $stock_result->fields['configuration_value'];

    /**
     *  Opening days of the store
     */
    
    $sunday = (MODULE_PAYMENT_MSP_QWINDO_OPENSUN == 'Open') ? true : false;
    $monday = (MODULE_PAYMENT_MSP_QWINDO_OPENMON == 'Open') ? true : false;
    $tuesday = (MODULE_PAYMENT_MSP_QWINDO_OPENTUE == 'Open') ? true : false;
    $wednesday = (MODULE_PAYMENT_MSP_QWINDO_OPENWED == 'Open') ? true : false;
    $thursday = (MODULE_PAYMENT_MSP_QWINDO_OPENTHU == 'Open') ? true : false;
    $friday = (MODULE_PAYMENT_MSP_QWINDO_OPENFRI == 'Open') ? true : false;
    $saturday = (MODULE_PAYMENT_MSP_QWINDO_OPENSAT == 'Open') ? true : false;

    $shopinfo_array = array
        (
        "shipping_countries" => array(
            $shipping_countries_array
        ),
        "allowed_countries" => array(
            $allowed_countries_array
        ),
        "languages" => array(
            $supported_languages_array
        ),
        "stock_updates" => $stock_update_enabled,
        "supported_currencies" => $supported_currencies_array,
        "including_tax" => (boolean) MODULE_PAYMENT_MSP_QWINDO_INCL_TAX,
        "shipping_tax" => array(),
        "require_shipping" => (boolean) MODULE_PAYMENT_MSP_QWINDO_REQ_SHIP,
        "base_url" => MODULE_PAYMENT_MSP_QWINDO_URL_BASE,
        "order_push_url" => MODULE_PAYMENT_MSP_QWINDO_URL_OP,
        "coc" => MODULE_PAYMENT_MSP_QWINDO_COC,
        "email" => MODULE_PAYMENT_MSP_QWINDO_STORE_EMAIL,
        "contact_phone" => MODULE_PAYMENT_MSP_QWINDO_STORE_PHONE,
        "address" => $street,
        "housenumber" => $housenumber,
        "zipcode" => MODULE_PAYMENT_MSP_QWINDO_STORE_ZIP,
        "city" => MODULE_PAYMENT_MSP_QWINDO_STORE_CITY,
        "country" => $q->getCountryFromCode(MODULE_PAYMENT_MSP_QWINDO_STORE_COUNTRY),
        "vat_nr" => MODULE_PAYMENT_MSP_QWINDO_VAT,
        "terms_and_conditions" => MODULE_PAYMENT_MSP_QWINDO_TOC,
        "faq" => MODULE_PAYMENT_MSP_QWINDO_FAQ,
        "open" => MODULE_PAYMENT_MSP_QWINDO_OPEN,
        "closed" => MODULE_PAYMENT_MSP_QWINDO_CLOSED,
        "days" => array(
            "Sunday" => $sunday,
            "Monday" => $monday,
            "Tuesday" => $tuesday,
            "Wednesday" => $wednesday,
            "Thursday" => $thursday,
            "Friday" => $friday,
            "Saturday" => $saturday
        ),
        "social" => array(
            "facebook" => MODULE_PAYMENT_MSP_QWINDO_URL_FB,
            "twitter" => MODULE_PAYMENT_MSP_QWINDO_URL_TW,
            "linkedin" => MODULE_PAYMENT_MSP_QWINDO_URL_LI
        )
    );

    return $shopinfo_array;
}
?>

