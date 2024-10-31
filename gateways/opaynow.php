<?php
/**
 * Plugin Name: Opay Now Payment Gateway for WooCommerce
 * Description: WooCommerce payment gateway
 * Version: least
 * Author: OpayNow,jianhui.zhu
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_OpayNow extends WC_Payment_Gateway
{

    /**
     * Logger instance
     *
     * @var WC_Logger
     */
    public static $log = false;

    /**
     * @var string 渠道
     */
    public static $channel = "woocommerce";

    /**
     * @var bool 测试模式
     */
    private $test_mode;

    /**
     * @var string 商户ID
     */
    private $merchant_id;

    /**
     * @var string 商户Token
     */
    private $merchant_token;

    /**
     * @var string 商户公约
     */
    private $merchant_public_key;

    /**
     * @var string 商户私约
     */
    private $merchant_private_key;

    /**
     * @var string 货币单位
     */
    private $currency;

    /**
     * @var String 展示支付到商品页面
     */
    private $single_product_page_display;

    /**
     * @var string 快捷支付
     */
    private $quick_checkout;

    /**
     * 订单支付超时时长
     *
     * @var int
     */
    private $payment_timeout = 10;

    /**
     * @var String 展示支付到商品页面的位置
     */
    private $single_product_page_display_postion = "woocommerce_after_product_price";

    public function __construct()
    {
        $this->id = 'opaynow';
        $this->icon = OPGFW_WC_OPAYNOW_URL . "/assets/images/logo.png";
        $this->has_fields = true;
        $this->method_title = 'OpayNow';
        $this->method_description = 'Use our plugin and start accepting our pay later checkout solution - Opay Now, which allows consumers to buy what they want now and pay for it later';
        $this->supports = array(
            'products',
            'refunds',
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->test_mode = 'yes' === $this->get_option('test_mode', 'no');
        $this->single_product_page_display = 'yes' === $this->get_option('single_product_page_display', 'no');
        $this->quick_checkout = 'yes' === $this->get_option('quick_checkout', 'no');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_token = $this->get_option('merchant_token');
        $this->merchant_public_key = $this->get_option('merchant_public_key');
        $this->merchant_private_key = $this->get_option('merchant_private_key');
        $this->currency = $this->get_option('currency');
        $this->payment_timeout = $this->get_option('payment_timeout', 10);

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_opaynow', array(&$this, 'thankyou_page'));

        // init Payment method for the frontend.
        // 根据支付类型获取配置
        //$opaynow_settings = (new OpayNow_Settings())->settings($this->get_option("front_description_style", "normal"));
        $opaynow_settings = (new OpayNow_Settings())->settings();
        $this->title = $opaynow_settings["title"];
        $this->description = $opaynow_settings["description"];
        $this->icon = OPGFW_WC_OPAYNOW_URL . $opaynow_settings["logo"];
        $single_product_page = $opaynow_settings["single_product_page"];
        $opaynow_wsppc_hook = array();
        if ($this->enabled == 'yes' && $this->single_product_page_display) {
            $hook = $this->single_product_page_display_postion;
            $opaynow_wsppc_hook[$hook] = htmlentities($single_product_page);
        }
        update_option('opaynow_wsppc_hook', $opaynow_wsppc_hook);
        update_option('opaynow_quick_checkout', $this->quick_checkout);
        //end init payment method for the frontend.
    }


    /**
     * Initialise Settings Form Fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woothemes'),
                'type' => 'checkbox',
                'label' => 'Open Opay Now Payment',
                'default' => 'no'
            ),

            'merchant_id' => array(
                'title' => 'Merchant AppID',
                'type' => 'text',
                'default' => '',
                'description' => 'The merchant AppID in merchant dashboard.',
                'custom_attributes' => array('required' => 'required'),
            ),
            'merchant_token' => array(
                'title' => 'Merchant App Token',
                'type' => 'text',
                'default' => '',
                'description' => 'The APP token in merchant dashboard.',
                'custom_attributes' => array('required' => 'required'),
            ),
            'merchant_public_key' => array(
                'title' => 'Merchant App Public Key',
                'type' => 'textarea',
                'default' => '',
                'description' => 'The APP Public Key in merchant dashboard.',
                'custom_attributes' => array('required' => 'required'),
            ),
            'merchant_private_key' => array(
                'title' => 'Merchant App Private Key',
                'type' => 'textarea',
                'default' => '',
                'description' => 'The APP Private Key in merchant dashboard.',
                'custom_attributes' => array('required' => 'required'),
            ),
            'currency' => array(
                'title' => 'Currency',
                'type' => 'select',
                'description' => 'Please select payment currency.',
                'default' => 'EGP',
                'options' => array(
                    'EGP' => 'EGP',
                ),
                'custom_attributes' => array('required' => 'required'),
            ),
            'single_product_page_display' => array(
                'title' => 'Display Opay Now in single product page',
                'label' => 'Enable',
                'type' => 'checkbox',
                'description' => 'Display Opay Now in single product detail page.',
                'default' => 'yes',
            ),
            /*            'front_description_style' => array(
                            'title' => 'Opay Now Display Style',
                            'type' => 'select',
                            'description' => 'Please select Opay Now display style',
                            'default' => 'normal',
                            'options' => array(
                                'normal' => 'Normal',
                                'sales' => "Sales",
                            ),
                            'custom_attributes' => array('required' => 'required'),
                        ),*/
            'quick_checkout' => array(
                'title' => 'Quick Checkout',
                'label' => 'Enable/Disable Quick Checkout',
                'type' => 'checkbox',
                'description' => 'You can control when to enable and disable functionality.',
                'default' => 'yes',
            ),
            'payment_timeout' => array(
                'title' => 'Order Expiry Time',
                'type' => 'select',
                'description' => 'Please select order expiry time.(Unit: minute)',
                'default' => '30',
                'options' => array(
                    '10' => '10',
                    '30' => '30',
                    '60' => '60',
                    '120' => '120',
                ),
                'custom_attributes' => array('required' => 'required'),
            ),
            'test_mode' => array(
                'title' => 'Test Mode',
                'label' => 'Enable Test Mode',
                'type' => 'checkbox',
                'description' => 'Helpful while testing, please uncheck if live.',
                'default' => 'yes',
            ),
        );
    }

    /**
     * payment page description
     *
     * @return string
     */
    public function thankyou_page()
    {
        if ($this->description) {
            return wpautop(wptexturize($this->description));
        }
    }

    /**
     * Process Payment.
     *
     * Process the payment. Override this in your gateway. When implemented, this should.
     * return the success and redirect in an array. e.g:
     *
     *        return array(
     *            'result'   => 'success',
     *            'redirect' => $this->get_return_url( $order )
     *        );
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order || !$order->needs_payment()) {
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        $this->log('order info: ' . $order);

        if ($this->test_mode) {
            $url = OPGFW_OPAYNOW_TEST_URL;
        } else {
            $url = OPGFW_OPAYNOW_URL;
        }

        // 订单货币是否允许被支付
        if ($order->get_currency() != $this->currency) {
            throw new Exception("Current payment type does not support this currency");
        }
        // 订单ID
        $outTradeNo = sprintf("WC-%s-%s", $this->merchant_id, $order_id);

        $orderInfo = $this->order_query($outTradeNo);
        if (!empty($orderInfo)) {
            if ($orderInfo["status"] == "PENDING" && $orderInfo['redirect'] != "") {
                return array(
                    'result' => 'success',
                    'redirect' => $orderInfo['redirect'],
                );
            } else {
                throw new Exception(sprintf("Transaction payment failed, current status is %s", $orderInfo["status"]));
            }
        }

        $processUrl = $url . "/v1/order/create";
        // Body
        $amount = $order->get_total();
        $goods = $this->get_order_goods($order);
        $callbackUrl = add_query_arg(array('wc-api' => 'opaynow_notify', "action" => "payment"), home_url('/'));

        $shipping = array(
            "receiverName" => $order->get_formatted_billing_full_name(),
            "receiverPhone" => $order->get_billing_phone(),
            //"country" => $order->get_billing_country(),
            "region" => $order->get_billing_state(),
            "city" => $order->get_billing_city(),
            "street" => "",
            "addressLine" => sprintf("%s %s", $order->get_billing_address_1(), $order->get_billing_address_2()),
            "postcode" => $order->get_billing_postcode(),
        );


        $data = [
            "outTradeNo" => $outTradeNo,
            "storeId" => $this->merchant_id,
            "storeName" => sprintf("WC-%s", $this->merchant_id),
            "subject" => "wc shop",
            "totalAmount" => intval($amount * 100),
            //"currency" => $order->get_currency(),
            "currency" => $this->currency,
            "client" => self::$channel,
            "timestamp" => time() * 1000,
            'notifyUrl' => $callbackUrl,
            'redirectUrl' => $this->get_return_url($order),
            'timeoutMinutes' => intval($this->payment_timeout),
            'shipping' => $shipping,
            'items' => $goods,
        ];

        $signature = $this->getSign(json_encode($data));

        $header = [
            'Content-Type' => 'application/json',
            'TOKEN' => $this->merchant_token,
            'SIGN' => $signature,
        ];
        try {
            $body = json_encode($data);
            $result = $this->safe_http_post($processUrl, $header, $body);
            if (is_wp_error($result)) {
                $this->log('Process payment Failed: ' . $result->get_error_message(), 'error');
                throw new Exception($result->get_error_message());
            }
            return array(
                'result' => 'success',
                'redirect' => $result->data->payUrl,
            );
        } catch (Exception $e) {
            $order->add_order_note(sprintf(__('Payment could not be created: %s'), $e->getMessage()));
            wc_add_notice("Payment Error:{$e->getMessage()}", 'error');
            return array(
                'result' => 'failure',
                'redirect' => $this->get_return_url($order)
            );
        }
    }

    /**
     * 查询订单
     *
     * @param $outTradeNo
     * @return array|null
     */
    private function order_query($outTradeNo)
    {

        if ($this->test_mode) {
            $url = OPGFW_OPAYNOW_TEST_URL;
        } else {
            $url = OPGFW_OPAYNOW_URL;
        }

        $processUrl = $url . "/v1/order/query";
        $data = [
            "outTradeNo" => $outTradeNo
        ];
        $signature = $this->getSign(json_encode($data));
        $header = [
            'Content-Type' => 'application/json',
            'TOKEN' => $this->merchant_token,
            'SIGN' => $signature,
        ];
        try {
            $body = json_encode($data);
            $result = $this->safe_http_post($processUrl, $header, $body);
            if (is_wp_error($result)) {
                $this->log('Order query Failed: ' . $result->get_error_message(), 'error');
                return null;
            }
            return array(
                'status' => $result->data->status,
                'redirect' => $result->data->payUrl,
            );
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 获取商品列表
     * @param WC_Order $order
     * @return array
     */
    public function get_order_goods($order)
    {
        $items = array();
        $order_items = $order->get_items("line_item");
        if (!empty($order_items)) foreach ($order_items as $item_id => $item) {
            $goods = array(
                "skuName" => $item["name"],
                "skuId" => $item["product_id"] . "",
                "unitPrice" => intval($item["subtotal"] * 100),
                "qty" => intval($item["quantity"]),
            );
            $items[] = $goods;
        }
        return $items;
    }


    /**
     *  safe http post
     * @param $url
     * @param $header
     * @param $body
     * @return array|object|WP_Error
     */
    public function safe_http_post($url, $header, $body)
    {
        $this->log('call api request url: ' . $url);
        $this->log('call api request header: ' . json_encode($header));
        $this->log('call api request body : ' . $body);
        $raw_response = wp_safe_remote_post(
            $url,
            array(
                'method' => 'POST',
                'body' => $body,
                'timeout' => 100,
                'user-agent' => 'WooCommerce/' . WC()->version,
                'httpversion' => '1.1',
                'headers' => $header,
            )
        );

        if (is_wp_error($raw_response)) {
            return $raw_response;
        } elseif (empty($raw_response['body'])) {
            return new WP_Error("error", 'Empty Response');
        }
        $this->log('call api response : ' . $raw_response['body']);
        $response = json_decode($raw_response['body']);
        if (!isset($response->code) || $response->code != "00000") {
            return new WP_Error('error', $response->message);
        }
        return $response;
    }

    /**
     * 生成签名
     * @param $signString
     * @return string
     */
    public function getSign($signString)
    {
        $privKeyId = openssl_pkey_get_private($this->getPrivateKey());
        openssl_sign(md5($signString), $signature, $privKeyId, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    /**
     * 验证签名
     * @param $signString
     * @param $sign
     * @return bool
     */
    public function checkSign($signString, $sign)
    {
        //$sign = $this->getSign($signString);
        //$this->log('sign : ' . $sign);
        $publicKeyId = openssl_pkey_get_public($this->getPublicKey());
        $result = openssl_verify(md5($signString), base64_decode($sign), $publicKeyId, OPENSSL_ALGO_SHA256);
        //$this->log('checkSign : ' . $result);
        return $result == 1;
    }

    /**
     * Process a refund if supported.
     *
     * @param int $order_id Order ID.
     * @param float $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if (!$this->can_refund_order($order)) {
            return new WP_Error('error', __('Refund failed.', 'woocommerce'));
        }
        if ($order->get_total() != $amount) {
            return new WP_Error('error', __('Refund failed.Only full refund is supported.', 'woocommerce'));
        }

        $result = $this->refund_transaction($order, $amount, $reason);
        if (is_wp_error($result)) {
            $order->add_order_note(sprintf(__('Refund initiated %s %s'), "failed", $result->message));
            $this->log('Refund Failed: ' . $result->get_error_message(), 'error');
            return new WP_Error('error', $result->get_error_message());
        }
        //$this->log('Refund Result: ' . wc_print_r($result, true));

        // 退款状态
        $isRefund = (isset($result->data->refundStatus) && $result->data->refundStatus == 'PENDING');
        $refundMessage = sprintf(__('Refund initiated %s'), ($isRefund ? "successfully" : "failed"));
        $order->add_order_note($refundMessage);
        return $isRefund ? true : new WP_Error('error', $refundMessage);
    }

    /**
     * Refund an order
     *
     * @param WC_Order $order Order object.
     * @param float $amount Refund amount.
     * @param string $reason Refund reason.
     * @return object Either an object of name value pairs for a success, or a WP_ERROR object.
     */
    private function refund_transaction($order, $amount, $reason = '')
    {
        if ($this->test_mode) {
            $url = OPGFW_OPAYNOW_TEST_URL;
        } else {
            $url = OPGFW_OPAYNOW_URL;
        }
        $refundUrl = $url . "/v1/order/refund";

        //调用订单ID
        $outTradeNo = sprintf("WC-%s-%s", $this->merchant_id, $order->get_id());
        $callbackUrl = add_query_arg(array('wc-api' => 'opaynow_notify', "action" => "refund"), home_url('/'));
        $data = [
            "outTradeNo" => $outTradeNo,
            "outRefundNo" => sprintf("WCR-%s-%s", $this->merchant_id, $order->get_id()),
            "storeId" => $this->merchant_id,
            "storeName" => sprintf("WC-%s", $this->merchant_id),
            "refundAmount" => intval($amount * 100),
            "currency" => $order->get_currency(),
            "refundReason" => $reason,
            "notifyUrl" => $callbackUrl,
        ];

        $signature = $this->getSign(json_encode($data));
        $header = [
            'Content-Type' => 'application/json',
            'TOKEN' => $this->merchant_token,
            'SIGN' => $signature,
        ];
        $body = json_encode($data);
        //$this->log('refund payment order request: ' . wc_print_r(["header" => $header, "body" => $data, "url" => $refundUrl], true));
        return $this->safe_http_post($refundUrl, $header, $body);
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level Optional. Default 'info'. Possible values:
     *                      emergency|alert|critical|error|warning|notice|info|debug.
     */
    public function log($message, $level = 'info')
    {
        if (empty(self::$log)) {
            self::$log = wc_get_logger();
        }
        self::$log->log($level, $message, array('source' => $this->id));
    }

    /**
     * 获取商户公钥
     * @return string
     */
    private function getPublicKey()
    {
        return "-----BEGIN PUBLIC KEY-----\n" . wordwrap($this->merchant_public_key, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
    }


    /**
     * 获取商户私钥
     * @return string
     */
    private function getPrivateKey()
    {
        return "-----BEGIN PRIVATE KEY-----\n" . wordwrap($this->merchant_private_key, 64, "\n", true) . "\n-----END PRIVATE KEY-----";
    }

}
