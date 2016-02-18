<?php declare(strict_types = 1);

namespace pcrov\JsonReader;

use pcrov\JsonReader\InputStream\File;
use pcrov\JsonReader\InputStream\StringInput;
use pcrov\JsonReader\Parser\Parser;
use pcrov\JsonReader\Parser\Lexer;

/**
 * Class JsonReader
 * @package JsonReader
 */
class JsonReader implements NodeTypes
{
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
