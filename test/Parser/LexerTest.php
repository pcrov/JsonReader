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
        $this->assertEquals($expectedToken, $token);
    }

    /** @dataProvider provideTestTokenization */
    public function testTokenizationWithSmallBuffer($input, $expectedToken)
    {
        $inputStream = $this->getInputStreamWithSmallBuffer();
        $inputStream->setString($input);
        $lexer = new Lexer($inputStream);

        $token = $lexer->read();
        $this->assertEquals($expectedToken, $token);
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
        } while ($token->getType() !== Token::T_EOF);
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
        } while ($token->getType() !== Token::T_EOF);
    }

    public function provideTestTokenization()
    {
        return [
            "variety of line breaks" => [
                "\n\r\r\n\r\n",
                new Token(Token::T_EOF, 5)
            ],
            "simple string" => [
                '"foo"',
                new Token(Token::T_STRING, 1, "foo")
            ],
            "string surrounded with spaces, a tab, and trailing newline" => [
                " \t \"foo\" \n",
                new Token(Token::T_STRING, 1, "foo")
            ],
            "string with escaped backspace" => [
                '"\b"',
                new Token(Token::T_STRING, 1, "\x8")
            ],
            "string with escaped double quote" => [
                '"\""',
                new Token(Token::T_STRING, 1, "\"")
            ],
            "string with escaped backslash" => [
                '"\\\\"',
                new Token(Token::T_STRING, 1, "\\")
            ],
            "string with escaped forward slash" => [
                '"\/"',
                new Token(Token::T_STRING, 1, "/")
            ],
            "string with escaped newline" => [
                '"\n"',
                new Token(Token::T_STRING, 1, "\n")
            ],
            "string with escaped carriage return" => [
                '"\r"',
                new Token(Token::T_STRING, 1, "\r")
            ],
            "string with escaped form feed" => [
                '"\f"',
                new Token(Token::T_STRING, 1, "\f")
            ],
            "string with escaped tab" => [
                '"\t"',
                new Token(Token::T_STRING, 1, "\t")
            ],
            "string with unicode escaped a" => [
                '"\u0061"',
                new Token(Token::T_STRING, 1, "a")
            ],
            "string with unicode escaped null" => [
                '"\u0000"',
                new Token(Token::T_STRING, 1, "\0")
            ],
            "string with unicode escaped line separator" => [
                '"\u2028"',
                new Token(Token::T_STRING, 1, "\u{2028}")
            ],
            "string with line separator" => [
                "\"\u{2028}\"",
                new Token(Token::T_STRING, 1, "\u{2028}")
            ],
            "string with unicode escaped paragraph separator" => [
                '"\u2029"',
                new Token(Token::T_STRING, 1, "\u{2029}")
            ],
            "string with paragraph separator" => [
                "\"\u{2029}\"",
                new Token(Token::T_STRING, 1, "\u{2029}")
            ],
            "string with unicode escaped surrogate pair" => [
                '"\uD83D\uDC18"',
                new Token(Token::T_STRING, 1, "\u{1F418}")
            ],
            "string with DEL" => [
                "\"\x7f\"",
                new Token(Token::T_STRING, 1, "\x7f")
            ],
            "simple number" => [
                '42',
                new Token(Token::T_NUMBER, 1, "42")
            ],
            "negative zero with fractional part" => [
                '-0.8',
                new Token(Token::T_NUMBER, 1, "-0.8")
            ],
            "number with exponent" => [
                '42e5',
                new Token(Token::T_NUMBER, 1, "42e5")
            ],
            "number with fractional part and negative exponent" => [
                '42.8e-5',
                new Token(Token::T_NUMBER, 1, "42.8e-5")
            ],
            "literal true" => [
                'true',
                new Token(Token::T_TRUE, 1, true)
            ],
            "literal false" => [
                'false',
                new Token(Token::T_FALSE, 1, false)
            ],
            "literal null" => [
                'null',
                new Token(Token::T_NULL, 1, null)
            ],
            "colon" => [
                ':',
                new Token(Token::T_COLON, 1)
            ],
            "comma" => [
                ',',
                new Token(Token::T_COMMA, 1)
            ],
            "open bracket" => [
                '[',
                new Token(Token::T_BEGIN_ARRAY, 1)
            ],
            "close bracket" => [
                ']',
                new Token(Token::T_END_ARRAY, 1)
            ],
            "open curly brace" => [
                '{',
                new Token(Token::T_BEGIN_OBJECT, 1)
            ],
            "close curly brace" => [
                '}',
                new Token(Token::T_END_OBJECT, 1)
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
            "number with malformed fractional part" => [
                '0.a',
                "Line 1: Unexpected 'a'."
            ],
            "number with malformed exponent" => [
                '0.4eb',
                "Line 1: Unexpected 'b'."
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
            "bare invalid UTF-8 first byte, two byte sequence" => [
                "\xC0\x80",
                "Line 1: Ill-formed UTF-8 sequence 0xC0 0x80."
            ],
            "bare invalid UTF-8 two byte sequence, second byte not a continuation byte" => [
                "\xE0\x00",
                "Line 1: Ill-formed UTF-8 sequence 0xE0."
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
