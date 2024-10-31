<?php
/**
 * Class OpayNow Settings
 */
// title:前端支付方式名称
// description:前端支付方式描述
// single_product_page 单个商品页面支付暴露
class OpayNow_Settings {
    // 通用版本语言配置
    private static $normal_config = array(
        "logo" => "/assets/images/logo.png",
        "title" => 'Opay Now',
        "description" => "Enjoy up to 8 installments and support multiple installment modes",
        "single_product_page" => 'Enjoy up to <span style="color:#007B3D">8</span>  installments and more installments, with the amount of <span style="color:#007B3D">{{estimate.price}}</span> per installment <img src="{{plugins_url}}/assets/images/logo.png" width="100px">',
    );

    // 营销版本语言配置
    private static $sales_config = array(
        "logo" => "/assets/images/logo.png",
        "title" => 'Opay Now',
        "description" =>"Enjoy up to 8 installments and support multiple installment modes",
        "single_product_page" => 'Enjoy up to <span style="color:#007B3D">8</span>  installments and more installments, with the amount of <span style="color:#007B3D">{{estimate.price}}</span> per installment <img src="{{plugins_url}}/assets/images/logo.png" width="100px">',
    );

    /**
     * 根据类型获取配置
     * @param string $setting_type 根据类型获取配置信息
     */
    public function settings($setting_type = "normal") {
        if ($setting_type == "sales") {
            return self::$sales_config;
        }
        return self::$normal_config;
    }
}