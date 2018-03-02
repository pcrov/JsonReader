<?php

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\JsonReader;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{

    /** @var Tokenizer */
    protected $tokenizer;

    /** @dataProvider provideTestParser */
    public function testParser($input, $expected)
    {
        $tokenizer = $this->tokenizer;
        $tokenizer->setTokens($input);

        $parser = new \IteratorIterator(new Parser($tokenizer));
        $parser->rewind();
        $this->assertTrue($parser->valid());

        $iterator = new \MultipleIterator(\MultipleIterator::MIT_NEED_ANY | \MultipleIterator::MIT_KEYS_ASSOC);
        $iterator->attachIterator($parser, "parser");
        $iterator->attachIterator(new \ArrayIterator($expected), "expected");

        foreach ($iterator as $tuple) {
            $this->assertSame($tuple["expected"], $tuple["parser"]);
        }
    }

    /** @dataProvider provideTestParserError */
    public function testParserError($input, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedMessage);

        $tokenizer = $this->tokenizer;
        $tokenizer->setTokens($input);

        $parser = new \IteratorIterator(new Parser($tokenizer));
        $parser->rewind();

        foreach ($parser as $_);
    }

    public function provideTestParser()
    {
        return [
            "string" => [
                [
                    [Token::T_STRING, "foo"],
                ],
                [
                    [JsonReader::STRING, null, "foo", 0],
                ]
            ],
            "number" => [
                [
                    [Token::T_NUMBER, 42],
                ],
                [
                    [JsonReader::NUMBER, null, 42, 0],
                ]
            ],
            "true" => [
                [
                    [Token::T_TRUE, true],
                ],
                [
                    [JsonReader::BOOL, null, true, 0],
                ]
            ],
            "false" => [
                [
                    [Token::T_FALSE, false],
                ],
                [
                    [JsonReader::BOOL, null, false, 0],
                ]
            ],
            "null" => [
                [
                    [Token::T_NULL, null],
                ],
                [
                    [JsonReader::NULL, null, null, 0],
                ]
            ],
            "empty array" => [
                [
                    [Token::T_BEGIN_ARRAY, null],
                    [Token::T_END_ARRAY, null],
                ],
                [
                    [JsonReader::ARRAY, null, null, 0],
                    [JsonReader::END_ARRAY, null, null, 0],
                ]
            ],
            "array with varied content" => [
                [
                    [Token::T_BEGIN_ARRAY, null],
                    [Token::T_FALSE, false],
                    [Token::T_COMMA, null],
                    [Token::T_BEGIN_ARRAY, null],
                    [Token::T_END_ARRAY, null],
                    [Token::T_COMMA, null],
                    [Token::T_NUMBER, 42],
                    [Token::T_END_ARRAY, null],
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
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_END_OBJECT, null],
                ],
                [
                    [JsonReader::OBJECT, null, null, 0],
                    [JsonReader::END_OBJECT, null, null, 0],
                ]
            ],
            "object with varied content" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "foo"],
                    [Token::T_COLON, null],
                    [Token::T_FALSE, false],
                    [Token::T_COMMA, null],
                    [Token::T_STRING, "bar"],
                    [Token::T_COLON, null],
                    [Token::T_BEGIN_ARRAY, null],
                    [Token::T_END_ARRAY, null],
                    [Token::T_COMMA, null],
                    [Token::T_STRING, "answer"],
                    [Token::T_COLON, null],
                    [Token::T_NUMBER, 42],
                    [Token::T_END_OBJECT, null],
                ],
                [
                    [JsonReader::OBJECT, null, null, 0],
                    [JsonReader::BOOL, "foo", false, 1],
                    [JsonReader::ARRAY, "bar", null, 1],
                    [JsonReader::END_ARRAY, "bar", null, 1],
                    [JsonReader::NUMBER, "answer", 42, 1],
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
                    [Token::T_TRUE, true],
                    [Token::T_NULL, null],
                ],
                "Line 42: Unexpected token T_NULL."
            ],
            "unfinished empty array" => [
                [
                    [Token::T_BEGIN_ARRAY, null],
                ],
                "Line 42: Unexpected end of file."
            ],
            "array with missing comma between members" => [
                [
                    [Token::T_BEGIN_ARRAY, null],
                    [Token::T_NULL, null],
                    [Token::T_TRUE, true],
                ],
                "Line 42: Unexpected token T_TRUE."
            ],
            "unfinished empty object" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                ],
                "Line 42: Unexpected end of file."
            ],
            "object member with incorrect token type for name" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_FALSE, false],
                ],
                "Line 42: Unexpected token T_FALSE."
            ],
            "object members with missing colon between name and value" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_STRING, "err"],
                ],
                "Line 42: Unexpected token T_STRING."
            ],
            "object member with incorrect token type for value" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_COLON, null],
                ],
                "Line 42: Unexpected token T_COLON."
            ],
            "incomplete object ending at trailing comma" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_COMMA, null],
                ],
                "Line 42: Unexpected end of file."
            ],
            "object with trailing comma after pair" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_COMMA, null],
                    [Token::T_END_OBJECT, null],
                ],
                "Line 42: Unexpected token T_END_OBJECT."
            ],
            "object ending with multiple commas after pair" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_COMMA, null],
                    [Token::T_COMMA, null],
                    [Token::T_END_OBJECT, null],
                ],
                "Line 42: Unexpected token T_COMMA."
            ],
            "object with multiple commas between pairs" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_COMMA, null],
                    [Token::T_COMMA, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_END_OBJECT, null],
                ],
                "Line 42: Unexpected token T_COMMA."
            ],
            "object member with incorrect token type for name after valid pair" => [
                [
                    [Token::T_BEGIN_OBJECT, null],
                    [Token::T_STRING, "name"],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_COMMA, null],
                    [Token::T_FALSE, false],
                    [Token::T_COLON, null],
                    [Token::T_STRING, "value"],
                    [Token::T_END_OBJECT, null],
                ],
                "Line 42: Unexpected token T_FALSE."
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

            public function read(): Token
            {
                $tokens = &$this->tokens;

                if (($current = \current($tokens)) === false) {
                    return new Token(Token::T_EOF, null, 42);
                }
                next($tokens);

                return new Token($current[0], $current[1], 42);
            }
        };
    }
}
