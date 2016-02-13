<?php declare(strict_types = 1);

namespace JsonReader;

//TODO: Everything.
class Parser
{
    private $lexer;

    public function __construct(\Traversable $lexer)
    {
        $this->lexer = $lexer;
    }
}