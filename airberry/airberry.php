<?php

namespace AirBerry;

defined('ABSPATH') || exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once 'product.php';
require_once 'logger.php';

ProductSync::init();

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
        $logger = new Logger();

        ignore_user_abort(true);
        header("Connection: close");
        http_response_code(200);
        header("Content-Length: 0");
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // == Continue in background ==

        // self::delete_all();
        self::perform_sync($logger);
        $logger->stop();
        self::log($logger->__tostring());
        // self::log_request($request);

        exit;
    }

    private static function delete_all()
    {
        $products = wc_get_products(['limit' => -1]);

        foreach ($products as $product) {
            wp_delete_post($product->get_id(), true);
        }
    }


    public static function perform_sync(Logger $logger)
    {
        $airtable_data = self::get_airtable_data($logger);

        $products = wc_get_products(['limit' => -1]);

        foreach ($products as $product) {
            $id = $product->get_name();

            if (!isset($airtable_data[$id])) {
                wp_delete_post($product->get_id(), true);
                continue;
            }

            $data = $airtable_data[$id];
            unset($airtable_data[$id]);
            if ($data->time !== $product->get_meta('time', true)) {
                self::update_record($product, $data, $logger);
            }
        }

        // Create new products for remaining Airtable records
        foreach ($airtable_data as $id => $data) {
            $product = new \WC_Product_Simple();
            self::update_record($product, $data, $logger);
        }
    }

    private static function update_record($product, $data, $logger)
    {
        $product->set_name($data->name);
        $product->set_price($data->price);
        $product->set_regular_price($data->price);
        $product->set_description($data->description);

        if (count($data->photos) != 0) {
            $photo = $data->photos[0];
            $image_id = self::get_or_download_image($photo->id, $photo->url, $logger);
            if ($image_id) {
                $product->set_image_id($image_id);
            }
        }

        $product->save();
    }

    private static function get_or_download_image($photo_id, $url, $logger)
    {
        $existing = get_page_by_title($photo_id, OBJECT, 'attachment');

        if ($existing) {
            return $existing->ID;
        }

        $logger->api_calls++;
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            return 0;
        }

        $file_array = [
            'name' => $photo_id . '.png',
            'tmp_name' => $tmp,
        ];

        // Upload the file into the media library
        $id = media_handle_sideload($file_array, 0);

        // Cleanup temp file
        @unlink($tmp);

        if (is_wp_error($id)) {
            error_log('Image upload failed: ' . $id->get_error_message());
            return 0;
        }

        // Rename title of attachment for future checking
        wp_update_post([
            'ID' => $id,
            'post_title' => $photo_id,
        ]);

        return $id;
    }


    private static function get_airtable_data(Logger $logger): array
    {
        $api_key = defined('AIRBERRY_API_KEY') ? AIRBERRY_API_KEY : 'the_key_is_missing';
        $base_id = 'appZPDCjfJhFvNq09';
        $table = 'tblYnNFl6HFBkyttV';
        $fields = [
            'fldU1Raf5P6THroHu', // Číslo
            'fldaNfRswOv3RtOU0', // Fotky
            'fldn7zGh4f1dVnRNJ', // Na skladě
            'fldDq1bO4Ko3wKoei', // Cena za gram
            'fldhnaf9K7xsOILsT', // Délka
            'fldwJ2r7auac1abrT', // Tmavost
            'fldUUtRmbvjDo2ySE', // Stav copu
            'fldz43qxcIXIw2rKM', // Struktura
            'fld7ybHXicGzwNXZc', // Jemnost
            'fldKZVmoQZ3l8yDXL', // Původ
            'fldEyuH0Gegf698sA', // Odstín
            'fldkxfxX0SkzC5lFq', // Lesk
            'fldJ26gtk8AOxhVgP', // Šediny
            'fld4jVXlkdTTVgSsG', // Sleva kadeřnice
            'fld5MySAm1Fe5Sg6O', // Sleva kadeřnice+
            'fld4pALElQ0nEb41N', // Obecná sleva
            'fldVkVbGhNhANjhZC', // Povolit prodej na váhu
        ];

        $offset = "";
        $all = [];
        do{
            [$data, $offset] = self::get_airtable_data_page($logger, $api_key, $base_id, $table, $fields, $offset);
            $all = array_merge($all, $data);
        }while($offset != "");
        
        return $all;
    }

    private static function get_airtable_data_page(Logger $logger, $api_key, $base_id, $table, $fields, $offset)
    {
         $query = http_build_query([
            'filterByFormula' => 'Na skladě > 0',
            'fields' => $fields,
            'offset' => $offset
        ]);

        $url = "https://api.airtable.com/v0/{$base_id}/{$table}?{$query}";

        $logger->api_calls++;
        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ]
        ]);

        if (is_wp_error($response)) {
            self::log('api call error: ' . $response->get_error_message());
            return [[], ""];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (empty($json['records'])) {
            self::log('there are no records');
            self::log($json);
            return [[], ""];
        }

        $data = [];
        $offset = $json['offset'] ?? "";

        foreach ($json['records'] as $record) {
            $fields = $record['fields'];
            
            $id = $fields['Číslo'];
            $sell_by_weight = false;
            if (isset($fields["Povolit prodej na váhu"]) && $fields["Povolit prodej na váhu"]=="Ano"){
                $sell_by_weight = true;
            }

            $photos = [];
            if (!empty($fields['Fotky'])) {
                foreach ($fields['Fotky'] as $photo) {
                    $photos[] = new Image($photo['id'], $photo['url']);
                }
            }

            $des = '';

            $des .= isset($fields['Délka']) ? "Délka: {$fields['Délka']}\n" : '';
            $des .= isset($fields['Na skladě']) ? "Váha: {$fields['Váha']}\n" : '';
            $des .= isset($fields['Tmavost']) ? "Tmavost: {$fields['Tmavost']}\n" : '';
            $des .= isset($fields['Struktura']) ? "Struktura: {$fields['Struktura']}\n" : '';
            $des .= isset($fields['Jemnost']) ? "Jemnost: {$fields['Jemnost']}\n" : '';
            $des .= isset($fields['Původ']) ? "Původ: {$fields['Původ']}\n" : '';
            $des .= isset($fields['Cena za gram']) ? "Cena za gram: {$fields['Cena za gram']}\n" : '';
            $des .= isset($fields['Lesk']) ? "Lesk: {$fields['Lesk']}\n" : '';
            $des .= isset($fields['Stav copu']) ? "Stav copu: {$fields['Stav copu']}\n" : '';
            $des .= isset($fields['Šediny']) ? "Šedinky: {$fields['Šediny']}\n" : '';
            $des .= isset($fields['Poznámka']) && $fields['Poznámka'] !== '' ? "Poznámka: {$fields['Poznámka']}\n" : '';
            $des = trim($des);

            $data[$id] = new ProductData($id, $fields['Cena copu'], $des, $photos, $sell_by_weight );
        }
        return [$data, $offset];
    }

    private static function log($message)
    {
        $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';
        if (!is_string($message)) {
            $message = print_r($message, true);
        }
        error_log($message . "\n", 3, $pluginlog);
    }

    private static function log_request(\WP_REST_Request $request)
    {
        $method = $request->get_method();
        $route = $request->get_route();
        $params = $request->get_params();
        $headers = $request->get_headers();
        $body = $request->get_body();

        $log_data = [
            'Method' => $method,
            'Route' => $route,
            'Params' => $params,
            'Headers' => $headers,
            'Body' => $body,
        ];

        $message = print_r($log_data, true);
        self::log("=== WP REST Request ===\n" . $message . "\n");
    }
}
