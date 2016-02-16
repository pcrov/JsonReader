<?php declare(strict_types = 1);

namespace JsonReader\Parser;

class Parser implements \IteratorAggregate
{
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

    }

    private function parseObject() : \Generator
    {

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
            case Tokenizer::T_NUMBER:
            case Tokenizer::T_TRUE:
            case Tokenizer::T_FALSE:
            case Tokenizer::T_NULL:
                yield [$token, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                break;
            case Tokenizer::T_BEGIN_ARRAY:
                yield [$token, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                yield from $this->parseArray();
                break;
            case Tokenizer::T_BEGIN_OBJECT:
                yield [$token, $name, $value, $depth];
                $this->name = null;
                $iterator->next();
                yield from $this->parseObject();
                break;
            default:
                throw new ParseException($this->getExceptionMessage($token));
        }
    }
}