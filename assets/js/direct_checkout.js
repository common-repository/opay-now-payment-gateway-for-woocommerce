jQuery(document).ready(function ($) {
    jQuery(".opaynow_checkout").on('click', function (event) {
        let product_id = parseInt($('input[name="product_id"]').val());
        let product_type = $('input[name="product_type"]').val();
        let variation_id = $('input[name="variation_id"]').val();
        if (product_id === 0) {
            alert('Please select product');
            return;
        }
        if (product_type === "variable" && (variation_id == 0 || variation_id == "")) {
            alert('Please select some product options before checkout');
            return;
        }
        $('.cart').attr("action", opaynow_ajax_object.ajax_url).submit();
        event.preventDefault();
    });

});