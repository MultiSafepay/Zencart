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

//Dirty fix. Zencart uses the get variable currency= to set the shop currency. It does this by redirecting the customer to the main page. Because of this the shippingmethods arn't returned to Fast Checkout.
unset($_GET['currency']);


chdir("../../../../");
require("includes/application_top.php");
require("includes/modules/payment/multisafepay.php");
require("includes/modules/payment/multisafepay_fastcheckout.php");
require("includes/modules/payment/multisafepay_payafter.php");
require("includes/modules/payment/multisafepay_klarna.php");
require("includes/modules/payment/multisafepay_einvoice.php");

$initial_request = ($_GET['type'] == 'initial');

if (empty($_GET['transactionid'])) {
    $message = "No transaction ID supplied";
    $url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $payment_module->code . '&error=' . urlencode($message), 'NONSSL', true, false);
} else {
    // load selected payment module
    global $db;
    $_SESSION['cart']->reset(true);

    require(DIR_WS_CLASSES . "order.php");
    $order = new order($_GET['transactionid']);
    if ($_GET['type'] != 'shipping') {
        //print_r($order);exit;
    }
    $order_status_query = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = '" . $order->info['orders_status'] . "' AND language_id = '" . $languages_id . "'");

    $order->info['order_status'] = $order_status_query->fields['orders_status_id'];

    require(DIR_WS_CLASSES . "order_total.php");
    $order_total_modules = new order_total();

    $customer_id = $order->customer['id'];
    $order_totals = $order->totals;

    // update order status
    $_SESSION['payment'] = 'multisafepay';
    $payment_module = new multisafepay();
    $payment_module->order_id = $_GET['transactionid'];

    $transdata = $payment_module->check_transaction();

    if ($payment_module->msp->orders->data->fastcheckout === "NO") {
        if ($payment_module->msp->orders->data->payment_details->type == "PAYAFTER") {
            $merchant_order_id = $payment_module->msp->orders->data->order_id;
            $payment_module = new multisafepay_payafter();
            $payment_module->order_id = $merchant_order_id; //$_GET['transactionid'];
            $_SESSION['payment'] = 'multisafepay_payafter';
            //$payment_module = $GLOBALS[$payment_modules->selected_module];
        } elseif ($payment_module->msp->orders->data->payment_details->type == "KLARNA") {
            $merchant_order_id = $payment_module->msp->orders->data->order_id;
            $payment_module = new multisafepay_klarna();
            $payment_module->order_id = $merchant_order_id; //$_GET['transactionid'];
            $_SESSION['payment'] = 'multisafepay_klarna';
        }
    } elseif ($payment_module->msp->orders->data->fastcheckout === "YES") {
        $payment_module = new multisafepay_fastcheckout();
        $payment_module->order_id = $_GET['transactionid'];
        $_SESSION['payment'] = 'multisafepay_fastcheckout';
        //$payment_module = $GLOBALS[$payment_modules->selected_module];
    }

    include_once("includes/languages/english/modules/payment/" . $_SESSION['payment'] . ".php");

    $GLOBALS[$_SESSION['payment']] = $payment_module;

    if (isset($payment_module->msp->orders->data->payment_details->type)) {
        $GLOBALS[$_SESSION['payment']]->title = $payment_module->msp->orders->data->payment_details->type;
    } else {
        $GLOBALS[$_SESSION['payment']]->title = MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE;
    }

    $status = $payment_module->checkout_notify();

    if ($payment_module->_customer_id) {
        $hash = $payment_module->get_hash($payment_module->order_id, $payment_module->_customer_id);
        $parameters = 'customer_id=' . $payment_module->_customer_id . '&hash=' . $hash;
    }

    switch ($status) {
        case "initialized":
        case "completed":
        case "uncleared":
            $message = "OK";
            $url = zen_href_link("ext/modules/payment/multisafepay/success.php", $parameters, 'NONSSL');
            break;
        default:
            $message = "OK";
            $url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $payment_module->code . '&error=' . urlencode($status), 'NONSSL', true, false);
    }
}

if ($initial_request) {
    echo "<p><a href=\"" . $url . "\">" . sprintf(MODULE_PAYMENT_MULTISAFEPAY_TEXT_RETURN_TO_SHOP, htmlspecialchars(STORE_NAME)) . "</a></p>";
} else {
    header("Content-type: text/plain");
    echo $message;
}
?>
