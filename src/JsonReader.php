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
     * @return void
     */
    public function close()
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
        $nodeType = $this->getNodeType();
        switch ($nodeType) {
            case self::ARRAY:
            case self::OBJECT:
                return $this->value ?? $this->buildTree($nodeType);
            default:
                return $this->value;
        }
    }

    /**
     * @param \Traversable $parser
     * @return void
     */
    public function init(\Traversable $parser)
    {
        $this->close();
        $iterator = new \AppendIterator();
        $iterator->append(new \ArrayIterator([[self::NONE, null, null, 0]])); //faux document start node
        $iterator->append(new \IteratorIterator($parser));
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

        do {
            $parser->next();

            //@formatter:off silly ide
            list (
                $this->nodeType,
                $this->name,
                $this->value,
                $this->depth
            ) = $parser->current();
            //@formatter:on

        } while (
            $parser->valid() &&
            ($this->nodeType === self::END_ARRAY || $this->nodeType === self::END_OBJECT)
        );

        if (!$parser->valid()) {
            $this->close();
            return false;
        }

        return true;
    }

    private function buildTree(int $type) : array
    {
        assert($type === self::ARRAY || $type === self::OBJECT);
        $parser = $this->parser;
        $end = ($type === self::ARRAY) ? self::END_ARRAY : self::END_OBJECT;
        $return = [];

        $parser->next();
        while (true) {
            list ($type, $name, $value) = $parser->current();

            if ($type === $end) {
                break;
            }

            if ($type === self::ARRAY || $type === self::OBJECT) {
                $value = $this->buildTree($type);
            }

            if ($name !== null) {
                $return[$name] = $value;
            } else {
                $return[] = $value;
            }

            $parser->next();
        }

        return $return;
    }
}
