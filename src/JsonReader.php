<?php declare(strict_types = 1);

namespace pcrov\JsonReader;

use pcrov\IteratorStackIterator;
use pcrov\JsonReader\InputStream\IOException;
use pcrov\JsonReader\InputStream\Stream;
use pcrov\JsonReader\InputStream\Uri;
use pcrov\JsonReader\InputStream\StringInput;
use pcrov\JsonReader\Parser\Parser;
use pcrov\JsonReader\Parser\Lexer;

/**
 * Class JsonReader
 * @package JsonReader
 */
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
     * @var IteratorStackIterator|null
     */
    private $parser;

    /**
     * @var array[] Tuples from the parser, cached during tree building.
     */
    private $parseCache = [];

    /**
     * @var int
     */
    private $type = self::NONE;

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
     * Initializes the reader with the given parser.
     *
     * You do not need to call this if you're using one of json(), open(),
     * or stream() methods. It's intended to be used with manual
     * initialization of the parser, et al.
     *
     * @param \Traversable $parser
     * @return void
     */
    public function init(\Traversable $parser)
    {
        $this->close();
        $stack = new IteratorStackIterator();
        $stack->push(new \IteratorIterator($parser));
        $stack->rewind();
        $this->parser = $stack;
    }

    /**
     * Initializes the reader with the given JSON string.
     *
     * This convenience method handles creating the parser and relevant
     * dependencies.
     *
     * @param string $json
     * @return void
     */
    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInput($json))));
    }

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
    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new Uri($uri))));
    }

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
    public function stream($stream)
    {
        $this->init(new Parser(new Lexer(new Stream($stream))));
    }

    /**
     * Type of the current node.
     *
     * @return int One of the JsonReader constants.
     */
    public function type() : int
    {
        return $this->type;
    }

    /**
     * Name of the current node if any (for object properties.)
     *
     * @return string|null
     */
    public function name()
    {
        return $this->name;
    }

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
    public function value()
    {
        $type = $this->type();

        if ($this->value === null && ($type === self::ARRAY || $type === self::OBJECT)) {
            $this->value = $this->buildTree($type);
            $this->parser->push(new \ArrayIterator($this->parseCache));
            $this->parseCache = [];
        }

        return $this->value;
    }

    /**
     * Depth of the current node in the tree, starting at 0.
     *
     * @return int
     */
    public function depth() : int
    {
        return $this->depth;
    }

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
    public function next(string $name = null) : bool
    {
        if ($this->parser === null) {
            throw new Exception("Load data before trying to read.");
        }

        $depth = $this->depth();
        $end = $this->getEndType($this->type());

        while ($result = $this->read()) {
            if ($this->depth() <= $depth) {
                break;
            }
        }

        // If we were on an object or array when called, we want to skip its end node.
        if ($end !== self::NONE &&
            $this->depth() === $depth &&
            $this->type() === $end
        ) {
            $result = $this->read();
        }

        if ($name !== null) {
            do {
                if ($this->name() === $name) {
                    break;
                }
            } while ($result = $this->next());
        }

        return $result;
    }

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
    public function read(string $name = null) : bool
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
            $this->type,
            $this->name,
            $this->value,
            $this->depth
        ) = $parser->current();
        //@formatter:on

        $parser->next();

        $result = true;
        if ($name !== null) {
            do {
                if ($this->name() === $name) {
                    break;
                }
            } while ($result = $this->read());
        }

        return $result;
    }

    /**
     * Close the parser.
     *
     * A file handle passed to JsonReader::stream() will not be closed by
     * calling this method. That is left to the caller.
     *
     * @return void
     */
    public function close()
    {
        $this->resetNode();
        $this->parser = null;
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
        $end = $this->getEndType($type);
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
     * @param int $type One of the self:: node type constants
     * @return int self::END_ARRAY, self::END_OBJECT, or self::NONE as appropriate
     */
    private function getEndType(int $type) : int
    {
        switch ($type) {
            case self::ARRAY:
                return self::END_ARRAY;
            case self::OBJECT:
                return self::END_OBJECT;
            default:
                return self::NONE;
        }
    }

    /**
     * Resets the node to the initial state.
     *
     * @return void
     */
    private function resetNode()
    {
        $this->type = self::NONE;
        $this->name = null;
        $this->value = null;
        $this->depth = 0;
    }
}
