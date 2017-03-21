<?php

/**
 *  FastCheckout Shop Feed for ZenCart
 */
chdir("../../../../");

require("includes/application_top.php");

//Validate required parameters depending on $_identifier

header('X-Feed-Version: 1.0');
header('api_key:' . $_GET['api_key']);


$_identifier = $_GET['identifier'];

switch ($_identifier)
{
    case "products": //Both in progress
        if (empty($_GET['category_id'])) {
            //echo json_encode(getProductById($_GET['language'], $_GET['product_id']));                
            var_dump(getProductById($_GET['language'], $_GET['product_id']));
        } elseif (!empty($_GET['category_id'])) {
            //echo json_encode(getProductsByCategoryID($_GET['language'], $_GET['category_id']));                   
            var_dump(getProductsByCategoryID($_GET['language'], $_GET['category_id']));
        }
        break;
    case "tax":
        echo json_encode(getTaxRules(), JSON_PRETTY_PRINT);
        break;
    case "stock":
        echo json_encode(getStockByProductID($_GET['product_id']), JSON_PRETTY_PRINT);
        break;
    case "shipping":
        echo json_encode(getShippingMethodsByCountryCode($_GET['countrycode'], $_GET['language']));
        break;
    case "categories": //In progress
        echo json_encode(getCategories($_GET['language']), JSON_PRETTY_PRINT);
        break;
    case "stores":
        echo json_encode(getShopInfo(), JSON_PRETTY_PRINT);
        break;
    default:
        //default action, throw exception? default message? (Invalid identifier)
        //die('Invalid identifier specified');
        break;
}

/**
 * FCO Shop Feed: Product by ID
 * 
 * @global type $db
 * @param type $language_locale
 * @param type $product_id
 * @return array
 */
