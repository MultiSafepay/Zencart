<?php

chdir("../../../../");
require("includes/application_top.php");
require("includes/modules/payment/multisafepay.php");

require(DIR_WS_CLASSES . "payment.php");

$payment_modules = new payment("multisafepay");
$payment_module = $GLOBALS[$payment_modules->selected_module];

require(DIR_WS_CLASSES . "order.php");

$order = new order($_GET['transactionid']);
$order_status_query = $db->Execute("SELECT orders_status_id FROM " . TABLE_ORDERS_STATUS . " WHERE orders_status_name = '" . $order->info['orders_status'] . "' AND language_id = '" . $_SESSION['languages_id'] . "'");

if(!is_null($order_status_query))
{
    $order_status = $order_status_query->fields['orders_status_id'];
    $order->info['order_status'] = $order_status['orders_status_id'];
}

require(DIR_WS_CLASSES . "order_total.php");
$order_total_modules = new order_total();

$customer_id = $order->customer['id'];
$order_totals = $order->totals;

$payment_module = new multisafepay();
$payment_module->order_id = $_GET['transactionid'];

//Pass Cancelled as the manual status
$transdata = $payment_module->checkout_notify('cancelled');

$messageStack->add_session('checkout_payment', "Payment was cancelled", 'caution');
zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT));
?>
