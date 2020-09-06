<?php

namespace Commerce\Payments;

class LiqPay extends Payment implements \Commerce\Interfaces\Payment
{
    /** @var $commerce \Commerce\Commerce */
    private $commerce;

    /** @var $commerce \DocumentParser */
    protected $modx;
    /**
     * @var \LiqPay
     */
    private $LiqPay;


    public function __construct(\DocumentParser $modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->commerce = $modx->commerce;
        $this->modx = $modx;
        $this->lang = $this->commerce->getUserLanguage('sberbank');
        $this->LiqPay = new \LiqPay($this->getSetting('public_key'), $this->getSetting('private_key'));

    }

    public function getMarkup()
    {
        if (empty($this->getSetting('public_key')) || empty($this->getSetting('private_key'))) {
            return '<span class="error" style="color: red;">' . $this->lang['liqpay.empty_keys'] . '</span>';
        }
        if ($this->getSetting('sandbox_mode') === 'yes') {
            return '<span class="error" style="color: red;">' . $this->lang['liqpay.sandbox_mode'] . '</span>';
        }
    }

    public function getPaymentMarkup()
    {

        $processor = $this->modx->commerce->loadProcessor();
        $order = $processor->getOrder();

        $amount = floatval($order['amount']);
        $payment = $this->createPayment($order['id'], $amount);

        $description = $this->modx->parseText($this->lang['liqpay.description_template'], [
            'order_id' => $order['id'],
            'payment_id' => $payment['id'],
        ]);

        $params = [
            'version' => 3,
            'public_key' => $this->getSetting('public_key'),
            'action' => 'pay',

            'amount' => $amount,
            'currency' => $order['currency'],

            'description' => $description,
            'language' => $this->lang['liqpay.lang_code'],

            'paytypes' => 'apay,gpay,card,liqpay,privat24,masterpass,qr',

            'result_url' => $this->modx->makeUrl($this->getSetting('redirect_after_payment'),'','','full'),
            'server_url' => $this->modx->getConfig('site_url') . 'commerce/liqpay/payment-process/?' . http_build_query([
                        'payment_hash' => $payment['hash'],
                    ]
                ),
        ];

        $formData = $this->LiqPay->cnb_form_raw($params);


        $view = new \Commerce\Module\Renderer($this->modx, null, [
            'path' => 'assets/plugins/commerce/templates/front/',
        ]);

        return $view->render('payment_form.tpl', [
            'url' => $formData['url'],
            'data' => $formData,
        ]);

    }

    public function handleCallback()
    {

        $data = $_POST['data'];
        $sign = base64_encode(sha1($this->getSetting('private_key') . $data . $this->getSetting('private_key'), 1));

        if ($sign !== $_POST['signature']) {
            return false;
        }
        $paymentHash = $_REQUEST['payment_hash'];
        $orderProcessor = $this->commerce->loadProcessor();

        $payment = $orderProcessor->loadPaymentByHash($paymentHash);


        if (empty($payment)) {
            return false;
        }

        $parsedData = json_decode(base64_decode($data), true);
        if ($parsedData['status'] !== 'success') {
            return false;
        }

        try {
            $orderProcessor->processPayment($payment['id'], $parsedData['amount']);
        } catch (\Exception $e) {
            $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Paymaster Payment');
            return false;
        }
        return true;
    }



}
