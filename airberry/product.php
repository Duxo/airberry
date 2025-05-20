<?php

namespace AirBerry;

class ProductData
{
    public string $name;

    public string $price;

    public string $description;
    public array $photos;

    public bool $sell_by_weight;

    public function __construct(string $name, string $price, string $description, array $photos, bool $sell_by_weight)
    {
        $this->name = $name;
        $this->price = $price;
        $this->description = $description;
        $this->photos = $photos;
        $this->sell_by_weight = $sell_by_weight;
    }
}

class Image {
    public string $id;
    public string $url;

    public function __construct(string $id, string $url) {
        $this->id = $id;
        $this->url = $url;
    }
}