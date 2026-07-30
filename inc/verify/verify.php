<?php
require_once('class.verify-purchase.php');
if (isset($_POST['docy_purchase_code'])){
    $purchase_code = htmlspecialchars($_POST['docy_purchase_code']);
    $o = EnvatoApi2::verifyPurchase( $purchase_code );
    if ( is_object($o) ) {
        echo 'verified';
    } else {
      return '';
    }
}