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

    private static $stateDocumentEnd = 0;
    private static $stateDocumentStart = 1;
    private static $stateAfterArrayStart = 2;
    private static $stateAfterArrayMember = 3;
    private static $stateAfterObjectStart = 4;
    private static $stateAfterObjectMember = 5;

    private static $inArray = 1;
    private static $inObject = 2;

    /**
     * @var Tokenizer
     */
    private $tokenizer;

    private $depth = 0;
    private $state;
    private $names = [null];
    private $stack = [];

    public function __construct(Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
        $this->state = self::$stateDocumentStart;
    }

    /**
     * @throws ParseException
     */
    public function read()
    {
        $depth = &$this->depth;
        $names = &$this->names;
        $stack = &$this->stack;
        $state = &$this->state;
        $tokenizer = $this->tokenizer;

        $token = $tokenizer->read();

        switch ($state) {
            case self::$stateAfterArrayStart:
                if ($token[0] === Tokenizer::T_END_ARRAY) {
                    goto end_of_array_or_object;
                }

                $names[$depth] = null;
                $state = self::$stateAfterArrayMember;
                goto value;

            case self::$stateAfterArrayMember:
                if ($token[0] === Tokenizer::T_END_ARRAY) {
                    goto end_of_array_or_object;
                }

                if ($token[0] !== Tokenizer::T_COMMA) {
                    throw new ParseException($this->getExceptionMessage($token));
                }

                $token = $tokenizer->read();
                goto value;

            case self::$stateAfterObjectStart:
                if ($token[0] === Tokenizer::T_END_OBJECT) {
                    goto end_of_array_or_object;
                }

                $state = self::$stateAfterObjectMember;
                goto object_member;

            case self::$stateAfterObjectMember:
                if ($token[0] === Tokenizer::T_END_OBJECT) {
                    goto end_of_array_or_object;
                }

                if ($token[0] !== Tokenizer::T_COMMA) {
                    throw new ParseException($this->getExceptionMessage($token));
                }

                $token = $tokenizer->read();
                goto object_member;

            case self::$stateDocumentStart:
                $state = self::$stateDocumentEnd;
                goto value;

            case self::$stateDocumentEnd:
                $names = [null];
                if ($token[0] !== Tokenizer::T_EOF) {
                    throw new ParseException($this->getExceptionMessage($token));
                }
                return null;
        }

        object_member: {
            if ($token[0] !== Tokenizer::T_STRING) {
                throw new ParseException($this->getExceptionMessage($token));
            }
            $names[$depth] = $token[1];

            $token = $tokenizer->read();
            if ($token[0] !== Tokenizer::T_COLON) {
                throw new ParseException($this->getExceptionMessage($token));
            }

            $token = $tokenizer->read();
            goto value;
        }

        end_of_array_or_object: {
            \array_pop($stack);
            $depth--;
            switch (\end($stack)) {
                case self::$inArray:
                    $state = self::$stateAfterArrayMember;
                    break;
                case self::$inObject:
                    $state = self::$stateAfterObjectMember;
                    break;
                default:
                    $state = self::$stateDocumentEnd;
            }
            return [self::$tokenTypeMap[$token[0]], $names[$depth], $token[1], $depth];
        }

        value: {
            $currentDepth = $depth;
            switch ($token[0]) {
                case Tokenizer::T_STRING:
                case Tokenizer::T_NUMBER:
                case Tokenizer::T_TRUE:
                case Tokenizer::T_FALSE:
                case Tokenizer::T_NULL:
                    break;
                case Tokenizer::T_BEGIN_ARRAY:
                    $state = self::$stateAfterArrayStart;
                    $stack[] = self::$inArray;
                    $depth++;
                    break;
                case Tokenizer::T_BEGIN_OBJECT:
                    $state = self::$stateAfterObjectStart;
                    $stack[] = self::$inObject;
                    $depth++;
                    break;
                default:
                    throw new ParseException($this->getExceptionMessage($token));
            }
            return [self::$tokenTypeMap[$token[0]], $names[$currentDepth], $token[1], $currentDepth];
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
}