function getProductById($language_locale, $product_id)
{
    global $db;

    $language_id = getLangIDByLocale($language_locale);

    //If language_id returned is null, then ommit from query

    if (!is_null($language_id)) {
        $lang_whereclause = " AND pdesc.language_id = {$language_id} AND cd.language_id = {$language_id}";
    }

    $product_query = "SELECT * FROM " . TABLE_PRODUCTS
            . " AS p INNER JOIN " . TABLE_PRODUCTS_DESCRIPTION
            . " AS pdesc on p.products_id=pdesc.products_id"
            . " INNER JOIN " . TABLE_MANUFACTURERS
            . " AS m on p.manufacturers_id=m.manufacturers_id"
            . " INNER JOIN " . TABLE_CATEGORIES
            . " AS c on p.master_categories_id=c.categories_id"
            . " INNER JOIN " . TABLE_CATEGORIES_DESCRIPTION
            . " AS cd on c.categories_id=cd.categories_id"
            . " WHERE p.products_id = {$product_id} {$lang_whereclause}";

    $result = $db->Execute($product_query);

    //Retrieve the product's main category

    if (!is_null($language_id)) {
        $maincat_whereclause = " AND " . TABLE_CATEGORIES_DESCRIPTION . ".language_id = {$language_id}";
    }

    $cat_id_query = "SELECT master_categories_id FROM " . TABLE_PRODUCTS . " WHERE products_id = {$product_id}";
    $cat_id_result = $db->Execute($cat_id_query)->fields['master_categories_id'];
    $main_cat_query = "SELECT parent_id FROM " . TABLE_CATEGORIES . " WHERE categories_id  =   {$cat_id_result}";
    $main_cat_result = $db->Execute($main_cat_query)->fields['parent_id'];
    $main_query = "SELECT categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " WHERE categories_id = {$main_cat_result} {$maincat_whereclause}";
    $main_category = $db->Execute($main_query)->fields['categories_name'];

    //Determine whether or not product has been previously modified

    if (!is_null($result->fields['products_last_modified'])) {
        $last_modified = getDateTimeStamp($result->fields['products_last_modified']);
    } else {
        $last_modified = null;
    }

    //Determine whether or not SSL has been enabled

    if (!ENABLE_SSL) {
        $host = HTTP_SERVER;
    } else {
        $host = HTTPS_SERVER;
    }

    //Retrieve product attributes (product "options" in ZenCart)



    $product_attributes_array = array(
    );

    //var_dump($result);exit;

    $product_array = array
        (
        "ProductID" => (int) $result->fields['products_id'],
        "ProductName" => $result->fields['products_name'],
        "Brand" => $result->fields['manufacturers_name'],
        "SKUnumber" => $result->fields['products_model'],
        "PrimaryCategory" => $main_category,
        "SecondaryCategory" => $result->fields['categories_name'],
        "ProductURL" => $result->fields['products_url'],
        "ShortProductDescription" => substr($result->fields['products_description'], 0, 255),
        "LongProductDescription" => $result->fields['products_description'],
        "SalePrice" => (double) $result->fields['products_price_sorter'],
        "RetailPrice" => (double) $result->fields['products_price'],
        "FTIN" => false,
        "MPN" => false,
        "UniqueIdentifier" => false,
        "Currency" => '', //NA
        "TaxId" => (int) $result->fields['products_tax_class_id'],
        "Stock" => (int) $result->fields['products_quantity'],
        "Metadata" => array
            (
            "key" => 'value', //NA
            "key1" => 'value1' //NA
        ),
        "Created" => getDateTimeStamp($result->fields['products_date_added']),
        "Updated" => $last_modified,
        "Downloadable" => (boolean) $result->fields['products_virtual'],
        "PackageDimensions" => '', //NA
        "Weight" => (double) $result->fields['products_weight'],
        "ProductImageURLs" => array
            (
            array
                (
                "url" => $host . DIR_WS_CATALOG . DIR_WS_IMAGES . $result->fields['products_image'],
                "main" => true
            )
        ),
        $product_attributes_array,
        //DB: products_options x3
        "Options" => array(
            "GlobalOptions" => array(
                "Shoe size" => array(
                    "102" => array(
                        "Label" => "6",
                        "Pricing" => null
                    ),
                    "101" => array(
                        "Label" => "7",
                        "Pricing" => null
                    ),
                    "100" => array(
                        "Label" => "8",
                        "Pricing" => null
                    ),
                    "99" => array(
                        "Label" => "9",
                        "Pricing" => null
                    ),
                    "98" => array(
                        "Label" => "10",
                        "Pricing" => null
                    )
                )
            )
        )
    );

    return $product_array;
}

/**
 * FCO Shop Feed: Products by CategoryID
 * 
 * @global type $db
 * @param type $language_locale
 * @param type $category_id
 * @return type array
 */
function getProductsByCategoryID($language_locale, $category_id)
{
    global $db;

    $language_id = getLangIDByLocale($language_locale);

    $product_query = "SELECT ...";

    $product_result = $db->Execute($product_query);
}

/**
 * FCO Shop Feed: Tax Rules
 * 
 * @global type $db
 * @return type array
 */
function getTaxRules()
{
    global $db;

    /**
     *  Retrieve available tax rates
     */
    $taxrules_array = array();

    $tax_query = "SELECT * FROM " . TABLE_TAX_RATES
            . " AS tr INNER JOIN " . TABLE_TAX_CLASS
            . " AS tc on tr.tax_class_id=tc.tax_class_id"
            . " INNER JOIN " . TABLE_GEO_ZONES
            . " AS gz on tr.tax_zone_id=gz.geo_zone_id"
            . " INNER JOIN " . TABLE_ZONES_TO_GEO_ZONES
            . " AS ztgz on gz.geo_zone_id=ztgz.geo_zone_id"
            . " INNER JOIN " . TABLE_ZONES
            . " AS z on ztgz.zone_country_id=z.zone_country_id";

    $tax_result = $db->Execute($tax_query);

    if ($tax_result->RecordCount() > 0) {
        while (!$tax_result->EOF) {

            $taxrules_array[] = array
                (
                "id" => (int) $tax_result->fields['tax_rates_id'],
                "name" => $tax_result->fields['tax_class_title'],
                "rules" => array
                    (
                    $tax_result->fields['zone_code'] => /* (float) */ $tax_result->fields['tax_rate']
                )
            );
            $tax_result->MoveNext();
        }
    }

    return $taxrules_array;
}

