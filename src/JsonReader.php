<?php declare(strict_types = 1);

namespace JsonReader;

//TODO: Almost everything.
class JsonReader
{
    const NONE = 0;
    const STRING = 1;
    const NUMBER = 2;
    const OBJECT = 3;
    const ARRAY = 4;
    const BOOL = 5;
    const NULL = 6;

    private $name; //string|null
    private $nodeType; //int
    private $value; //mixed

    private $parser;

    public function init(Parser $parser)
    {
        $this->name = null;
        $this->nodeType = null;
        $this->value = null;
        $this->parser = $parser;
    }

    public function close() : bool
    {

    }

    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInputStream($json))));
    }

    public function next(string $name = null) : bool
    {

    }

    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new FileInputStream($uri))));
    }

    public function read() : bool
    {

    }
}