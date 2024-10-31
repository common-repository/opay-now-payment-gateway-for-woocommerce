<?php

/** Front Side Global Content Print Start */

add_action('init', 'opaynow_wsppc_global_content_print_function');

function opaynow_wsppc_global_content_print_function()
{
    $wsppc_hooks = opaynow_wsppc_get_hook();

    if (!empty($wsppc_hooks)) {
        foreach ($wsppc_hooks as $key => $wsppc_hook) {
            if ($key == 'woocommerce_after_product_title') {
                add_action('woocommerce_single_product_summary', 'opaynow_wsppc_woocommerce_after_product_title', 5);
            } elseif ($key == 'woocommerce_after_product_price') {
                add_action('woocommerce_single_product_summary', 'opaynow_wsppc_woocommerce_after_product_price', 10);
            }
        }
    }
}

function opaynow_wsppc_woocommerce_after_product_title()
{
    $wsppc_hooks = opaynow_wsppc_get_hook();
    echo "<div class='nonoplazo_wsppc_div_block woocommerce_after_product_title'>";
    echo opaynow_wsppc_output($wsppc_hooks['woocommerce_after_product_title']);
    echo "</div>";
}

function opaynow_wsppc_woocommerce_after_product_price()
{
    $wsppc_hooks = opaynow_wsppc_get_hook();
    echo "<div class='nonoplazo_wsppc_div_block woocommerce_after_product_price'>";
    echo opaynow_wsppc_output($wsppc_hooks['woocommerce_after_product_price']);
    echo "</div>";
}

/** Front Side Global Content Print End */

/** front side sss */
function opaynow_wsppc_front_site_css_add()
{
    ?>
    <style>
        .nonoplazo_wsppc_div_block {
            display: inline-block;
            width: 100%;
            margin-top: 10px;
        }

        .opaynow_checkout {
            width: 100%;
            background: #00B876;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 1px;
            margin-bottom: 1px;
            text-align: center;
        }
    </style>
    <?php
}

add_action('wp_head', 'opaynow_wsppc_front_site_css_add');

/**
 * adding quick checkout funciton
 */
if (get_option('opaynow_quick_checkout')) {
    add_action('woocommerce_after_add_to_cart_button', 'opaynow_woocommerce_after_add_to_cart_form');
    //add_action('woocommerce_after_shop_loop_item', 'woocommerce_after_add_to_cart_form');
    function opaynow_woocommerce_after_add_to_cart_form()
    {
        global $product;
        $product_type = $product->get_type();
        if ($product->get_type() == "simple" || $product->get_type() == "variable") {
        echo do_shortcode('<input type="hidden" name="product_id" value="'.sanitize_text_field($product->get_id()).'">
                                    <input type="hidden" name="product_type" value="' . sanitize_text_field($product_type) . '">
                                    <div class="opaynow_checkout" >Pay with Opay Now</div>
                                    ');
        }
    }

    // add quick checkout ajax script
    add_action('init', 'opaynow_adding_ajax');
    function opaynow_adding_ajax()
    {
        wp_enqueue_script('opaynow-ajax-script',
            OPGFW_WC_OPAYNOW_URL . '/assets/js/direct_checkout.js',
            array('jquery'), OPAYNOW_BUILD, true
        );
        wp_localize_script('opaynow-ajax-script', 'opaynow_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php?action=opaynow_quick_checkout_action'),
        ));
    }

    // add quick checkout add to cart action
    add_action('wp_ajax_nopriv_opaynow_quick_checkout_action', 'opaynow_quick_checkout_action');
    add_action('wp_ajax_opaynow_quick_checkout_action', 'opaynow_quick_checkout_action');
    function opaynow_quick_checkout_action()
    {
        if (!isset($_POST['product_id'])) {
            echo "not added";
            exit;
        }

        $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
        $product = wc_get_product($product_id);
        $quantity = isset($_POST['quantity']) ? wc_stock_amount(wp_unslash(absint($_POST['quantity']))) : 1;
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        $product_status = get_post_status($product_id);
        $variation = array();
        if ($product && 'variable' === $product->get_type()) {
            foreach ($_POST as $key => $value) {
                if (strpos($key, "attribute_") === 0) {
                    $variation[$key] = $value;
                }
            }
        }
        if ('publish' != $product_status) {
            echo "not added";
            exit;
        }
        $wc = WC();
        $wc->cart->empty_cart();
        $wc->cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
        // 设置默认支付方式
        $wc->session->set('chosen_payment_method', 'opaynow');
        wp_safe_redirect( get_permalink(wc_get_page_id('checkout')));
        exit;
    }
}