/**
 * FCO Shop Feed: Stock
 * 
 * @global type $db
 * @param type $product_id
 * @return array
 */
function getStockByProductID($product_id)
{
    global $db;

    $stock_query = "SELECT products_quantity, products_id FROM " . TABLE_PRODUCTS . " as p WHERE p.products_id = {$product_id}";
    $result = $db->Execute($stock_query);

    $stock_array = array
        (
        "ProductID" => $result->fields['products_id'],
        "Stock" => $result->fields['products_quantity']
    );

    return $stock_array;
}

/**
 * FCO Shop Feed: Shopinfo
 * 
 * @global type $db
 * @return array $shopinfo_array
 */
function getShopInfo()
{
    global $db;

    /**
     * Retrieve allowed/enabled countries
     * The returned data is equal to the coutries selectable during an order placement in the webshop
     */
    $allowed_countries_array = array();

    $allowed_countries_query = "SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES;

    $result = $db->Execute($allowed_countries_query);

    if ($result->RecordCount() > 0) {
        while (!$result->EOF) {
            $allowed_countries_array[] = $result->fields['countries_iso_code_2'];

            $result->MoveNext();
        }
    }

    /**
     *  Retrieve supported Languages
     */
    $supported_languages_array = array();

    $language_query = "SELECT * FROM " . TABLE_LANGUAGES;

    $result = $db->Execute($language_query);

    if ($result->RecordCount() > 0) {
        while (!$result->EOF) {
            $supported_languages_array[getLocaleFromLanguageCode($result->fields['code'])] = array
                (
                "title" => $result->fields['name']
            );

            $result->MoveNext();
        }
    }

    /**
     *  Split street and housenumber
     */
    if (MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS) {
        list($cust_street, $cust_housenumber) = parseAddress(MODULE_PAYMENT_MSP_QWINDO_STORE_ADDRESS);
    } else {
        $cust_street = null;
        $cust_housenumber = null;
    }

    /**
     *  Retrieve stock update info.
     */
    $stock_query = "SELECT * FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'STOCK_CHECK'";

    $result = $db->Execute($stock_query);

    $stock_update_enabled = (boolean) $result->fields['configuration_value'];

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
        "allowed_countries" => array(
            $allowed_countries_array
        ),
        "languages" => array(
            $supported_languages_array
        ),
        "stock_updates" => $stock_update_enabled,
        "including_tax" => (boolean) MODULE_PAYMENT_MSP_QWINDO_INCL_TAX,
        "require_shipping" => (boolean) MODULE_PAYMENT_MSP_QWINDO_REQ_SHIP,
        "base_url" => MODULE_PAYMENT_MSP_QWINDO_URL_BASE,
        "order_push_url" => MODULE_PAYMENT_MSP_QWINDO_URL_OP,
        "coc" => MODULE_PAYMENT_MSP_QWINDO_COC,
        "email" => MODULE_PAYMENT_MSP_QWINDO_STORE_EMAIL,
        "contact_phone" => MODULE_PAYMENT_MSP_QWINDO_STORE_PHONE,
        "address" => $cust_street,
        "housenumber" => $cust_housenumber,
        "zipcode" => MODULE_PAYMENT_MSP_QWINDO_STORE_ZIP,
        "city" => MODULE_PAYMENT_MSP_QWINDO_STORE_CITY,
        "country" => getCountryFromCode(MODULE_PAYMENT_MSP_QWINDO_STORE_COUNTRY),
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

/**
 * FCO Shop Feed: Shipping methods 
 * 
 * @param type $country_code
 * @param type $language_locale
 * @return type array
 */
function getShippingMethodsByCountryCode($country_code, $language_locale)
{
    /**
     *  Retrieve available shipping methods
     */
    //Load shipping modules

    $module_directory = DIR_FS_CATALOG . DIR_WS_INCLUDES . 'modules/' . 'shipping/';

    if (!file_exists($module_directory)) {
        die('Couldn\'t find shipping modules in: ' . $module_directory);
    }

    $file_extension = substr(__FILE__, strrpos(__FILE__, '.'));

    $directory_array = array();

    if ($dir = @dir($module_directory)) {
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
        include_once (DIR_FS_CATALOG . DIR_WS_LANGUAGES . getLanguageByLocale($language_locale) . '/modules/shipping/' . $file);
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
            $module_info[$module->code] = array
                (
                'code' => $module->code,
                'title' => $module->title,
                'description' => $module->description,
                'sort_order' => $module->sort_order,
                'status' => $module->check(),
                'allowed_zones' => @constant('MODULE_SHIPPING_' . $curr_ship . '_ZONE')
            );
        }

        if (!empty($module_info_enabled[$module->code]['enabled'])) {
            $shipping_modules[$module->code] = $module;
        }
    }

    $shippingmethods_array = array();

    foreach ($module_info as $key => $value)
    {
        //Check if active
        $module_name = $module_info[$key]['code'];
        $sort_order = $module_info[$key]['sort_order'];
        $allowed_zones = $module_info[$key]['allowed_zones'];

        if (!$module_info_enabled[$module_name]) {
            continue;
        }

        $curr_ship = strtoupper($module_name);
        $module = $shipping_modules[$module_name];
        $quote = $module->quote($method);
        $price = $quote['methods'][0]['cost'];

        global $currencies;

        $shipping_price = $currencies->get_value(DEFAULT_CURRENCY) * ($price >= 0 ? $price : 0);

        if (empty($quote['error'])) {
            if ($country_code === getCountryByZoneID($allowed_zones)) {
                foreach ($quote['methods'] as $method)
                {
                    $shippingmethods_array[] = array
                        (
                        "id" => $quote['id'],
                        "name" => $quote['module'] . ' - ' . $quote['methods'][0]['title'],
                        "price" => (float) $shipping_price,
                        "sort_order" => (int) $sort_order,
                        "allowed_areas" => getCountryByZoneID($allowed_zones)
                    );
                }
            }

            if ($quote['id'] === 'storepickup') {
                $shippingmethods_array[] = array
                    (
                    "id" => $quote['id'],
                    "name" => $quote['module'],
                    "price" => (float) 0.00,
                    "sort_order" => null,
                    "allowed_areas" => $country_code
                );
            }
        }
    }

    return $shippingmethods_array;
}

