<?php declare(strict_types = 1);

namespace JsonReader;

use JsonReader\InputStream\File;
use JsonReader\InputStream\StringInput;
use JsonReader\Parser\Parser;
use JsonReader\Parser\Lexer;

/**
 * Class JsonReader
 * @package JsonReader
 */
class JsonReader
{
    /*
     * Duplicating the parser type constants here to give reader users a single, simple api.
     * They shouldn't concern themselves with the parser unless they want to.
     */
    const STRING = 1;
    const NUMBER = 2;
    const BOOL = 3;
    const NULL = 4;
    const ARRAY = 5;
    const END_ARRAY = 6;
    const OBJECT = 7;
    const END_OBJECT = 8;

    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param \Traversable $parser
     */
    public function init(\Traversable $parser)
    {
        $this->close();
        $this->parser = $parser;
    }

    /**
     * @return bool
     */
    public function close() : bool
    {
        $this->name = null;
        $this->nodeType = null;
        $this->value = null;
        $this->parser = null;
    }

    /**
     * @param string $json
     */
    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInput($json))));
    }

    /**
     * @param string|null $name
     * @return bool
     */
    public function next(string $name = null) : bool
    {

    }

    /**
     * @param string $uri
     */
    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new File($uri))));
    }

    /**
     * @return bool
     */
    public function read() : bool
    {

    }

    /**
     * @return int
     */
    public function getNodeType() : int
    {

    }

    /**
     * @return string
     */
    public function getName() : string
    {

    }

    /**
     * @return mixed
     */
    public function getValue()
    {

    }
}
