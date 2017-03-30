<?php

class Qwindo {

    
    /**
     * Construct
     */
    
    public function __construct()
    {
        if($this->isHttps())        
        //if(!ENABLE_SSL && !$this->isHttps()) - temp as we don't support SSL on the new plug-in group server
        {
            die("An SSL secure connection is required for Qwindo's Feed.");
        }
        
        if (MODULE_PAYMENT_MSP_QWINDO_STATUS !== "True") {
            die("The Qwindo Product Feed is disabled.");
        }
        
        $this->_setReqHeaders();
    }
    
    
    
    /**
     * Check whether the current connection is over HTTPS
     * 
     * @return boolean
     */
    
    protected function isHttps()
    {
        if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        {
            return true;
        } else {
            return false;
        }
    }
    
    
    
    /**
     * Set the required headers
     */
    
    private function _setReqHeaders()
    {
        header('X-Feed-Version: 1.0');
        //header('Auth: '. 'temp');
        
        
        //header("HTTP/1.0 418 I'm a teapot");exit;
        //var_dump(headers_list());exit;
        //header('api_key:' . $_GET['api_key']); //c092a2a61eec2964c19638c9fd76f62a6b5bf0ca
        
    }
    
    
    
    /**
     * Set the required headers
     */
    
    private function _setAuth()
    {
        //Dev API key: c092a2a61eec2964c19638c9fd76f62a6b5bf0ca
        
        //get full url of the call
        $api_key    =   'Your stored Qwindo API Key';// This can be found within your website profile at MultiSafepay
        $url        =   get_current_url(); // This should be the full URL including parameters
        $hash_id    =   'Your stored HASH ID'; // This can be found within your website profile at MultiSafepay
        $timestamp  =   microtime(true);
        $auth       =   explode('|', base64_decode($header['Auth']));
        $message    =   $url.$auth[0].$hash_id;
        $token      =   hash_hmac('sha512', $message, $api_key);

        //in this check you can count the diff between your timestamp and the timestamp of the call. If the timestamp is grater than etc 10 
        //seconds you reject the call (key will be valid only for just some time.)
        if($token !== $auth[1] and round($timestamp - $auth[0]) > 10)
        {
            //not valid call
        } else {
            //valid call
        }        
        
    }
    
    
        
    /**
     * Array to JSON for output
     * 
     * @param type $data
     * @return type JSON
     */
    
    public function outputJson($data)
    {
        //return gzcompress(json_encode($data, JSON_PRETTY_PRINT));
        return json_encode($data, JSON_PRETTY_PRINT);
    }
    
    
    
    /**
     * Switch through the provided identifier
     * 
     * @param type $identifier
     * @return type
     */
    
    public function switchIdentifier($identifier)
    {
        if($identifier != "")
        {
            switch ($identifier)
            {
                case "categories":
                        return $this->outputJson(getCategories());
                    break;
                case "products":
                        return $this->outputJson(getProduct($_GET['product_id']));
                    break;
                case "stock":
                        return $this->outputJson(getStock($_GET['product_id']));
                    break;        
                case "shipping":
                        return $this->outputJson(getShipping($_GET['countrycode']));
                    break;
                case "stores":
                        return $this->outputJson(getStore());
                    break;
                default:
                    die("Invalid identifier supplied.");
                break;
            }
        } else {
            die("Invalid identifier supplied.");
        }        
    }

    
    
    /**
     * Split street and housenumber
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
     * Recursively search in an array
     * 
     * @param type $needle
     * @param type $haystack
     * @param type $strict
     * @return boolean
     */
    
    public function in_array_recursive($needle, $haystack, $strict = false)
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
     * Get language code by language_id
     * 
     * @global type $db
     * @param type $lang_id
     * @return type
     */
    
    public function getLocale($lang_id)
    {
        global $db;
        
        $lang_query =   "SELECT code FROM " . TABLE_LANGUAGES . " WHERE languages_id='{$lang_id}'";
        $lang_result=   $db->Execute($lang_query);
        
        return $this->getLocaleFromLanguageCode($lang_result->fields['code']);
    }
    
    
    
    /**
     * Convert language_code to locale
     * 
     * @param type $language_code
     * @return type
     */
    
    public function getLocaleFromLanguageCode($language_code)
    {
        $locale_array = array
            (
            'nl' => 'nl_NL',
            'en' => 'en_US',
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
     * Retrieve country ISO 3166-1 Alpha 2 by country name 
     * 
     * @global type $db
     * @param type $code
     * @return type
     */
    
    public function getCountryFromCode($country_id)
    {
        global $db;

        $country = $db->Execute("SELECT countries_iso_code_2 FROM " . TABLE_COUNTRIES . " WHERE countries_id = '" . $country_id . "'");

        return $country->fields['countries_iso_code_2'];
    }

    
    
    
}

?>