<?php
require_once __DIR__ . '/LiqPay.php';
if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}
/** @var $commerce Commerce\Commerce */
$commerce = $modx->commerce;

switch ($modx->event->name) {
    case 'OnRegisterPayments':

        if (empty($params['title'])) {
            $lang = $commerce->getUserLanguage('liqpay');
            $params['title'] = $lang['liqpay.caption'];
        }

        $class = new \Commerce\Payments\LiqPay($modx, $params);
        $modx->commerce->registerPayment('liqpay', $params['title'], $class);


        break;
}