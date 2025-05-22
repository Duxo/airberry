<?php

namespace AirBerry;

// this is here to be close with the 
function get_request_fields($offset)
{
    $fields = [
        // technical data
        'Číslo',
        'Fotky',
        'Cena',
        'Cena za gram',
        'Sleva kadeřnice',
        'Sleva kadeřnice+',
        'Obecná sleva',
        'Povolit prodej na váhu',
        'Datum modifikace',

        // data for only filtering
        'Na skladě',
        'Délka',
        'Tmavost',
        'Stav copu',
        'Struktura',
        'Jemnost',
        'Původ',
        'Odstín',
        'Lesk',
        'Šediny',
    ];

    return http_build_query([
        'filterByFormula' => '{Na skladě} > 0',
        'fields' => $fields,
        'offset' => $offset
    ]);
}


class ProductData
{
    // these are important for later use
    public bool $sell_by_weight;
    public int $price;
    public int $price_per_gram;
    public float $discount1; // kadeřnice
    public float $discount2; // kadeřnice+
    public float $discount3; // general discount
    public string $time; // can be string because it is tested against exact match

    // these are used just once
    public string $name;
    public array $photos;
    public array $meta_keys; // all other meta keys (for filtering)
    public string $description;

    public function __construct($fields)
    {
        $this->sell_by_weight = false;
        if (isset($fields['Povolit prodej na váhu']) && $fields['Povolit prodej na váhu'] == "Ano") {
            $this->sell_by_weight = true;
        }
        $this->price = $fields['Cena'];
        $this->price_per_gram = $fields['Cena za gram'];
        $this->discount1 = $fields['Sleva kadeřnice'];
        $this->discount2 = $fields['Sleva kadeřnice+'];
        $this->discount3 = $fields['Obecná sleva'];
        $this->time = $fields['Datum modifikace'];

        $this->name = 'Vlasy: ' . $fields['Číslo'];
        $this->photos = [];
        if (!empty($fields['Fotky'])) {
            foreach ($fields['Fotky'] as $photo) {
                $this->photos[] = new Image($photo['id'], $photo['url']);
            }
        }


        // TODO set the rest of the fields

        

        $des = '';
        $des .= isset($fields['Délka']) ? "Délka: {$fields['Délka']}\n" : '';
        $des .= isset($fields['Na skladě']) ? "Váha: {$fields['Na skladě']}\n" : '';
        $des .= isset($fields['Tmavost']) ? "Tmavost: {$fields['Tmavost']}\n" : '';
        $des .= isset($fields['Struktura']) ? "Struktura: {$fields['Struktura']}\n" : '';
        $des .= isset($fields['Jemnost']) ? "Jemnost: {$fields['Jemnost']}\n" : '';
        $des .= isset($fields['Původ']) ? "Původ: {$fields['Původ']}\n" : '';
        $des .= isset($fields['Cena za gram']) ? "Cena za gram: {$fields['Cena za gram']}\n" : '';
        $des .= isset($fields['Lesk']) ? "Lesk: {$fields['Lesk']}\n" : '';
        $des .= isset($fields['Stav copu']) ? "Stav copu: {$fields['Stav copu']}\n" : '';
        $des .= isset($fields['Šediny']) ? "Šedinky: {$fields['Šediny']}\n" : '';
        $des .= isset($fields['Odstín']) ? "Odstín: {$fields['Odstín']}\n" : '';
        $des .= isset($fields['Poznámka']) && $fields['Poznámka'] !== '' ? "Poznámka: {$fields['Poznámka']}\n" : '';
        $this->description = trim($des);
    }
}

class Image
{
    public string $id;
    public string $url;

    public function __construct(string $id, string $url)
    {
        $this->id = $id;
        $this->url = $url;
    }
}