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
     * Close the JsonReader input.
     *
     * @return void
     */
    public function close()
    {
        $this->resetNode();
        $this->parser = null;
    }

    /**
     * Depth of the node in the tree, starting at 0.
     *
     * @return int
     */
    public function getDepth() : int
    {
        return $this->depth;
    }

    /**
     * Name of the current node if any (for object properties).
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Type of the current node.
     *
     * @return int One of the JsonReader constants.
     */
    public function getNodeType() : int
    {
        return $this->nodeType;
    }

    /**
     * Value of the current node.
     *
     * For array and object nodes this will be evaluated on demand.
     *
     * Objects will be returned as arrays with strings for keys. Trying to return stdClass objects would gain nothing
     * but exposure to edge cases where valid JSON produces property names that are not allowed in PHP objects (e.g. ""
     * or "\u0000".) The behavior of `json_decode()` in these cases is inconsistent and can introduce key collisions, so
     * we'll not be following its lead.
     *
     * @return mixed
     */
    public function getValue()
    {
        $nodeType = $this->getNodeType();

        if ($this->value === null && ($nodeType === self::ARRAY || $nodeType === self::OBJECT)) {
            $this->value = $this->buildTree($nodeType);
            $this->parser->push(new \ArrayIterator($this->parseCache));
            $this->parseCache = [];
        }

        return $this->value;
    }

    /**
     * Initializes the reader with the given parser.
     *
     * You do not need to call this if you're using one of the json() or open() methods.
     *
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
     * Initializes the reader with the given JSON string.
     *
     * This convenience method handles creating the parser and relevant dependencies.
     *
     * @param string $json
     * @return void
     */
    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInput($json))));
    }

    /**
     * Move to the next node, skipping subtrees.
     *
     * If a name is given it will continue until a node of that name is reached.
     *
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
     * Initializes the reader with the given file URI.
     *
     * This convenience method handles creating the parser and relevant dependencies.
     *
     * @param string $uri
     * @return void
     */
    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new File($uri))));
    }

    /**
     * Move to the next node.
     *
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
            $this->resetNode();
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

        if ($this->nodeType === self::END_ARRAY || $this->nodeType === self::END_OBJECT) {
            $parser->next();
            return $this->read();
        }

        $parser->next();

        return true;
    }

    /**
     * Builds a compound node recursively.
     *
     * @param int $type Must be self::ARRAY or self::OBJECT.
     * @return array
     */
    private function buildTree(int $type) : array
    {
        assert($type === self::ARRAY || $type === self::OBJECT);
        $parser = $this->parser;
        $end = ($type === self::ARRAY) ? self::END_ARRAY : self::END_OBJECT;
        $result = [];

        while (true) {
            $current = $parser->current();
            $this->parseCache[] = $current;
            list ($type, $name, $value) = $current;
            $parser->next();

            if ($type === $end) {
                break;
            }

            if ($type === self::ARRAY || $type === self::OBJECT) {
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

    /**
     * Resets the node to the initial state.
     *
     * @return void
     */
    private function resetNode()
    {
        $this->nodeType = self::NONE;
        $this->name = null;
        $this->value = null;
        $this->depth = 0;
    }
}
