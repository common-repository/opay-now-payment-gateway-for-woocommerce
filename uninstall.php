<?php
/**
 * Opay Now Uninstall
 *
 * Uninstalling Opay Now deletes pages, options.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
delete_option('opaynow_quick_checkout');
delete_option('opaynow_wsppc_hook');