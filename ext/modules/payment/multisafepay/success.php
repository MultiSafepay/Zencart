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

chdir('../../../../');

require('includes/application_top.php');
require("includes/modules/payment/multisafepay.php");
require("includes/modules/payment/multisafepay_fastcheckout.php");
require("includes/modules/payment/multisafepay_payafter.php");
require("includes/modules/payment/multisafepay_klarna.php");
require("includes/modules/payment/multisafepay_einvoice.php");
require($template->get_template_dir('main_template_vars.php', DIR_WS_TEMPLATE, $current_page_base, 'common') . '/main_template_vars.php');

$_SESSION['cart']->reset(true);
unset($_SESSION['sendto']);
unset($_SESSION['billto']);
unset($_SESSION['shipping']);
unset($_SESSION['payment']);
unset($_SESSION['comments']);
$_SESSION['order_number_created'] = $_GET['transactionid'];

if (empty($_GET['transactionid'])) {
    $message = "No transaction ID supplied";
    $url = zen_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $payment_module->code . '&error=' . urlencode($message), 'NONSSL', true, false);
} else {
    global $db;

    $_SESSION['cart']->reset(true);

    require(DIR_WS_CLASSES . "order.php");
    $order = new order($_GET['transactionid']);

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

    if ($payment_module->msp->orders->data->fastcheckout == "NO") {
        $merchant_order_id = $payment_module->msp->orders->data->order_id;
        if ($payment_module->msp->orders->data->payment_details->type == "PAYAFTER") {
            $payment_module = new multisafepay_payafter();
            $payment_module->order_id = $merchant_order_id; //$_GET['transactionid'];
            $_SESSION['payment'] = 'multisafepay_payafter';
        } elseif ($payment_module->msp->orders->data->payment_details->type == "KLARNA") {
            $payment_module = new multisafepay_klarna();
            $payment_module->order_id = $merchant_order_id; //$_GET['transactionid'];
            $_SESSION['payment'] = 'multisafepay_klarna';
        }
    } else {
        $payment_module = new multisafepay_fastcheckout();
        $payment_module->order_id = $_GET['transactionid'];
        $_SESSION['payment'] = 'multisafepay_fastcheckout';
    }

    include_once("includes/languages/english/modules/payment/" . $_SESSION['payment'] . ".php");

    $GLOBALS[$_SESSION['payment']] = $payment_module;

    if (isset($payment_module->msp->orders->data->payment_details->type)) {
        $GLOBALS[$_SESSION['payment']]->title = $payment_module->msp->orders->data->payment_details->type;
    } else {
        $GLOBALS[$_SESSION['payment']]->title = MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE;
    }

    $status = $payment_module->checkout_notify();
}

if (!isset($_SESSION['customer_id'])) {
    $_SESSION['customer_id'] = $customer_id;
}

if ($_SESSION['customer_id']) {
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
} else {
    zen_redirect(zen_href_link('index'));
}
?>

