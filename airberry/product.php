<?php

namespace AirBerry;

// this is here to be close with the 
function get_request_fields($offset)
{
    $fields = [
        'Číslo',
        'Fotky',
        'Na skladě',
        'Cena',
        'Cena za gram',
        'Délka',
        'Tmavost',
        'Stav copu',
        'Struktura',
        'Jemnost',
        'Původ',
        'Odstín',
        'Lesk',
        'Šediny',
        'Sleva kadeřnice',
        'Sleva kadeřnice+',
        'Obecná sleva',
        'Povolit prodej na váhu',
        'Datum modifikace',
    ];

    return http_build_query([
        'filterByFormula' => '{Na skladě} > 0',
        'fields' => $fields,
        'offset' => $offset
    ]);
}


class ProductData
{
    public string $name;

    public string $price;

    public string $description;
    public array $photos;

    public bool $sell_by_weight;

    public string $time;

    public string $category;
    public array $tags = [];

    public function __construct($fields)
    {
        $this->name = 'Vlasy: ' . $fields['Číslo'];
        $this->photos = [];
        if (!empty($fields['Fotky'])) {
            foreach ($fields['Fotky'] as $photo) {
                $this->photos[] = new Image($photo['id'], $photo['url']);
            }
        }
        $this->sell_by_weight = false;
        if (isset($fields['Povolit prodej na váhu']) && $fields['Povolit prodej na váhu'] == "Ano") {
            $this->sell_by_weight = true;
        }

        $this->price = $fields['Cena'];

        $this->time = $fields['Datum modifikace'];

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
        $des .= isset($fields['Poznámka']) && $fields['Poznámka'] !== '' ? "Poznámka: {$fields['Poznámka']}\n" : '';
        $this->description = trim($des);

        $this->category = "Category1";
        $this->tags = ["tag1", "tag2"];
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