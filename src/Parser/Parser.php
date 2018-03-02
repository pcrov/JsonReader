<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\JsonReader;

final class Parser implements \IteratorAggregate
{
    /**
     * @var array Map of tokens to node types.
     */
    private $tokenTypeMap = [
        Token::T_STRING => JsonReader::STRING,
        Token::T_NUMBER => JsonReader::NUMBER,
        Token::T_TRUE => JsonReader::BOOL,
        Token::T_FALSE => JsonReader::BOOL,
        Token::T_NULL => JsonReader::NULL,
        Token::T_BEGIN_ARRAY => JsonReader::ARRAY,
        Token::T_END_ARRAY => JsonReader::END_ARRAY,
        Token::T_BEGIN_OBJECT => JsonReader::OBJECT,
        Token::T_END_OBJECT => JsonReader::END_OBJECT
    ];

    /**
     * @var Tokenizer
     */
    private $tokenizer;

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
     * Generates tuples in the form of:
     *  [$type, $name, $value, $depth]
     *
     * Objects and arrays will have a value of null. The consumer should use a
     * tree builder to flesh these if desired.
     *
     * @return \Generator
     * @throws ParseException
     */
    public function getIterator(): \Generator
    {
        $this->name = null;
        $this->depth = 0;
        $tokenizer = $this->tokenizer;

        yield from $this->parseValue($tokenizer->read());

        $token = $tokenizer->read();
        if ($token->getType() !== Token::T_EOF) {
            throw new ParseException($this->getExceptionMessage($token));
        }
    }

    private function getExceptionMessage(Token $token): string
    {
        $tokenType = $token->getType();
        $tokenLine = $token->getLine();

        if ($tokenType === Token::T_EOF) {
            return \sprintf(
                "Line %d: Unexpected end of file.",
                $tokenLine
            );
        }

        return \sprintf(
            "Line %d: Unexpected token %s.",
            $tokenLine,
            $tokenType
        );
    }

    /**
     * @throws ParseException
     */
    private function parseArray(): \Generator
    {
        $tokenizer = $this->tokenizer;
        $depth = &$this->depth;

        $name = $this->name;
        yield [JsonReader::ARRAY, $name, null, $depth];

        $this->name = null;
        $depth++;
        $token = $tokenizer->read();
        $tokenType = $token->getType();

        if ($tokenType !== Token::T_END_ARRAY) {
            yield from $this->parseValue($token);
            $token = $tokenizer->read();
            $tokenType = $token->getType();

            while ($tokenType === Token::T_COMMA) {
                yield from $this->parseValue($tokenizer->read());
                $token = $tokenizer->read();
                $tokenType = $token->getType();
            }
        }

        if ($tokenType !== Token::T_END_ARRAY) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $depth--;
        yield [JsonReader::END_ARRAY, $name, null, $depth];
    }

    /**
     * @throws ParseException
     */
    private function parseObject(): \Generator
    {
        $tokenizer = $this->tokenizer;
        $depth = &$this->depth;

        $name = $this->name;
        yield [JsonReader::OBJECT, $name, null, $depth];

        $depth++;
        $token = $tokenizer->read();
        $tokenType = $token->getType();

        // name:value property pairs
        if ($tokenType === Token::T_STRING) {
            yield from $this->parsePair($token);
            $token = $tokenizer->read();
            $tokenType = $token->getType();

            while ($tokenType === Token::T_COMMA) {
                yield from $this->parsePair($tokenizer->read());
                $token = $tokenizer->read();
                $tokenType = $token->getType();
            }
        }

        if ($tokenType !== Token::T_END_OBJECT) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $depth--;
        yield [JsonReader::END_OBJECT, $name, null, $depth];
    }

    /**
     * @throws ParseException
     */
    private function parsePair(Token $token): \Generator
    {
        $tokenizer = $this->tokenizer;

        // name
        $tokenType = $token->getType();
        if ($tokenType !== Token::T_STRING) {
            throw new ParseException($this->getExceptionMessage($token));
        }
        $this->name = $token->getValue();

        $token = $tokenizer->read();
        $tokenType = $token->getType();
        // :
        if ($tokenType !== Token::T_COLON) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        // value
        yield from $this->parseValue($tokenizer->read());
        $this->name = null;
    }

    /**
     * @throws ParseException
     */
    private function parseValue(Token $token): \Generator
    {
        $tokenType = $token->getType();

        switch ($tokenType) {
            case Token::T_STRING:
            case Token::T_NUMBER:
            case Token::T_TRUE:
            case Token::T_FALSE:
            case Token::T_NULL:
                yield [$this->tokenTypeMap[$tokenType], $this->name, $token->getValue(), $this->depth];
                break;
            case Token::T_BEGIN_ARRAY:
                yield from $this->parseArray();
                break;
            case Token::T_BEGIN_OBJECT:
                yield from $this->parseObject();
                break;
            default:
                throw new ParseException($this->getExceptionMessage($token));
        }
    }
}
