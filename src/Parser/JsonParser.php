<?php declare(strict_types = 1);

namespace JsonReader\Parser;

use JsonReader\Parser;

//TODO: Everything.
class JsonParser implements \IteratorAggregate, Parser
{
    private $lexer;

    public function __construct(\Traversable $lexer)
    {
        $this->lexer = $lexer;
    }

    public function getIterator()
    {
        // TODO: Implement getIterator() method.
    }

    public function getValue()
    {
        // TODO: Implement getValue() method.
    }
}