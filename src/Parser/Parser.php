<?php declare(strict_types = 1);

namespace JsonReader\Parser;

class Parser implements \IteratorAggregate
{
    const STRING = 1;
    const NUMBER = 2;
    const BOOL = 3;
    const NULL = 4;
    const ARRAY = 5;
    const END_ARRAY = 6;
    const OBJECT = 7;
    const END_OBJECT = 8;

    /**
     * @var Tokenizer
     */
    private $tokenizer;

    /**
     * @var \Iterator Iterator provided by the $lexer, which might be the lexer itself.
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

    private function initTokenizer()
    {
        $tokenizer = $this->tokenizer;

        /** @var \Iterator $iterator */
        $iterator = ($tokenizer instanceof \IteratorAggregate) ? $tokenizer->getIterator() : $tokenizer;
        $iterator->rewind();
        $this->tokenIterator = $iterator;
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

    private function parseArray() : \Generator
    {
        $iterator = $this->tokenIterator;
        assert($iterator->key() === Tokenizer::T_BEGIN_ARRAY);

        $name = $this->name;
        yield [self::ARRAY, $name, null, $this->depth];

        $this->name = null;
        $this->depth++;
        $iterator->next();

        if ($iterator->key() !== Tokenizer::T_END_ARRAY) {
            do {
                yield from $this->parseValue();
            } while ($this->consumeComma());
        }

        $token = $iterator->key();
        if ($token === Tokenizer::T_END_ARRAY) {
            $this->depth--;
            yield [self::END_ARRAY, $name, null, $this->depth];
            $iterator->next();
        } else {
            throw new ParseException($this->getExceptionMessage($token));
        }
    }

    private function parseObject() : \Generator
    {
        $iterator = $this->tokenIterator;
        assert($iterator->key() === Tokenizer::T_BEGIN_OBJECT);

        $name = $this->name;
        yield [self::OBJECT, $name, null, $this->depth];

        $this->name = null;
        $this->depth++;
        $iterator->next();

        // name:value property pairs
        if ($iterator->key() === Tokenizer::T_STRING) {
            do {
                yield from $this->parsePair();
            } while ($this->consumeComma());
        }

        $token = $iterator->key();
        if ($token === Tokenizer::T_END_OBJECT) {
            $this->depth--;
            yield [self::END_OBJECT, $name, null, $this->depth];
            $iterator->next();
        } else {
            throw new ParseException($this->getExceptionMessage($token));
        }
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
    }

    private function parseValue() : \Generator
    {
        $iterator = $this->tokenIterator;

        $token = $iterator->key();
        $name = $this->name;
        $value = $iterator->current();
        $depth = $this->depth;

        switch ($token) {
            case Tokenizer::T_STRING:
                yield [self::STRING, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                break;
            case Tokenizer::T_NUMBER:
                yield [self::NUMBER, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                break;
            case Tokenizer::T_TRUE:
            case Tokenizer::T_FALSE:
                yield [self::BOOL, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
            break;
            case Tokenizer::T_NULL:
                yield [self::NULL, $name, $value, $depth];
                $this->name = null;
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
