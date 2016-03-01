# JsonReader

[![Build Status](https://travis-ci.org/pcrov/JsonReader.svg?branch=master)](https://travis-ci.org/pcrov/JsonReader)
[![License](https://poser.pugx.org/pcrov/jsonreader/license)](https://github.com/pcrov/JsonReader/blob/master/LICENSE)
[![Latest Stable Version](https://poser.pugx.org/pcrov/jsonreader/v/stable)](https://packagist.org/packages/pcrov/jsonreader)

This is a streaming pull parser - like [XMLReader](http://php.net/xmlreader), but for JSON.

If you are not memory-limited you should be using [`json_decode()`](http://php.net/json_decode) as it is *significantly*
faster than parsing with user-land PHP.

It's when you are dealing with documents large enough that memory becomes an issue that you'll want a streaming
parser - either a pull parser like this, or a SAX-style push parser. For a good overview of the difference between push
and pull parsers see [*XML reader models: SAX versus XML pull parser*](http://www.firstobject.com/xml-reader-sax-vs-xml-pull-parser.htm) -
the article focuses on XML but the same concepts apply.

One other case for streaming parsers is if you've got some unusual JSON that includes duplicate names on an object's
properties. This is allowed by the JSON specification, but `json_decode()` (and the majority of other implementations)
will clobber properties as their keys collide. Streaming parsers allow you to access an element at a time and retrieve
data that might otherwise disappear.

## Requirements

PHP 7 and the Intl extension

## Installation

To install with composer:

```sh
composer require pcrov/jsonreader
```

## Usage

JsonReader's api and behavior is very much like [XMLReader](http://php.net/xmlreader). If you've worked with that then
this will feel familiar.

Note: this parser assumes UTF-8 encoded JSON but does not strictly enforce it.

---

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
    if ($reader->getNodeType() === JsonReader::NUMBER) {
        printf("%s: %d\n", $reader->getName(), $reader->getValue());
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

---

### Example 2

(With the JSON from example 1 in a file named "data.json".)

```php
use pcrov\JsonReader\JsonReader;

$reader = new JsonReader();
$reader->open("data.json");

while ($reader->read("type")) {
    echo $reader->getValue(), "\n";
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

### Class synopsis
```php
class JsonReader
{
    /* Node types */
    const NONE = 0;
    const STRING = 1;
    const NUMBER = 2;
    const BOOL = 3;
    const NULL = 4;
    const ARRAY = 5;
    const END_ARRAY = 6;
    const OBJECT = 7;
    const END_OBJECT = 8;
    
    /**
     * Close the parser.
     *
     * If a file handle was passed to JsonReader::open() it will not
     * be closed by calling this method. That is left to the caller.
     *
     * @return void
     */
    public function close();

    /**
     * Depth of the node in the tree, starting at 0.
     *
     * @return int
     */
    public function getDepth() : int;

    /**
     * Name of the current node if any (for object properties).
     *
     * @return string|null
     */
    public function getName();

    /**
     * Type of the current node.
     *
     * @return int One of the JsonReader constants.
     */
    public function getNodeType() : int;

    /**
     * Value of the current node.
     *
     * For array and object nodes this will be evaluated on demand.
     *
     * Objects will be returned as arrays with strings for keys. Trying to
     * return stdClass objects would gain nothing but exposure to edge cases
     * where valid JSON produces property names that are not allowed in PHP
     * objects (e.g. "" or "\u0000".) The behavior of json_decode() in these
     * cases is inconsistent and can introduce key collisions, so we'll not be
     * following its lead.
     *
     * @return mixed
     */
    public function getValue();

    /**
     * Initializes the reader with the given parser.
     *
     * You do not need to call this if you're using one of the json() or open()
     * methods.
     *
     * @param \Traversable $parser
     * @return void
     */
    public function init(\Traversable $parser);

    /**
     * Initializes the reader with the given JSON string.
     *
     * This convenience method handles creating the parser and relevant
     * dependencies.
     *
     * @param string $json
     * @return void
     */
    public function json(string $json);

    /**
     * Move to the next node, skipping subtrees.
     *
     * If a name is given it will continue until a node of that name is
     * reached.
     *
     * @param string|null $name
     * @return bool
     * @throws \pcrov\JsonReader\Exception
     */
    public function next(string $name = null) : bool;

    /**
     * Initializes the reader with the given file URI or handle.
     *
     * This convenience method handles creating the parser and relevant
     * dependencies.
     *
     * @param string|resource $file URI or file handle.
     * @return void
     * @throws IOException if a given file handle is not readable.
     */
    public function open($file);

    /**
     * Move to the next node.
     *
     * If a name is given it will continue until a node of that name is
     * reached.
     *
     * @param string|null $name
     * @return bool
     * @throws \pcrov\JsonReader\Exception
     */
    public function read(string $name = null) : bool
}
```
