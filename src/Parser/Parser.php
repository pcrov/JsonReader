<?php declare(strict_types = 1);

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\NodeType;

class Parser implements \IteratorAggregate
{
    /**
     * @var array Map of tokens to node types.
     */
    private $tokenTypeMap = [
        Tokenizer::T_STRING => NodeType::STRING,
        Tokenizer::T_NUMBER => NodeType::NUMBER,
        Tokenizer::T_TRUE => NodeType::BOOL,
        Tokenizer::T_FALSE => NodeType::BOOL,
        Tokenizer::T_NULL => NodeType::NULL,
        Tokenizer::T_BEGIN_ARRAY => NodeType::ARRAY,
        Tokenizer::T_END_ARRAY => NodeType::END_ARRAY,
        Tokenizer::T_BEGIN_OBJECT => NodeType::OBJECT,
        Tokenizer::T_END_OBJECT => NodeType::END_OBJECT
    ];

    /**
     * @var Tokenizer
     */
    private $tokenizer;

    /**
     * @var \IteratorIterator Iterator of $tokenizer.
     */
    private $tokenIterator;

    /**
     * @var int
     */
    private $depth;

    /**
     * @var string|null Name of the current object pair.
     */
    private $name;

    public function __construct(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Reads from the token stream, generates a data stream in the form of:
     *  [$type, $name, $value, $depth]
     *
     * Objects and arrays will have no value. The consumer should use a tree builder
     * to flesh these out as desired.
     *
     * @return \Generator
     * @throws ParseException
     */
    public function getIterator() : \Generator
    {
        $this->initTokenizer();
        $this->name = null;
        $this->depth = 0;
        $iterator = $this->tokenIterator;

        yield from $this->parseValue();

        if ($iterator->valid()) {
            throw new ParseException($this->getExceptionMessage($iterator->key()));
        }
    }

    private function consumeComma() : bool
    {
        $iterator = $this->tokenIterator;
        if ($iterator->key() === Tokenizer::T_COMMA) {
            $iterator->next();
            return true;
        }
        return false;
    }

    private function getExceptionMessage(int $token = null) : string
    {
        $tokenizer = $this->tokenizer;

        if ($token === null) {
            return sprintf(
                "Line %d: Unexpected end of file.",
                $tokenizer->getLineNumber()
            );
        }

        return sprintf(
            "Line %d: Unexpected token %s.",
            $tokenizer->getLineNumber(),
            $tokenizer::NAMES[$token]
        );
    }

    private function initTokenizer()
    {
        $iterator = new \IteratorIterator($this->tokenizer);
        $iterator->rewind();
        $this->tokenIterator = $iterator;
    }

    private function parseArray() : \Generator
    {
        $iterator = $this->tokenIterator;
        assert($iterator->key() === Tokenizer::T_BEGIN_ARRAY);

        $name = $this->name;
        yield [NodeType::ARRAY, $name, null, $this->depth];

        $this->name = null;
        $this->depth++;
        $iterator->next();
        $token = $iterator->key();

        if ($token !== Tokenizer::T_END_ARRAY) {
            do {
                yield from $this->parseValue();
            } while ($this->consumeComma());
            $token = $iterator->key();
        }

        if ($token !== Tokenizer::T_END_ARRAY) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $this->depth--;
        yield [NodeType::END_ARRAY, $name, null, $this->depth];
        $iterator->next();
    }

    private function parseObject() : \Generator
    {
        $iterator = $this->tokenIterator;
        assert($iterator->key() === Tokenizer::T_BEGIN_OBJECT);

        $name = $this->name;
        yield [NodeType::OBJECT, $name, null, $this->depth];

        $this->depth++;
        $iterator->next();
        $token = $iterator->key();

        // name:value property pairs
        if ($token === Tokenizer::T_STRING) {
            do {
                yield from $this->parsePair();
            } while ($this->consumeComma());
            $token = $iterator->key();
        }

        if ($token !== Tokenizer::T_END_OBJECT) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $this->depth--;
        yield [NodeType::END_OBJECT, $name, null, $this->depth];
        $iterator->next();
    }

    private function parsePair() : \Generator
    {
        $iterator = $this->tokenIterator;
        assert($iterator->key() === Tokenizer::T_STRING);

        // name
        $this->name = $iterator->current();
        $iterator->next();

        // :
        $token = $iterator->key();
        if ($token !== Tokenizer::T_COLON) {
            throw new ParseException($this->getExceptionMessage($token));
        }
        $iterator->next();

        // value
        yield from $this->parseValue();
        $this->name = null;
    }

    private function parseValue() : \Generator
    {
        $iterator = $this->tokenIterator;

        $token = $iterator->key();
        switch ($token) {
            case Tokenizer::T_STRING:
            case Tokenizer::T_NUMBER:
            case Tokenizer::T_TRUE:
            case Tokenizer::T_FALSE:
            case Tokenizer::T_NULL:
                yield [$this->tokenTypeMap[$token], $this->name, $iterator->current(), $this->depth];
                $iterator->next();
                break;
            case Tokenizer::T_BEGIN_ARRAY:
                yield from $this->parseArray();
                break;
            case Tokenizer::T_BEGIN_OBJECT:
                yield from $this->parseObject();
                break;
            default:
                throw new ParseException($this->getExceptionMessage($token));
        }
    }
}
