<?php declare(strict_types = 1);

namespace pcrov\JsonReader;

use pcrov\IteratorStackIterator;
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
     * @var \IteratorIterator|null
     */
    private $parser;

    /**
     * @var int
     */
    private $nodeType = self::NONE;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @return bool
     */
    public function close() : bool
    {
        $this->parser = null;
        $this->nodeType = self::NONE;
        $this->name = null;
        $this->value = null;
        $this->depth = 0;
    }

    /**
     * @return int
     */
    public function getDepth() : int
    {
        return $this->depth;
    }

    /**
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getNodeType() : int
    {
        return $this->nodeType;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        $type = $this->getNodeType();
        $value = $this->value;

        if ($value === null && ($type === self::ARRAY || $type === self::OBJECT)) {
            $value = $this->buildTree();
            $this->value = $value;
        }

        return $value;
    }

    /**
     * @param \Traversable $parser
     * @return void
     */
    public function init(\Traversable $parser)
    {
        $this->close();
        $parser = new \IteratorIterator($parser);
        $parser->rewind();
        $this->parser = $parser;
    }

    /**
     * @param string $json
     * @return void
     */
    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInput($json))));
    }

    /**
     * @param string|null $name
     * @return bool
     * @throws Exception
     */
    public function next(string $name = null) : bool
    {
        if ($this->parser === null) {
            throw new Exception("Load data before trying to read.");
        }

    }

    /**
     * @param string $uri
     * @return void
     */
    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new File($uri))));
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function read() : bool
    {
        $parser = $this->parser;

        if ($parser === null) {
            throw new Exception("Load data before trying to read.");
        }

        $parser->next();
        if (!$parser->valid()) {
            return false;
        }

        //@formatter:off silly ide
        list (
            $this->nodeType,
            $this->name,
            $this->value,
            $this->depth
        ) = $parser->current();
        //@formatter:on

        return true;
    }

    /**
     * @return array
     */
    private function buildTree() : array
    {
        //todo: actually build the tree
        return [];
    }
}
