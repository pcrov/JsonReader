# JsonReader

[![Build Status](https://travis-ci.org/pcrov/JsonReader.svg?branch=master)](https://travis-ci.org/pcrov/JsonReader)
[![License](https://poser.pugx.org/pcrov/jsonreader/license)](https://github.com/pcrov/JsonReader/blob/master/LICENSE)
[![Latest Stable Version](https://poser.pugx.org/pcrov/jsonreader/v/stable)](https://packagist.org/packages/pcrov/jsonreader)

This is a streaming pull parser - like [XMLReader](http://php.net/xmlreader), but for JSON.

When you are dealing with documents large enough that memory becomes an issue that you'll want a streaming
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

Note: Only UTF-8 encoded JSON is supported.

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

---

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
     * Initializes the reader with the given parser.
     *
     * You do not need to call this if you're using one of json(), open(),
     * or stream() methods. It's intended to be used with manual
     * initialization of the parser, et al.
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
     * Initializes the reader with the given local or remote file URI.
     *
     * This convenience method handles creating the parser and relevant
     * dependencies.
     *
     * @param string $uri URI.
     * @return void
     * @throws IOException if a given URI is not readable.
     */
    public function open(string $uri);

    /**
     * Initializes the reader with the given file stream resource.
     *
     * This convenience method handles creating the parser and relevant
     * dependencies.
     *
     * @param resource $stream Readable file stream resource.
     * @return void
     * @throws InvalidArgumentException if a given resource is not a valid stream.
     * @throws IOException if a given stream resource is not readable.
     */
    public function stream($stream);

    /**
     * Type of the current node.
     *
     * @return int One of the JsonReader constants.
     */
    public function type() : int;

    /**
     * Name of the current node if any (for object properties.)
     *
     * @return string|null
     */
    public function name();

    /**
     * Value of the current node.
     *
     * For array and object nodes this will be evaluated on demand.
     *
     * Objects will be returned as arrays with strings for keys. Trying to
     * return stdClass objects would gain nothing but exposure to edge cases
     * where valid JSON produces property names that are not allowed in PHP
     * objects (e.g. "" or "\u0000".)
     *
     * Numbers will be returned as strings. The JSON specification places no
     * limits on the range or precision of numbers, and returning them as
     * strings allows you to handle them as you wish. For typical cases where
     * you'd expect an integer or float an automatic cast like
     * `$value = +$reader->value()` is sufficient, while in others you might
     * want to use [BC Math](http://php.net/bcmath) or [GMP](http://php.net/gmp).
     *
     * @return mixed
     */
    public function value();

    /**
     * Depth of the current node in the tree, starting at 0.
     *
     * @return int
     */
    public function depth() : int;

    /**
     * Move to the next node, skipping subtrees.
     *
     * If a name is given it will continue until a node of that name is
     * reached or the document ends.
     *
     * @param string|null $name
     * @return bool
     * @throws Exception
     */
    public function next(string $name = null) : bool;

    /**
     * Move to the next node.
     *
     * If a name is given it will continue until a node of that name is
     * reached or the document ends.
     *
     * @param string|null $name
     * @return bool
     * @throws Exception
     */
    public function read(string $name = null) : bool;

    /**
     * Close the parser.
     *
     * A file handle passed to JsonReader::stream() will not be closed by
     * calling this method. That is left to the caller.
     *
     * @return void
     */
    public function close();
}
```
