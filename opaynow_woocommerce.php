<?php
/**
 * Plugin Name: Opay Now Payment Gateway for WooCommerce
 * Description: WooCommerce payment gateway
 * Version: 1.1.0
 * Author: OpayNow
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    exit;
}
define("OPAYNOW_BUILD", "1.0.1");
define('OPGFW_OPAYNOW_ABSPATH', __DIR__ . '/');
define('OPGFW_WC_OPAYNOW_URL', plugins_url('', __FILE__));
define('OPGFW_OPAYNOW_TEST_URL', 'http://opay-egypt-app-payment-api-server.kong-hw-test.opayweb.com');
define('OPGFW_OPAYNOW_URL', 'https://cashier-paylater.opayeg.com');

function add_opaynow_gateway_class($methods)
{
    $methods[] = 'WC_Gateway_OpayNow';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_opaynow_gateway_class');

function init_opaynow_gateway_class()
{
    include_once 'gateways/opaynow.php';
}


add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'opaynow_settings_link');

/**
 * Add setting link on plugin page.
 *
 * @param int $links Default links array.
 */
function opaynow_settings_link($links)
{
    $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=opaynow');
    $links[] = "<a href='{$url}'>" . __('Settings') . '</a>';
    return $links;
}


add_action('plugins_loaded', 'init_opaynow_gateway_class');

// load front settings
require_once(OPGFW_OPAYNOW_ABSPATH . 'front/opaynow-settings.php');
// display product page
require_once(OPGFW_OPAYNOW_ABSPATH . 'front/functions.php');
require_once(OPGFW_OPAYNOW_ABSPATH . 'front/index.php');

//添加hook钩子，设置回调函数
add_action('woocommerce_api_opaynow_notify', 'opaynow_notify');
function opaynow_notify()
{
    $gateway = new WC_Gateway_OpayNow();
    //获取header信息
    $sign = !empty($_SERVER["HTTP_SIGN"]) ? sanitize_text_field($_SERVER["HTTP_SIGN"]) : "";
    $gateway->log("opaynow_notify | header : sign:$sign");
    if (empty($sign)) {
        new opaynow_response("20001");
    }
    $bodyStr = file_get_contents('php://input');
    $gateway->log("opaynow_notify | body : $bodyStr");

    $data = json_decode($bodyStr, true);

    // 获取通知类型
    $action = sanitize_text_field(isset($_GET['action']) ? $_GET['action'] : "");
    if (!in_array($action, ["payment", "refund"])) {
        new opaynow_response("20003");
    }
    // 支付订单号
    if (!isset($data['outTradeNo']) || empty($data['outTradeNo'])) {
        new opaynow_response("20002");
    }

    // 交易状态
    if (!isset($data['orderStatus']) && !isset($data['refundStatus'])) {
        new opaynow_response("20002");
    }

    // 根据插件验签
    try {
        $isOk = $gateway->checkSign($bodyStr, $sign);
    } catch (Exception $e) {
        $gateway->log("opaynow_notify | checkSign error :".$e->getMessage());
        $isOk = false;
    }
    if (!$isOk) {
        new opaynow_response("20005");
    }
    // 订单验证
    $payment_arr = explode('-', $data['outTradeNo']);
    $order_id = $payment_arr[2];
    $order = wc_get_order($order_id);
    if (!$order) {
        new opaynow_response("20004");
    }
    $gateway->log('order info: ' . $order);

    $noteMessage = "";
    // 支付回调
    if ($action == "payment" && $order->needs_payment()) {
        switch ($data["orderStatus"]) {
            case "SUCCESS":
                // 更新订单状态
                if (isset($data['orderNo'])) {
                    try {
                        $order->set_transaction_id($data['orderNo']);
                    } catch (Exception $e) {
                    }
                }
                $order->set_status("processing");
                $noteMessage = sprintf(__('Payment %s'), "successful");
                break;
            case "FAIL":
                $order->set_status("failed");
                $noteMessage = sprintf(__('Payment %s'), "failed");
                break;
            case "CLOSE":
                $order->set_status("failed");
                $noteMessage = sprintf(__('Payment %s'), "closed");
                break;
            default:
                $order->set_status("failed");
                $noteMessage = sprintf(__('Payment %s'), strtolower($data["orderStatus"]));
        }
        $order->save();
    }
    //退款回调
    if ($action == "refund") {
        switch ($data["refundStatus"]) {
            case "SUCCESS":
                $noteMessage = sprintf(__('Refund  %s'), "successful");
                break;
            default:
                $noteMessage = sprintf(__('Refund %s'), "failed");
        }
    }

    if ($noteMessage) {
        $order->add_order_note($noteMessage);
    }
    new opaynow_response("10000");
}

class opaynow_response
{
    const CODE_SUCCESS = 10000;
    const CODE_UNKNOWN_HEADER = 20001;
    const CODE_INVALID_PARAMETER = 20002;
    const CODE_INVALID_PAYMENT_METHOD = 20003;
    const CODE_UNKNOWN_ORDER = 20004;
    const CODE_SIGNATURE_ERROR = 20005;
    const CODE_ORDER_STATUS_ERROR = 20006;

    private static $messages = array(
        self::CODE_SUCCESS => "Success",
        self::CODE_UNKNOWN_HEADER => "Unknown Header",
        self::CODE_INVALID_PARAMETER => "Invalid Parameter",
        self::CODE_INVALID_PAYMENT_METHOD => "Invalid payment method",
        self::CODE_UNKNOWN_ORDER => "Unknown Order",
        self::CODE_SIGNATURE_ERROR => "Signature Error",
        self::CODE_ORDER_STATUS_ERROR => "Order status not pending",
    );

    public function __construct($code)
    {
        $response = array(
            "code" => intval($code),
            "message" => isset(self::$messages[$code]) ? self::$messages[$code] : "Unknown error",
        );
        ob_clean();
        echo wp_json_encode($response);
        exit();
    }
}