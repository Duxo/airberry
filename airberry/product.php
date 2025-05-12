<?php

namespace AirBerry;

class ProductData
{
    public string $name;

    public string $price;

    public string $time;
    public string $description;
    public array $photos;

    public function __construct(string $name, string $price, string $time, string $description, array $photos)
    {
        $this->name = $name;
        $this->price = $price;
        $this->time = $time;
        $this->description = $description;
        $this->photos = $photos;
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