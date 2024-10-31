<?php
if (!defined('ABSPATH')) exit;

function opaynow_wsppc_get_hook()
{
    return get_option('opaynow_wsppc_hook');
}

function opaynow_wsppc_get_hook_value($hook)
{
    $all_hook = opaynow_wsppc_get_hook();
    return $all_hook[$hook];
}

function opaynow_wsppc_output($meta)
{
    if (empty($meta)) {
        return "";
    }
    if (trim($meta) == '') {
        return "";
    }
    global $product;
    $price = sprintf("%.2f", $product->get_price() / 8);
    // 替换价格,价格单位分
    $content = html_entity_decode(wp_unslash($meta));
    $content = str_replace("{{estimate.price}}", $price, $content);
    $content = str_replace("{{plugins_url}}", OPGFW_WC_OPAYNOW_URL, $content);
    // Output
    return do_shortcode($content);

}
