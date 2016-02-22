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
class JsonReader
{
    const NONE = NodeType::NONE;
    const STRING = NodeType::STRING;
    const NUMBER = NodeType::NUMBER;
    const BOOL = NodeType::BOOL;
    const NULL = NodeType::NULL;
    const ARRAY = NodeType::ARRAY;
    const OBJECT = NodeType::OBJECT;

    /**
     * @var IteratorStackIterator|null
     */
    private $parser;

    /**
     * @var array[] Tuples from the parser, cached during tree building
     */
    private $parseCache = [];

    /**
     * @var int
     */
    private $nodeType = NodeType::NONE;

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
     * @return void
     */
    public function close()
    {
        $this->clear();
        $this->parser = null;
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
        $nodeType = $this->getNodeType();

        if ($this->value === null && ($nodeType === NodeType::ARRAY || $nodeType === NodeType::OBJECT)) {
            $this->value = $this->buildTree($nodeType);
            $this->parser->push(new \ArrayIterator($this->parseCache));
            $this->parseCache = [];
        }

        return $this->value;
    }

    /**
     * @param \Traversable $parser
     * @return void
     */
    public function init(\Traversable $parser)
    {
        $this->close();
        $iterator = new IteratorStackIterator();
        $iterator->push(new \IteratorIterator($parser));
        $iterator->rewind();
        $this->parser = $iterator;
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
        $parser = $this->parser;

        if ($parser === null) {
            throw new Exception("Load data before trying to read.");
        }

        $depth = $this->getDepth();
        while ($result = $this->read()) {
            if ($this->getDepth() <= $depth) {
                break;
            }
        }

        if ($name !== null) {
            do {
                if ($this->name === $name) {
                    break;
                }
            } while ($result = $this->next());
        }

        return $result;
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

        if (!$parser->valid()) {
            $this->clear();
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

        if ($this->nodeType === NodeType::END_ARRAY || $this->nodeType === NodeType::END_OBJECT) {
            $parser->next();
            return $this->read();
        }

        $parser->next();

        return true;
    }

    private function buildTree(int $type) : array
    {
        assert($type === NodeType::ARRAY || $type === NodeType::OBJECT);
        $parser = $this->parser;
        $end = ($type === NodeType::ARRAY) ? NodeType::END_ARRAY : NodeType::END_OBJECT;
        $result = [];

        while (true) {
            $current = $parser->current();
            $this->parseCache[] = $current;
            list ($type, $name, $value) = $current;
            $parser->next();

            if ($type === $end) {
                break;
            }

            if ($type === NodeType::ARRAY || $type === NodeType::OBJECT) {
                $value = $this->buildTree($type);
            }

            if ($name !== null) {
                $result[$name] = $value;
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function clear()
    {
        $this->nodeType = NodeType::NONE;
        $this->name = null;
        $this->value = null;
        $this->depth = 0;
    }
}
