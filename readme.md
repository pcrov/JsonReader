# JsonReader

[![Build Status](https://travis-ci.org/pcrov/JsonReader.svg?branch=master)](https://travis-ci.org/pcrov/JsonReader)
[![License](https://poser.pugx.org/pcrov/jsonreader/license)](https://github.com/pcrov/JsonReader/blob/master/LICENSE)
[![Latest Stable Version](https://poser.pugx.org/pcrov/jsonreader/v/stable)](https://packagist.org/packages/pcrov/jsonreader)

This is a streaming pull parser - like [XMLReader](http://php.net/xmlreader) but for JSON.

## Requirements

PHP 7 and the Intl extension

## Installation

To install with composer:

```sh
composer require pcrov/jsonreader
```

## API

For API documentation see [the wiki](https://github.com/pcrov/JsonReader/wiki/JsonReader-API).

## Basic Usage

JsonReader's interface and behavior is very much like [XMLReader](http://php.net/xmlreader). If you've worked with that then
this will feel familiar.

### Example 1
```php
use pcrov\JsonReader\JsonReader;

$json = <<<'JSON'
{
    "type": "donut",
    "name": "Cake",
    "toppings": [
        { "id": 5002, "type": "Glazed" },
        { "id": 5006, "type": "Chocolate with Sprinkles" },
        { "id": 5004, "type": "Maple" }
    ]
}
JSON;

$reader = new JsonReader();
$reader->json($json);

while ($reader->read()) {
    if ($reader->type() === JsonReader::NUMBER) {
        printf("%s: %d\n", $reader->name(), $reader->value());
    }
}
$reader->close();
```
Output:
```
id: 5002
id: 5006
id: 5004
```


### Example 2

(With the JSON from example 1 in a file named "data.json".)

```php
use pcrov\JsonReader\JsonReader;

$reader = new JsonReader();
$reader->open("data.json");

while ($reader->read("type")) {
    echo $reader->value(), "\n";
}
$reader->close();
```
Output:
```
donut
Glazed
Chocolate with Sprinkles
Maple
```

---

## Note

Only UTF-8 encoded JSON is supported. If you need to parse JSON in another encoding you can use an
[iconv](http://php.net/iconv) stream filter to transcode it to UTF-8 on the fly.
