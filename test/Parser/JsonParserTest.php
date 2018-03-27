<?php

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\JsonReader;
use PHPUnit\Framework\TestCase;

class JsonParserTest extends TestCase
{

    /** @var Tokenizer */
    protected $tokenizer;

    /** @dataProvider provideTestParser */
    public function testParser($input, $expected)
    {
        $tokenizer = $this->tokenizer;
        $tokenizer->setTokens($input);
        $parser = new JsonParser($tokenizer);

        $i = 0;
        while (($node = $parser->read()) !== null) {
            self::assertSame($expected[$i], $node);
            $i++;
        }
    }

    /** @dataProvider provideTestParserError */
    public function testParserError($input, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedMessage);

        $tokenizer = $this->tokenizer;
        $tokenizer->setTokens($input);
        $parser = new JsonParser($tokenizer);

        while ($parser->read() !== null) {
            ;
        }
    }

    public function provideTestParser()
    {
        return [
            "string" => [
                [
                    [Tokenizer::T_STRING, "foo", 1],
                ],
                [
                    [JsonReader::STRING, null, "foo", 0],
                ]
            ],
            "number" => [
                [
                    [Tokenizer::T_NUMBER, 42, 1],
                ],
                [
                    [JsonReader::NUMBER, null, 42, 0],
                ]
            ],
            "true" => [
                [
                    [Tokenizer::T_TRUE, true, 1],
                ],
                [
                    [JsonReader::BOOL, null, true, 0],
                ]
            ],
            "false" => [
                [
                    [Tokenizer::T_FALSE, false, 1],
                ],
                [
                    [JsonReader::BOOL, null, false, 0],
                ]
            ],
            "null" => [
                [
                    [Tokenizer::T_NULL, null, 1],
                ],
                [
                    [JsonReader::NULL, null, null, 0],
                ]
            ],
            "empty array" => [
                [
                    [Tokenizer::T_BEGIN_ARRAY, null, 1],
                    [Tokenizer::T_END_ARRAY, null, 1],
                ],
                [
                    [JsonReader::ARRAY, null, null, 0],
                    [JsonReader::END_ARRAY, null, null, 0],
                ]
            ],
            "array with varied content" => [
                [
                    [Tokenizer::T_BEGIN_ARRAY, null, 1],
                    [Tokenizer::T_FALSE, false, 1],
                    [Tokenizer::T_COMMA, null, 1],
                    [Tokenizer::T_BEGIN_ARRAY, null, 1],
                    [Tokenizer::T_END_ARRAY, null, 1],
                    [Tokenizer::T_COMMA, null, 1],
                    [Tokenizer::T_NUMBER, 42, 1],
                    [Tokenizer::T_END_ARRAY, null, 1],
                ],
                [
                    [JsonReader::ARRAY, null, null, 0],
                    [JsonReader::BOOL, null, false, 1],
                    [JsonReader::ARRAY, null, null, 1],
                    [JsonReader::END_ARRAY, null, null, 1],
                    [JsonReader::NUMBER, null, 42, 1],
                    [JsonReader::END_ARRAY, null, null, 0],
                ]
            ],
            "empty object" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 1],
                    [Tokenizer::T_END_OBJECT, null, 1],
                ],
                [
                    [JsonReader::OBJECT, null, null, 0],
                    [JsonReader::END_OBJECT, null, null, 0],
                ]
            ],
            "object with varied content" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 1],
                    [Tokenizer::T_STRING, "foo", 1],
                    [Tokenizer::T_COLON, null, 1],
                    [Tokenizer::T_FALSE, false, 1],
                    [Tokenizer::T_COMMA, null, 1],
                    [Tokenizer::T_STRING, "bar", 1],
                    [Tokenizer::T_COLON, null, 1],
                    [Tokenizer::T_BEGIN_ARRAY, null, 1],
                    [Tokenizer::T_END_ARRAY, null, 1],
                    [Tokenizer::T_COMMA, null, 1],
                    [Tokenizer::T_STRING, "answer", 1],
                    [Tokenizer::T_COLON, null, 1],
                    [Tokenizer::T_NUMBER, "42", 1],
                    [Tokenizer::T_END_OBJECT, null, 1],
                ],
                [
                    [JsonReader::OBJECT, null, null, 0],
                    [JsonReader::BOOL, "foo", false, 1],
                    [JsonReader::ARRAY, "bar", null, 1],
                    [JsonReader::END_ARRAY, "bar", null, 1],
                    [JsonReader::NUMBER, "answer", "42", 1],
                    [JsonReader::END_OBJECT, null, null, 0],
                ]
            ],
        ];
    }

    public function provideTestParserError()
    {
        return [
            "no token" => [
                [

                ],
                "Line 42: Unexpected end of file."
            ],
            "true followed by null" => [
                [
                    [Tokenizer::T_TRUE, true, 42],
                    [Tokenizer::T_NULL, null, 42],
                ],
                "Line 42: Unexpected token T_NULL."
            ],
            "unfinished empty array" => [
                [
                    [Tokenizer::T_BEGIN_ARRAY, null, 42],
                ],
                "Line 42: Unexpected end of file."
            ],
            "array with missing comma between members" => [
                [
                    [Tokenizer::T_BEGIN_ARRAY, null, 42],
                    [Tokenizer::T_NULL, null, 42],
                    [Tokenizer::T_TRUE, true, 42],
                ],
                "Line 42: Unexpected token T_TRUE."
            ],
            "unfinished empty object" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                ],
                "Line 42: Unexpected end of file."
            ],
            "object member with incorrect token type for name" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_FALSE, false, 42],
                ],
                "Line 42: Unexpected token T_FALSE."
            ],
            "object members with missing colon between name and value" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_STRING, "err", 42],
                ],
                "Line 42: Unexpected token T_STRING."
            ],
            "object member with incorrect token type for value" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_COLON, null, 42],
                ],
                "Line 42: Unexpected token T_COLON."
            ],
            "incomplete object ending at trailing comma" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_COMMA, null, 42],
                ],
                "Line 42: Unexpected end of file."
            ],
            "object with trailing comma after pair" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_END_OBJECT, null, 42],
                ],
                "Line 42: Unexpected token T_END_OBJECT."
            ],
            "object ending with multiple commas after pair" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_END_OBJECT, null, 42],
                ],
                "Line 42: Unexpected token T_COMMA."
            ],
            "object with multiple commas between pairs" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_END_OBJECT, null, 42],
                ],
                "Line 42: Unexpected token T_COMMA."
            ],
            "object member with incorrect token type for name after valid pair" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_COMMA, null, 42],
                    [Tokenizer::T_FALSE, false, 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_END_OBJECT, null, 42],
                ],
                "Line 42: Unexpected token T_FALSE."
            ],
            "object members with missing comma" => [
                [
                    [Tokenizer::T_BEGIN_OBJECT, null, 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_STRING, "name", 42],
                    [Tokenizer::T_COLON, null, 42],
                    [Tokenizer::T_STRING, "value", 42],
                    [Tokenizer::T_END_OBJECT, null, 42],
                ],
                "Line 42: Unexpected token T_STRING."
            ],
        ];
    }

    protected function setUp()
    {
        $this->tokenizer = new class() implements Tokenizer
        {
            private $tokens = [];

            public function setTokens(array $tokens)
            {
                $this->tokens = $tokens;
            }

            public function read(): array
            {
                $tokens = &$this->tokens;

                if (($current = \current($tokens)) === false) {
                    return [Tokenizer::T_EOF, null, 42];
                }
                next($tokens);

                return $current;
            }
        };
    }
}
