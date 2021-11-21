<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\JsonReader;

final class JsonParser implements Parser
{
    private const TOKEN_TYPE_MAP = [
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

    private const STATE_DOCUMENT_END = 0;
    private const STATE_DOCUMENT_START = 1;
    private const STATE_AFTER_ARRAY_START = 2;
    private const STATE_AFTER_ARRAY_MEMBER = 3;
    private const STATE_AFTER_OBJECT_START = 4;
    private const STATE_AFTER_OBJECT_MEMBER = 5;

    private const IN_ARRAY = 1;
    private const IN_OBJECT = 2;

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
        $this->state = self::STATE_DOCUMENT_START;
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
            case self::STATE_AFTER_ARRAY_START:
                if ($token[0] === Tokenizer::T_END_ARRAY) {
                    goto end_of_array_or_object;
                }

                $names[$depth] = null;
                $state = self::STATE_AFTER_ARRAY_MEMBER;
                goto value;

            case self::STATE_AFTER_ARRAY_MEMBER:
                if ($token[0] === Tokenizer::T_END_ARRAY) {
                    goto end_of_array_or_object;
                }

                if ($token[0] !== Tokenizer::T_COMMA) {
                    throw new ParseException($this->getExceptionMessage($token));
                }

                $token = $tokenizer->read();
                goto value;

            case self::STATE_AFTER_OBJECT_START:
                if ($token[0] === Tokenizer::T_END_OBJECT) {
                    goto end_of_array_or_object;
                }

                $state = self::STATE_AFTER_OBJECT_MEMBER;
                goto object_member;

            case self::STATE_AFTER_OBJECT_MEMBER:
                if ($token[0] === Tokenizer::T_END_OBJECT) {
                    goto end_of_array_or_object;
                }

                if ($token[0] !== Tokenizer::T_COMMA) {
                    throw new ParseException($this->getExceptionMessage($token));
                }

                $token = $tokenizer->read();
                goto object_member;

            case self::STATE_DOCUMENT_START:
                $state = self::STATE_DOCUMENT_END;
                goto value;

            case self::STATE_DOCUMENT_END:
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
                case self::IN_ARRAY:
                    $state = self::STATE_AFTER_ARRAY_MEMBER;
                    break;
                case self::IN_OBJECT:
                    $state = self::STATE_AFTER_OBJECT_MEMBER;
                    break;
                default:
                    $state = self::STATE_DOCUMENT_END;
            }
            return [self::TOKEN_TYPE_MAP[$token[0]], $names[$depth], $token[1], $depth];
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
                    $state = self::STATE_AFTER_ARRAY_START;
                    $stack[] = self::IN_ARRAY;
                    $depth++;
                    break;
                case Tokenizer::T_BEGIN_OBJECT:
                    $state = self::STATE_AFTER_OBJECT_START;
                    $stack[] = self::IN_OBJECT;
                    $depth++;
                    break;
                default:
                    throw new ParseException($this->getExceptionMessage($token));
            }
            return [self::TOKEN_TYPE_MAP[$token[0]], $names[$currentDepth], $token[1], $currentDepth];
        }
    }

    private function getExceptionMessage(array $token): string
    {
        [$tokenType, , $tokenLine] = $token;

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
