<?
if (!defined('MODULE_PAYMENT_MSP_APPLEPAY_STATUS') || MODULE_PAYMENT_MSP_APPLEPAY_STATUS == 'False') {
	return false;
}
?>

<script type='text/javascript'>

    $(document).ready(function() {
        try {
            if (!(window.ApplePaySession && window.ApplePaySession.canMakePayments())) {
                {
                    $('input[id="pmt-multisafepay_applepay"]').prevUntil( "label" ).hide();
                    $('input[id="pmt-multisafepay_applepay"]').hide();
                    $('label[for="pmt-multisafepay_applepay"]').hide();
                }
            }
        } catch (error) {
            console.warn('MultiSafepay error when trying to initialize Apple Pay:', error);
        }
    });
</script>