/**
 * FCO Shop Feed: Categories
 * 
 * @global type $db
 * @param type $language_locale
 * @return type array
 */
function getCategories($language_locale)
{
    global $db;

    $language_id = getLangIDByLocale($language_locale);

    //Retrieve sub categories
    /*
      $subcat_query   =   "SELECT * FROM " . TABLE_CATEGORIES
      .   " AS c LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION
      .   " AS cd on c.categories_id=cd.categories_id"
      .   " WHERE cd.language_id='$language_id' AND c.parent_id != '0' and c.categories_status = '1'";

      $subcat_result  =   $db->Execute($subcat_query);

      if($subcat_result->RecordCount() > 0)
      {
      while(!$subcat_result->EOF)
      {
      $maincat_query  =   "SELECT * FROM " . TABLE_CATEGORIES
      .   " AS c LEFT JOIN " . TABLE_CATEGORIES_DESCRIPTION
      .   " AS cd on c.categories_id=cd.categories_id"
      .   " WHERE cd.language_id='$language_id' AND c.parent_id='{$subcat_result->fields['categories_id']}'"
      .   " AND c.categories_status = '1'";

      $maincat_result =   $db->Execute($maincat_query);
      var_dump($maincat_result);
      //var_dump($subcat_result->fields);
      $subcat_result->MoveNext();
      }
      }
     */

    //return $categories_array;
}

