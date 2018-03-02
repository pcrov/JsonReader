<?php declare(strict_types=1);

namespace pcrov\JsonReader;

use pcrov\IteratorStackIterator;
use pcrov\JsonReader\InputStream\IOException;
use pcrov\JsonReader\InputStream\Stream;
use pcrov\JsonReader\InputStream\Uri;
use pcrov\JsonReader\InputStream\StringInput;
use pcrov\JsonReader\Parser\Parser;
use pcrov\JsonReader\Parser\Lexer;

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
     * @return void
     */
    public function json(string $json)
    {
        $this->init(new Parser(new Lexer(new StringInput($json))));
    }

    /**
     * @return void
     * @throws IOException if a given URI is not readable.
     * @throws InvalidArgumentException
     */
    public function open(string $uri)
    {
        $this->init(new Parser(new Lexer(new Uri($uri))));
    }

    /**
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
     * @return int One of the JsonReader node constants.
     */
    public function type(): int
    {
        return $this->type;
    }

    /**
     * @return string|null
     */
    public function name()
    {
        return $this->name;
    }

    /**
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

    public function depth(): int
    {
        return $this->depth;
    }

    /**
     * @throws Exception
     */
    public function next(string $name = null): bool
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
     * @throws Exception
     */
    public function read(string $name = null): bool
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
     * @return void
     */
    public function close()
    {
        $this->resetNode();
        $this->parser = null;
    }

    private function buildTree(int $type): array
    {
        \assert($type === self::ARRAY || $type === self::OBJECT);

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

    private function getEndType(int $type): int
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

    private function resetNode()
    {
        $this->type = self::NONE;
        $this->name = null;
        $this->value = null;
        $this->depth = 0;
    }
}
