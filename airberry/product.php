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

    public function __construct($fields)
    {
        $this->name = $fields['Číslo'];
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
        
        $this->price = $fields['Cena copu'];

        $this->description = '';
        $this->description .= isset($fields['Délka']) ? "Délka: {$fields['Délka']}\n" : '';
        $this->description .= isset($fields['Na skladě']) ? "Váha: {$fields['Na skladě']}\n" : '';
        $this->description .= isset($fields['Tmavost']) ? "Tmavost: {$fields['Tmavost']}\n" : '';
        $this->description .= isset($fields['Struktura']) ? "Struktura: {$fields['Struktura']}\n" : '';
        $this->description .= isset($fields['Jemnost']) ? "Jemnost: {$fields['Jemnost']}\n" : '';
        $this->description .= isset($fields['Původ']) ? "Původ: {$fields['Původ']}\n" : '';
        $this->description .= isset($fields['Cena za gram']) ? "Cena za gram: {$fields['Cena za gram']}\n" : '';
        $this->description .= isset($fields['Lesk']) ? "Lesk: {$fields['Lesk']}\n" : '';
        $this->description .= isset($fields['Stav copu']) ? "Stav copu: {$fields['Stav copu']}\n" : '';
        $this->description .= isset($fields['Šediny']) ? "Šedinky: {$fields['Šediny']}\n" : '';
        $this->description .= isset($fields['Poznámka']) && $fields['Poznámka'] !== '' ? "Poznámka: {$fields['Poznámka']}\n" : '';
        $this->description = trim($this->description);
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