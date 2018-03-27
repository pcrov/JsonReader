<?php

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\InputStream\InputStream;
use PHPUnit\Framework\TestCase;

class LexerTest extends TestCase
{
    /** @dataProvider provideTestTokenization */
    public function testTokenization($input, $expectedToken)
    {
        $inputStream = $this->getInputStream();
        $inputStream->setString($input);
        $lexer = new Lexer($inputStream);

        $token = $lexer->read();
        $this->assertSame($expectedToken, $token);
    }

    /** @dataProvider provideTestTokenization */
    public function testTokenizationWithSmallBuffer($input, $expectedToken)
    {
        $inputStream = $this->getInputStreamWithSmallBuffer();
        $inputStream->setString($input);
        $lexer = new Lexer($inputStream);

        $token = $lexer->read();
        $this->assertSame($expectedToken, $token);
    }

    /** @dataProvider provideTestLexerError */
    public function testLexerError($input, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedMessage);

        $inputStream = $this->getInputStream();
        $inputStream->setString($input);
        $lexer = new Lexer($inputStream);

        do {
            $token = $lexer->read();
        } while ($token[0] !== Tokenizer::T_EOF);
    }

    /** @dataProvider provideTestLexerError */
    public function testLexerErrorWithSmallBuffer($input, $expectedMessage)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage($expectedMessage);

        $inputStream = $this->getInputStreamWithSmallBuffer();
        $inputStream->setString($input);
        $lexer = new Lexer($inputStream);

        do {
            $token = $lexer->read();
        } while ($token[0] !== Tokenizer::T_EOF);
    }

    public function provideTestTokenization()
    {
        return [
            "variety of line breaks" => [
                "\n\r\r\n\r\n",
                [Tokenizer::T_EOF, null, 5]
            ],
            "simple string" => [
                '"foo"',
                [Tokenizer::T_STRING, "foo", 1]
            ],
            "string surrounded with spaces, a tab, and trailing newline" => [
                " \t \"foo\" \n",
                [Tokenizer::T_STRING, "foo", 1]
            ],
            "string with escaped backspace" => [
                '"\b"',
                [Tokenizer::T_STRING, "\x8", 1]
            ],
            "string with escaped double quote" => [
                '"\""',
                [Tokenizer::T_STRING, "\"", 1]
            ],
            "string with escaped backslash" => [
                '"\\\\"',
                [Tokenizer::T_STRING, "\\", 1]
            ],
            "string with escaped forward slash" => [
                '"\/"',
                [Tokenizer::T_STRING, "/", 1]
            ],
            "string with escaped newline" => [
                '"\n"',
                [Tokenizer::T_STRING, "\n", 1]
            ],
            "string with escaped carriage return" => [
                '"\r"',
                [Tokenizer::T_STRING, "\r", 1]
            ],
            "string with escaped form feed" => [
                '"\f"',
                [Tokenizer::T_STRING, "\f", 1]
            ],
            "string with escaped tab" => [
                '"\t"',
                [Tokenizer::T_STRING, "\t", 1]
            ],
            "string with unicode escaped a" => [
                '"\u0061"',
                [Tokenizer::T_STRING, "a", 1]
            ],
            "string with unicode escaped null" => [
                '"\u0000"',
                [Tokenizer::T_STRING, "\0", 1]
            ],
            "string with unicode escaped line separator" => [
                '"\u2028"',
                [Tokenizer::T_STRING, "\u{2028}", 1]
            ],
            "string with line separator" => [
                "\"\u{2028}\"",
                [Tokenizer::T_STRING, "\u{2028}", 1]
            ],
            "string with unicode escaped paragraph separator" => [
                '"\u2029"',
                [Tokenizer::T_STRING, "\u{2029}", 1]
            ],
            "string with paragraph separator" => [
                "\"\u{2029}\"",
                [Tokenizer::T_STRING, "\u{2029}", 1]
            ],
            "string with unicode escaped surrogate pair" => [
                '"\uD83D\uDC18"',
                [Tokenizer::T_STRING, "\u{1F418}", 1]
            ],
            "string with DEL" => [
                "\"\x7f\"",
                [Tokenizer::T_STRING, "\x7f", 1]
            ],
            "string with escape sequence followed by text" => [
                '"\t foo"',
                [Tokenizer::T_STRING, "\t foo", 1]
            ],
            "simple number" => [
                '42',
                [Tokenizer::T_NUMBER, "42", 1]
            ],
            "number with surrounding space" => [
                ' 42 ',
                [Tokenizer::T_NUMBER, "42", 1]
            ],
            "negative zero with fractional part" => [
                '-0.8',
                [Tokenizer::T_NUMBER, "-0.8", 1]
            ],
            "number with exponent" => [
                '42e5',
                [Tokenizer::T_NUMBER, "42e5", 1]
            ],
            "number with fractional part and negative exponent" => [
                '42.8e-5',
                [Tokenizer::T_NUMBER, "42.8e-5", 1]
            ],
            "literal true" => [
                'true',
                [Tokenizer::T_TRUE, true, 1]
            ],
            "literal false" => [
                'false',
                [Tokenizer::T_FALSE, false, 1]
            ],
            "literal null" => [
                'null',
                [Tokenizer::T_NULL, null, 1]
            ],
            "colon" => [
                ':',
                [Tokenizer::T_COLON, null, 1]
            ],
            "comma" => [
                ',',
                [Tokenizer::T_COMMA, null, 1]
            ],
            "open bracket" => [
                '[',
                [Tokenizer::T_BEGIN_ARRAY, null, 1]
            ],
            "close bracket" => [
                ']',
                [Tokenizer::T_END_ARRAY, null, 1]
            ],
            "open curly brace" => [
                '{',
                [Tokenizer::T_BEGIN_OBJECT, null, 1]
            ],
            "close curly brace" => [
                '}',
                [Tokenizer::T_END_OBJECT, null, 1]
            ]
        ];
    }

    public function provideTestLexerError()
    {
        return [
            "lone double quote" => [
                '"',
                "Line 1: Unexpected end of file."
            ],
            "new line then double quote" => [
                "\n\"",
                "Line 2: Unexpected end of file."
            ],
            "unfinished true" => [
                't',
                "Line 1: Unexpected end of file."
            ],
            "malformed false" => [
                'faL',
                "Line 1: Unexpected 'L'."
            ],
            "unfinished null then space" => [
                'nul ',
                "Line 1: Unexpected ' '."
            ],
            "lone negative sign" => [
                '-',
                "Line 1: Unexpected end of file."
            ],
            "negative sign followed by non-digit" => [
                '-a',
                "Line 1: Unexpected 'a'."
            ],
            "number truncated after decimal point" => [
                '0.',
                "Line 1: Unexpected end of file."
            ],
            "number with malformed fractional part" => [
                '0.a',
                "Line 1: Unexpected 'a'."
            ],
            "number with malformed exponent" => [
                '0.4eb',
                "Line 1: Unexpected 'b'."
            ],
            "number with truncated exponent" => [
                '0.4e',
                "Line 1: Unexpected end of file."
            ],
            "number with truncated signed exponent" => [
                '0.4e+',
                "Line 1: Unexpected end of file."
            ],
            "invalid escape sequence" => [
                '"\h"',
                "Line 1: Unexpected 'h'."
            ],
            "malformed unicode escape sequence" => [
                '"\u454Z"',
                "Line 1: Unexpected 'Z'."
            ],
            "malformed unicode escape sequence with multibyte character" => [
                '"\u454' . "\u{1F418}\"",
                "Line 1: Unexpected '\u{1F418}'."
            ],
            "truncated unicode escape sequence" => [
                '"\u',
                "Line 1: Unexpected end of file."
            ],
            "string with record separator control character" => [
                "\"\x1e\"",
                "Line 1: Unexpected control character \\u{1E}."
            ],
            "string with new line" => [
                "\"\n\"",
                "Line 1: Unexpected control character \\u{A}."
            ],
            "string with carriage return" => [
                "\"\r\"",
                "Line 1: Unexpected control character \\u{D}."
            ],
            "string with invalid UTF-8 first byte" => [
                "\"\x81\"",
                "Line 1: Ill-formed UTF-8 sequence 0x81."
            ],
            "string with invalid UTF-8 first byte, two byte sequence" => [
                "\"\xC0\xAF\"",
                "Line 1: Ill-formed UTF-8 sequence 0xC0 0xAF."
            ],
            "string with invalid UTF-8 second byte, three byte sequence" => [
                "\"\xE0\x9F\x80\"",
                "Line 1: Ill-formed UTF-8 sequence 0xE0 0x9F 0x80."
            ],
            "string with invalid UTF-8 second byte, four byte sequence" => [
                "\"\xF0\x8F\x80\x80\"",
                "Line 1: Ill-formed UTF-8 sequence 0xF0 0x8F 0x80 0x80."
            ],
            "string with invalid UTF-8 first byte above Unicode range" => [
                "\"\xFF\"",
                "Line 1: Ill-formed UTF-8 sequence 0xFF."
            ],
            "DEL character" => [
                "\x7f",
                "Line 1: Unexpected non-printable character \\u{7F}."
            ],
            "lone low surrogate" => [
                '"\uDC18"',
                "Line 1: Unexpected UTF-16 low surrogate \\uDC18."
            ],
            "two low surrogates" => [
                '"\uD83D\uD83D"',
                "Line 1: Expected UTF-16 low surrogate, got \\uD83D."
            ],
            "bare multibyte character" => [
                "\u{1F418}",
                "Line 1: Unexpected '\u{1F418}'."
            ],
            "bare invalid UTF-8 byte, high out of range" => [
                "\xFF",
                "Line 1: Ill-formed UTF-8 sequence 0xFF."
            ],
            "bare invalid UTF-8 first byte, two byte sequence" => [
                "\xC0\x80",
                "Line 1: Ill-formed UTF-8 sequence 0xC0 0x80."
            ],
            "bare invalid UTF-8 two byte sequence, second byte not a continuation byte" => [
                "\xE0\x00",
                "Line 1: Ill-formed UTF-8 sequence 0xE0."
            ],
            "bare invalid UTF-8 four byte sequence, second byte high out of range" => [
                "\xF4\x90\x80\x80",
                "Line 1: Ill-formed UTF-8 sequence 0xF4 0x90 0x80 0x80."
            ],
            "string truncated at escape sequence" => [
                '"\\',
                "Line 1: Unexpected end of file."
            ]
        ];
    }

    private function getInputStream(): InputStream
    {
        return new class() implements InputStream
        {
            private $string = "";

            public function setString(string $string)
            {
                $this->string = $string;
            }

            public function read()
            {
                $string = $this->string;
                if ($string === "") {
                    return null;
                }
                $this->string = "";

                return $string;
            }
        };
    }

    private function getInputStreamWithSmallBuffer(): InputStream
    {
        return new class() implements InputStream
        {
            private $chunks = [];

            public function setString(string $string)
            {
                $this->chunks = \str_split($string);
            }

            public function read()
            {
                $chunks = &$this->chunks;

                if (($current = \current($chunks)) === false) {
                    return null;
                }
                next($chunks);

                return $current;
            }
        };
    }
}
