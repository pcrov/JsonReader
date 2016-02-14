<?php declare(strict_types = 1);

namespace JsonReader;

use JsonReader\InputStream\File;
use JsonReader\InputStream\StringInput;
use JsonReader\Parser\JsonParser;
use JsonReader\Parser\Lexer;

//TODO: Almost everything.
/**
 * Class JsonReader
 * @package JsonReader
 * @property-read int $nodeType
 * @property-read string $name
 * @property-read mixed $value
 */
class JsonReader
{
    const NONE = 0;
    const STRING = 1;
    const NUMBER = 2;
    const OBJECT = 3;
    const ARRAY = 4;
    const BOOL = 5;
    const NULL = 6;


    /**
     * @var Parser
     */
    private $parser;

    /**
     * @param Parser $parser
     */
    public function init(Parser $parser)
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
        $this->init(new JsonParser(new Lexer(new StringInput($json))));
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
        $this->init(new JsonParser(new Lexer(new File($uri))));
    }

    /**
     * @return bool
     */
    public function read() : bool
    {

    }

    /**
     * @internal
     *
     * We're using this instead of proper getters *only* to maintain api similarity with XMLReader.
     *
     * @param $name
     */
    public function __get($name)
    {
        // TODO: Implement __get() method.
    }
}