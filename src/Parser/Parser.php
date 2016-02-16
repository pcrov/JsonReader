<?php declare(strict_types = 1);

namespace JsonReader\Parser;

class Parser implements \IteratorAggregate
{
    const STRING = 1;
    const NUMBER = 2;
    const BOOL = 3;
    const NULL = 4;
    const ARRAY = 5;
    const OBJECT = 6;

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
     * to flesh these out based on the yielded depth.
     *
     * @return \Generator
     * @throws ParseException
     */
    public function getIterator() : \Generator
    {
        $this->initLexer();
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
        $token = $iterator->key();
        if ($token === Tokenizer::T_COMMA) {
            $iterator->next();
            return true;
        }
        return false;
    }

    private function initLexer()
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
        return sprintf(
            "Line %d: Unexpected token %s.",
            $tokenizer->getLineNumber(),
            $tokenizer::NAMES[$token]
        );
    }

    private function parseArray() : \Generator
    {
        $iterator = $this->tokenIterator;
        $this->depth++;

        if ($iterator->key() !== Tokenizer::T_END_ARRAY) {
            do {
                yield from $this->parseValue();
            } while ($this->consumeComma());
        }

        $token = $iterator->key();
        if ($token !== Tokenizer::T_END_ARRAY) {
            throw new ParseException($this->getExceptionMessage($token));
        }
        $iterator->next();
        $this->depth--;
    }

    private function parseObject() : \Generator
    {
        $iterator = $this->tokenIterator;
        $this->depth++;

        if ($iterator->key() !== Tokenizer::T_END_OBJECT) {
            do {
                //Property name
                $token = $iterator->key();
                if ($token !== Tokenizer::T_STRING) {
                    throw new ParseException($this->getExceptionMessage($token));
                }
                $this->name = $iterator->current();
                $iterator->next();

                //Name:value separator
                $token = $iterator->key();
                if ($token !== Tokenizer::T_COLON) {
                    throw new ParseException($this->getExceptionMessage($token));
                }
                $iterator->next();

                //Value
                yield from $this->parseValue();
            } while ($this->consumeComma());
        }

        $token = $iterator->key();
        if ($token !== Tokenizer::T_END_OBJECT) {
            throw new ParseException($this->getExceptionMessage($token));
        }
        $iterator->next();
        $this->depth--;
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
                yield [self::ARRAY, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                yield from $this->parseArray();
                break;
            case Tokenizer::T_BEGIN_OBJECT:
                yield [self::OBJECT, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                yield from $this->parseObject();
                break;
            default:
                throw new ParseException($this->getExceptionMessage($token));
        }
    }
}
