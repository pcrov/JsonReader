<?php declare(strict_types=1);

namespace pcrov\JsonReader;

use pcrov\JsonReader\InputStream\IOException;
use pcrov\JsonReader\InputStream\Stream;
use pcrov\JsonReader\InputStream\Uri;
use pcrov\JsonReader\InputStream\StringInput;
use pcrov\JsonReader\Parser\JsonParser;
use pcrov\JsonReader\Parser\Lexer;
use pcrov\JsonReader\Parser\Parser;

class JsonReader
{
    /* Node types */
    const NONE = "NONE";
    const STRING = "STRING";
    const NUMBER = "NUMBER";
    const BOOL = "BOOL";
    const NULL = "NULL";
    const ARRAY = "ARRAY";
    const END_ARRAY = "END_ARRAY";
    const OBJECT = "OBJECT";
    const END_OBJECT = "END_OBJECT";

    /* Options */
    const FLOAT_AS_STRING = 0b00000001;

    /**
     * @var Parser|null
     */
    private $parser;

    /**
     * @var array[] Tuples from the parser, cached during tree building.
     */
    private $cache = [];

    /**
     * @var int bit field of reader options
     */
    private $options;

    /**
     * @var string
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

    public function __construct(int $options = 0)
    {
        $this->options = $options;
    }

    /**
     * @return void
     */
    public function init(Parser $parser)
    {
        $this->close();
        $this->parser = $parser;
    }

    /**
     * @return void
     */
    public function json(string $json)
    {
        $this->init(new JsonParser(new Lexer(new StringInput($json))));
    }

    /**
     * @return void
     * @throws IOException if a given URI is not readable.
     * @throws InvalidArgumentException
     */
    public function open(string $uri)
    {
        $this->init(new JsonParser(new Lexer(new Uri($uri))));
    }

    /**
     * @param resource $stream Readable file stream resource.
     * @return void
     * @throws InvalidArgumentException if a given resource is not a valid stream.
     * @throws IOException if a given stream resource is not readable.
     */
    public function stream($stream)
    {
        $this->init(new JsonParser(new Lexer(new Stream($stream))));
    }

    /**
     * @return string One of the JsonReader node constants.
     */
    public function type(): string
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
        $type = $this->type;
        $value = &$this->value;

        if ($value === null && ($type === self::ARRAY || $type === self::OBJECT)) {
            $value = $this->buildTree($type, empty($this->cache));
        }

        if ($type === self::NUMBER) {
            return $this->castNumber($value);
        }

        return $value;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    /**
     * @throws Exception
     */
    public function next(string $target = null): bool
    {
        if ($this->parser === null) {
            throw new Exception("Load data before trying to read.");
        }

        $currentDepth = $this->depth;
        $endType = $this->getEndType($this->type);

        while ($result = $this->read()) {
            if ($this->depth <= $currentDepth) {
                break;
            }
        }

        // If we were on an object or array when called, we want to skip its end node.
        if ($endType !== self::NONE &&
            $this->depth === $currentDepth &&
            $this->type === $endType
        ) {
            $result = $this->read();
        }

        if ($target !== null) {
            do {
                if ($this->name === $target) {
                    break;
                }
            } while ($result = $this->next());
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function read(string $target = null): bool
    {
        $parser = $this->parser;

        if ($parser === null) {
            throw new Exception("Load data before trying to read.");
        }

        if (empty($this->cache)) {
            $node = $parser->read();
        } else {
            $node = \array_shift($this->cache);
        }

        if ($node === null) {
            $this->resetNode();
            return false;
        }

        list (
            $this->type,
            $this->name,
            $this->value,
            $this->depth
            ) = $node;

        $result = true;
        if ($target !== null) {
            do {
                if ($this->name === $target) {
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

    private function buildTree(string $type, bool $writeCache): array
    {
        \assert($type === self::ARRAY || $type === self::OBJECT);

        $parser = $this->parser;
        $cache = &$this->cache;
        $end = $this->getEndType($type);
        $result = [];

        while (true) {
            if ($writeCache) {
                $node = $parser->read();
                $cache[] = $node;
            } else {
                $node = \current($cache);
                \next($cache);
            }
            list ($type, $name, $value) = $node;

            if ($type === $end) {
                break;
            }

            if ($type === self::ARRAY || $type === self::OBJECT) {
                $value = $this->buildTree($type, $writeCache);
            }

            if ($type === self::NUMBER) {
                $value = $this->castNumber($value);
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
     * @return int|float|string
     */
    private function castNumber(string $number)
    {
        $cast = +$number;
        if (($this->options & self::FLOAT_AS_STRING) && \is_float($cast)) {
            return $number;
        }
        return $cast;
    }

    private function getEndType(string $type): string
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
        $this->cache = [];
    }
}
