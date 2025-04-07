<?php
/**
 * Plugin Name: AirBerry
 */

namespace AirBerry;

defined('ABSPATH') || exit;

class ProductSync
{
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_endpoint']);
    }

    public static function register_endpoint()
    {
        register_rest_route('airberry', 'sync', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_request'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_request(\WP_REST_Request $request)
    {
        // Let the script continue even if the client disconnects
        ignore_user_abort(true);

        // Close the connection immediately with 200 OK and no content
        header("Connection: close");
        http_response_code(200);
        header("Content-Length: 0");
        flush();

        // Continue in background
        self::perform_sync();

        exit;
    }

    public static function perform_sync()
    {
        // Delete all existing products
        $products = wc_get_products(['limit' => -1]);
        foreach ($products as $product) {
            wp_delete_post($product->get_id(), true);
        }

        /*
        // Fetch from Airtable
        $airtable_data = self::get_airtable_data();

        // Create new products
        foreach ($airtable_data as $item) {
            $product = new \WC_Product_Simple();
            $product->set_name($item['name']);
            $product->set_regular_price($item['price']);
            $product->set_description($item['description']);
            $product->save();
        }
        */
    }

    private static function get_airtable_data(): array
    {
	$api_key = defined('AIRBERRY_API_KEY') ? AIRBERRY_API_KEY : 'the_key_is_missing';
        $base_id = 'your_base_id';
        $table = 'Products';

        $url = "https://api.airtable.com/v0/{$base_id}/{$table}";
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ]
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        $data = [];

        if (!empty($json['records'])) {
            foreach ($json['records'] as $record) {
                $fields = $record['fields'];
                $data[] = [
                    'name' => $fields['Name'] ?? 'No Name',
                    'price' => $fields['Price'] ?? '0',
                    'description' => $fields['Description'] ?? '',
                ];
            }
        }

        return $data;
    }
}

ProductSync::init();