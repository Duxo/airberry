<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

PriceCalculator::init();

class PriceCalculator
{
    public static function init()
    {
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'adjust_price'], 20, 1);
    }

    public static function adjust_price($cart)
    {

        if (is_admin() && !defined('DOING_AJAX'))
            return;

        foreach ($cart->get_cart() as $cart_item) {
            if (empty($cart_item['thwepof_options'])) {
                continue;
            }

            $added_price = 0;
            $praminkovani_value = $cart_item['thwepof_options']['praminkovani']['value'] ?? '';
            if ($praminkovani_value === 'ano') {
                $added_price += 31;
            }

            // Update product price
            $original_price = $cart_item['data']->get_price();
            $cart_item['data']->set_price($original_price + $added_price);
        }
    }

    private static function log($message)
    {
        $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        error_log($message . "\n", 3, $pluginlog);
    }
}

// Adjust cart item prices based on ThemeHigh Extra Product Options

