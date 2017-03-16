<?php
if (MODULE_PAYMENT_MULTISAFEPAY_FCO_STATUS == 'True') {
    ?>
    <div style="float:left;">
        <pre>
        </pre>
    </div>
    <div class="clear:both"></div>
    <!-- BEGIN MSP CHECKOUT -->
    <div align="right">
        <div style="width: 220px; margin-top:15px; margin-bottom:5px;">
            <div align="center">
                <?php
                if ($GLOBALS['_SESSION']['cart']->contents) {
                    if (MODULE_PAYMENT_MULTISAFEPAY_FCO_BTN_COLOR == 'Orange') {
                        echo '- Or -<br/><br/><a href="mspcheckout/process.php"><img src="mspcheckout/images/fcobutton-orange.png" alt="Checkout" name="Checkout"></a>';
                    } else {
                        echo '- Or -<br/><br/><a href="mspcheckout/process.php"><img src="mspcheckout/images/fcobutton-black.png" alt="Checkout" name="Checkout"></a>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <?php
    // display any MSP error

    if (isset($HTTP_GET_VARS['payment_error']) && is_object(${$HTTP_GET_VARS['payment_error']}) && ($error = ${$HTTP_GET_VARS['payment_error']}->get_error())) {
        var_dump('error in mspcheckout.php line 35');
        exit;
        ?>
        <table border="0" width="100%" cellspacing="0" cellpadding="2">
            <tr>
                <td class="main"><b><?php echo tep_output_string_protected($error['title']); ?></b></td>
            </tr>
        </table>

        <table border="0" width="100%" cellspacing="1" cellpadding="2" class="infoBoxNotice">
            <tr class="infoBoxNoticeContents">
                <td><table border="0" width="100%" cellspacing="0" cellpadding="2">
                        <tr>
                            <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                            <td class="main" width="100%" valign="top"><?php echo tep_output_string_protected($error['error']); ?></td>
                            <td><?php echo tep_draw_separator('pixel_trans.gif', '10', '1'); ?></td>
                        </tr>
                    </table></td>
            </tr>
        </table>

        <?php
    }
    ?>

    <!-- END MSP CHECKOUT -->
    <?php
}
?>