/**
 * Helper functions
 */

/**
 * Split street and housenumber
 * 
 * @param type $street_address
 * @return type
 */
function parseAddress($street_address)
{
    $address = $street_address;
    $apartment = "";

    $offset = strlen($street_address);

    while (($offset = rstrpos($street_address, ' ', $offset)) !== false) {
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
function rstrpos($haystack, $needle, $offset = null)
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
 * Converts datetime to timestamp
 * 
 * @param type $datetime
 * @return type int
 */
function getDateTimeStamp($datetime)
{
    $date_obj = new DateTime($datetime);

    return (int) $date_obj->getTimestamp();
}

/**
 * Returns language_id based on the provided locale
 * 
 * @param type $language_code
 * @return type int OR null
 */
function getLangIDByLocale($language_code)
{
    global $db;

    $locale = substr($language_code, 0, 2);

    $language_id_query = "SELECT languages_id FROM " . TABLE_LANGUAGES . " WHERE code = '$locale'";

    $result = $db->Execute($language_id_query);

    if (zen_not_null($result->fields)) {
        return $result->fields['languages_id'];
    } else {
        return null;
    }
}

/**
 * Retrieve country ISO 3166-1 Alpha 2 by country name 
 * 
 * @global type $db
 * @param type $code
 * @return type
 */
function getCountryFromCode($country_id)
{
    global $db;

    $country = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = '" . $country_id . "'");

    return $country->fields['countries_iso_code_2'];
}

/**
 * Retrieve zone id from database by countrycode
 * 
 * @global type $db
 * @param type $country_code
 * @return type
 */
function getZoneIDByCountryCode($country_code)
{
    global $db;

    $country_id = $db->Execute("SELECT countries_id FROM " . TABLE_COUNTRIES . " WHERE countries_iso_code_2='$country_code'")->fields['countries_id'];
    $geo_id = $db->Execute("SELECT geo_zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE zone_country_id='$country_id'")->fields['geo_zone_id'];

    return $geo_id;
}

/**
 * Retrieve countrycode by zone id
 * 
 * @global type $db
 * @param type $zone_id
 * @return type
 */
function getCountryByZoneID($zone_id)
{
    global $db;

    $country_id = $db->Execute("SELECT zone_country_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '$zone_id'")->fields['zone_country_id'];
    $country_code = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id='$country_id'")->fields['countries_iso_code_2'];

    return $country_code;
}

/**
 * Convert language_code to locale
 * 
 * @param type $language_code
 * @return type
 */
function getLocaleFromLanguageCode($language_code)
{
    $locale_array = array
        (
        'nl' => 'nl_NL',
        'en' => 'en_GB',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
        'de' => 'de_DE',
        'it' => 'it_IT',
        'sv' => 'sv_SE',
        'tr' => 'tr_TR',
        'cs' => 'cs_CS',
        'pl' => 'pl_PL',
        'pt' => 'pt_PT',
        'he' => 'he_HE',
        'ru' => 'ru_RU',
        'ar' => 'ar_AR',
        'cn' => 'zh_CN',
        'ro' => 'ro_RO',
        'da' => 'da_DA',
        'fi' => 'fi_FI',
        'no' => 'no_NO'
    );

    if (array_key_exists($language_code, $locale_array)) {
        return $locale_array[$language_code];
    } else {
        return null;
    }
}

/**
 * Convert language_code to locale
 * 
 * @param type $language_code
 * @return string
 */
function getLanguageByLocale($language_code)
{
    $language_array = array
        (
        'nl_NL' => 'dutch',
        'en_GB' => 'english',
        'fr_FR' => 'french',
        'es_ES' => 'spanish',
        'de_DE' => 'german',
        'it_IT' => 'italian'
    );

    if (array_key_exists($language_code, $language_array)) {
        return $language_array[$language_code];
    } else {
        return 'english';
    }
}

/**
 * Recursively search in an array
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
?>

