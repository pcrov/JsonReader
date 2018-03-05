<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\JsonReader;

final class JsonParser implements Parser
{
    /**
     * @var array Map of token to node types.
     */
    private static $tokenTypeMap = [
        Tokenizer::T_STRING => JsonReader::STRING,
        Tokenizer::T_NUMBER => JsonReader::NUMBER,
        Tokenizer::T_TRUE => JsonReader::BOOL,
        Tokenizer::T_FALSE => JsonReader::BOOL,
        Tokenizer::T_NULL => JsonReader::NULL,
        Tokenizer::T_BEGIN_ARRAY => JsonReader::ARRAY,
        Tokenizer::T_END_ARRAY => JsonReader::END_ARRAY,
        Tokenizer::T_BEGIN_OBJECT => JsonReader::OBJECT,
        Tokenizer::T_END_OBJECT => JsonReader::END_OBJECT
    ];

    /**
     * @var Tokenizer
     */
    private $tokenizer;

    /**
     * @var int
     */
    private $depth = 0;

    /**
     * @var string|null Name of the current object pair.
     */
    private $name;

    /**
     * @var \Generator
     */
    private $nodeGenerator;

    public function __construct(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
        $this->nodeGenerator = $this->getNodeGenerator();
    }

    /**
     * @throws ParseException
     */
    public function read()
    {
        $this->nodeGenerator->next();
        return $this->nodeGenerator->current();
    }

    /**
     * @throws ParseException
     */
    private function getNodeGenerator(): \Generator
    {
        yield; // skipped by first read()

        $tokenizer = $this->tokenizer;
        yield from $this->parseValue($tokenizer->read());

        $token = $tokenizer->read();
        if ($token[0] !== Tokenizer::T_EOF) {
            throw new ParseException($this->getExceptionMessage($token));
        }
    }

    private function getExceptionMessage(array $token): string
    {
        list ($tokenType, , $tokenLine) = $token;

        if ($tokenType === Tokenizer::T_EOF) {
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
        $name = &$this->name;

        $arrayName = $name;
        yield [JsonReader::ARRAY, $arrayName, null, $depth];

        $name = null;
        $depth++;
        $token = $tokenizer->read();
        $tokenType = $token[0];

        if ($tokenType !== Tokenizer::T_END_ARRAY) {
            yield from $this->parseValue($token);
            $token = $tokenizer->read();
            $tokenType = $token[0];

            while ($tokenType === Tokenizer::T_COMMA) {
                yield from $this->parseValue($tokenizer->read());
                $token = $tokenizer->read();
                $tokenType = $token[0];
            }
        }

        if ($tokenType !== Tokenizer::T_END_ARRAY) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $depth--;
        yield [JsonReader::END_ARRAY, $arrayName, null, $depth];
    }

    /**
     * @throws ParseException
     */
    private function parseObject(): \Generator
    {
        $tokenizer = $this->tokenizer;
        $depth = &$this->depth;

        $objectName = $this->name;
        yield [JsonReader::OBJECT, $objectName, null, $depth];

        $depth++;
        $token = $tokenizer->read();
        $tokenType = $token[0];

        // name:value property pairs
        if ($tokenType === Tokenizer::T_STRING) {
            yield from $this->parsePair($token);
            $token = $tokenizer->read();
            $tokenType = $token[0];

            while ($tokenType === Tokenizer::T_COMMA) {
                yield from $this->parsePair($tokenizer->read());
                $token = $tokenizer->read();
                $tokenType = $token[0];
            }
        }

        if ($tokenType !== Tokenizer::T_END_OBJECT) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        $depth--;
        yield [JsonReader::END_OBJECT, $objectName, null, $depth];
    }

    /**
     * @throws ParseException
     */
    private function parsePair(array $token): \Generator
    {
        $tokenizer = $this->tokenizer;
        $name = &$this->name;

        // name
        list($tokenType, $tokenValue) = $token;
        if ($tokenType !== Tokenizer::T_STRING) {
            throw new ParseException($this->getExceptionMessage($token));
        }
        $name = $tokenValue;

        $token = $tokenizer->read();
        // :
        if ($token[0] !== Tokenizer::T_COLON) {
            throw new ParseException($this->getExceptionMessage($token));
        }

        // value
        yield from $this->parseValue($tokenizer->read());
        $name = null;
    }

    /**
     * @throws ParseException
     */
    private function parseValue(array $token): \Generator
    {
        list($tokenType, $tokenValue) = $token;

        switch ($tokenType) {
            case Tokenizer::T_STRING:
            case Tokenizer::T_NUMBER:
            case Tokenizer::T_TRUE:
            case Tokenizer::T_FALSE:
            case Tokenizer::T_NULL:
                yield [self::$tokenTypeMap[$tokenType], $this->name, $tokenValue, $this->depth];
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
