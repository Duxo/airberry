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

        // self::log_request($request);

        exit;
    }

    private static function log_request(\WP_REST_Request $request)
    {
        $pluginlog = plugin_dir_path(__FILE__) . 'debug.log';

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
        error_log("=== WP REST Request ===\n" . $message . "\n", 3, $pluginlog);
    }

    public static function perform_sync()
    {
        $airtable_data = self::get_airtable_data(); // ['recXXXXX' => [data]]
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
                self::update_record($product, $data);
            }
        }

        // Create new products for remaining Airtable records
        foreach ($airtable_data as $id => $data) {
            $product = new WC_Product_Simple();
            self::update_record($product, $data);
        }
    }

    private static function update_record($product, $d)
    {
        $product->set_name($d->name);
        $product->set_price($d->price);
        $product->set_description($d->description);
        $product->set_meta_data([
            'time' => $d->time
        ]);
        $product->save();
    }

    private static function get_airtable_data(): array
    {
        $api_key = defined('AIRBERRY_API_KEY') ? AIRBERRY_API_KEY : 'the_key_is_missing';
        $base_id = 'appSZ5xnr8W0wI5tN';
        $table = 'tblHi0Fn7quCOwSFJ';

        $fields = [
            'fldmlebQ5td40INq6',
            'fld0infbLQmtiGaEH',
            'fldLjMVW7MgmC85Xb',
            'fldfEfr9bdZdv8ADH',
            'fldDPGRoce8ES0X4s',
            'fldiZgqzdrMJ00QWA',
            'fldQtoHZjVvA0Lmb0',
            'fldtU8mqRISmCw29z',
            'fldTIsRuxxk4lrd6O',
            'fldM1MgpPk3qfjdLR',
            'fld3ssxZ1B9A63KRe',
            'fldErzGjMLbDvDK5q',
            'fldgHmpQj7pm8kgT8'
        ];

        $query = http_build_query([
            'filterByFormula' => 'Stav = "Aktivní"',
            'fields' => $fields
        ]);

        $url = "https://api.airtable.com/v0/{$base_id}/{$table}?{$query}";

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
        if (!empty($json['records'])) {
            return [];
        }

        $data = [];

        foreach ($json['records'] as $record) {
            $fields = $record['fields'];
            $id = $record['id'];

            $photos = [];
            if (!empty($fields['Fotky'])) {
                foreach ($fields['Fotky'] as $photo) {
                    $photos[] = [
                        'id' => $photo['id'],
                        'url' => $photo['url'],
                        'filename' => $photo['filename'],
                    ];
                }
            }

            $des = '';

            $des .= isset($fields['Délka']) ? "Délka: {$fields['Délka']}\n" : '';
            $des .= isset($fields['Váha']) ? "Váha: {$fields['Váha']}\n" : '';
            $des .= isset($fields['Tmavost']) ? "Tmavost: {$fields['Tmavost']}\n" : '';
            $des .= isset($fields['Struktura']) ? "Struktura: {$fields['Struktura']}\n" : '';
            $des .= isset($fields['Jemnost']) ? "Jemnost: {$fields['Jemnost']}\n" : '';
            $des .= isset($fields['Původ']) ? "Původ: {$fields['Původ']}\n" : '';
            $des .= isset($fields['Cena za gram']) ? "Cena za gram: {$fields['Cena za gram']}\n" : '';
            $des .= isset($fields['Cena copu']) ? "Cena copu: {$fields['Cena copu']}\n" : '';
            $des .= isset($fields['Lesk']) ? "Lesk: {$fields['Lesk']}\n" : '';
            $des .= isset($fields['Stav copu']) ? "Stav copu: {$fields['Stav copu']}\n" : '';
            $des .= isset($fields['Poznámka']) && $fields['Poznámka'] !== '' ? "Poznámka: {$fields['Poznámka']}\n" : '';

            // If you want to trim off the last newline:
            $des = trim($des);

            $d = new stdClass();
            $d->name = $id;
            $d->time = $fields['Datum modifikace'];
            $d->description = $des;
            $d->photos = $photos;
            $data[$id] = $d;

            $d->cena_za_gram = $fields['Cena za gram'] ?? null;
            $d->delka = $fields['Délka'] ?? null;
            $d->vaha = $fields['Váha'] ?? null;
            $d->tmavost = $fields['Tmavost'] ?? null;
            $d->stav_copu = $fields['Stav copu'] ?? null;
            $d->struktura = $fields['Struktura'] ?? null;
            $d->jemnost = $fields['Jemnost'] ?? null;
            $d->puvod = $fields['Původ'] ?? null;
            $d->fotky = $photos;
            $d->cena_copu = $fields['Cena copu'] ?? 0;
            $d->lesk = $fields['Lesk'] ?? null;
            $d->poznamka = $fields['Poznámka'] ?? '';
            $d->datum_modifikace = $fields['Datum modifikace'] ?? $record['createdTime'];
        }


        return $data;
    }
}

ProductSync::init();