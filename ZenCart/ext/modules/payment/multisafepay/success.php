<?php

chdir('../../../../');

// LOAD ZENCART DATA
require('includes/application_top.php');
require("includes/modules/payment/multisafepay.php");
require("includes/modules/payment/multisafepay_fastcheckout.php");
require("includes/modules/payment/multisafepay_payafter.php");
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
    $url = zen_href_link(
            FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $payment_module->code . '&error=' . urlencode($message), 'NONSSL', true, false
    );
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

    // set some globals (expected by osCommerce)
    $customer_id = $order->customer['id'];
    $order_totals = $order->totals;

    // update order status
    $_SESSION['payment'] = 'multisafepay';

    $payment_module = new multisafepay();

    $payment_module->order_id = $_GET['transactionid'];
    $transdata = $payment_module->check_transaction();

    if ($payment_module->msp->orders->data->fastcheckout == "NO") {

        if ($payment_module->msp->details['paymentdetails']['type'] == "PAYAFTER") {
            $payment_module = new multisafepay_payafter();
            $payment_module->order_id = $_GET['transactionid'];
            $_SESSION['payment'] = 'multisafepay_payafter';
        }
    } else {
        $payment_module = new multisafepay_fastcheckout();
        $payment_module->order_id = $_GET['transactionid'];
        $_SESSION['payment'] = 'multisafepay_fastcheckout';
    }

    include_once("includes/languages/english/modules/payment/" . $_SESSION['payment'] . ".php");

    $GLOBALS[$_SESSION['payment']] = $payment_module;
    $GLOBALS[$_SESSION['payment']]->title = MODULE_PAYMENT_MULTISAFEPAY_TEXT_TITLE;
    $status = $payment_module->checkout_notify();
}


if ($_SESSION['customer_id']) {
    zen_redirect(zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
} else {
    zen_redirect(zen_href_link('index'));
}
?>

