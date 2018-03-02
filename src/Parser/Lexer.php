<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

use pcrov\JsonReader\InputStream\InputStream;
use function pcrov\Unicode\surrogate_pair_to_code_point;
use function pcrov\Unicode\utf8_get_invalid_byte_sequence;

final class Lexer implements Tokenizer
{
    private $inputStream;
    private $buffer = "";
    private $offset = 0;
    private $line = 1;

    public function __construct(InputStream $inputStream)
    {
        $this->inputStream = $inputStream;
    }

    /**
     * @throws ParseException
     */
    public function read(): Token
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;
        $line = &$this->line;

        while (isset($buffer[$offset]) || $this->refillBuffer()) {
            switch ($byte = $buffer[$offset]) {
                case " ":
                case "\t":
                    $offset++;
                    break;
                case "\n":
                    $offset++;
                    $line++;
                    break;
                case "\r":
                    $offset++;
                    $line++;
                    if (
                        (isset($buffer[$offset]) || $this->refillBuffer())
                        && $buffer[$offset] === "\n"
                    ) {
                        $offset++;
                    }
                    break;
                case ":":
                    $offset++;
                    return new Token(Token::T_COLON, null, $line);
                case ",":
                    $offset++;
                    return new Token(Token::T_COMMA, null, $line);
                case "[":
                    $offset++;
                    return new Token(Token::T_BEGIN_ARRAY, null, $line);
                case "]":
                    $offset++;
                    return new Token(Token::T_END_ARRAY, null, $line);
                case "{":
                    $offset++;
                    return new Token(Token::T_BEGIN_OBJECT, null, $line);
                case "}":
                    $offset++;
                    return new Token(Token::T_END_OBJECT, null, $line);
                case "t":
                    $this->consumeLiteral("true");
                    return new Token(Token::T_TRUE, true, $line);
                case "f":
                    $this->consumeLiteral("false");
                    return new Token(Token::T_FALSE, false, $line);
                case "n":
                    $this->consumeLiteral("null");
                    return new Token(Token::T_NULL, null, $line);
                case '"':
                    $offset++;
                    return new Token(Token::T_STRING, $this->evaluateDoubleQuotedString(), $line);
                default:
                    if ($byte === "-" || \ctype_digit($byte)) {
                        return new Token(Token::T_NUMBER, $this->evaluateNumber(), $line);
                    }
                    throw new ParseException($this->getExceptionMessage());
            }
        }

        return new Token(Token::T_EOF, null, $line);
    }

    /**
     * @throws ParseException
     */
    private function consumeLiteral(string $string)
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;
        $consumeLength = \strlen($string);
        $subject = \substr($buffer, $offset, $consumeLength);

        $diffPosition = \strspn($subject ^ $string, "\0");

        // Match
        if ($diffPosition === $consumeLength) {
            $offset += $diffPosition;
            return;
        }

        $subjectLength = \strlen($subject);

        // No match
        if ($diffPosition !== $subjectLength) {
            $offset += $diffPosition;
            throw new ParseException($this->getExceptionMessage());
        }

        // Leading match at end of buffer
        if (!$this->refillBuffer()) {
            throw new ParseException($this->getExceptionMessage());
        }
        $this->consumeLiteral(\substr($string, $subjectLength));
    }

    /**
     * @throws ParseException
     */
    private function evaluateEscapedSequence(): string
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
            throw new ParseException($this->getExceptionMessage());
        }

        switch ($byte = $buffer[$offset++]) {
            case '"':
            case "\\":
            case "/":
                return $byte;
            case "b":
                return "\x8";
            case "f":
                return "\f";
            case "n":
                return "\n";
            case "r":
                return "\r";
            case "t":
                return "\t";
            case "u":
                return $this->evaluateEscapedUnicodeSequence();
            default:
                $offset--;
                throw new ParseException($this->getExceptionMessage());
        }

    }

    /**
     * @throws ParseException
     */
    private function evaluateDoubleQuotedString(): string
    {
        static $invalidBytes = "\x0\x1\x2\x3\x4\x5\x6\x7\x8\x9\xA\xB\xC\xD\xE\xF\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F";
        static $escapeChar = "\\";
        static $endChar = '"';

        $buffer = &$this->buffer;
        $offset = &$this->offset;

        $string = $this->scanUntil($invalidBytes . $escapeChar . $endChar);

        while (
            (isset($buffer[$offset]) || $this->refillBuffer())
            && $buffer[$offset] !== $endChar
        ) {
            $currentByte = $buffer[$offset];

            // Invalid
            if ($currentByte <= "\x1f") {
                throw new ParseException(
                    \sprintf(
                        "Line %d: Unexpected control character \\u{%X}.",
                        $this->line, \ord($currentByte)
                    )
                );
            }

            // Escape sequence
            if ($currentByte === $escapeChar) {
                $offset++;
                $string .= $this->evaluateEscapedSequence();
                continue;
            }

            $string .= $this->scanUntil($invalidBytes . $escapeChar . $endChar);
        }

        // Unexpected end of file.
        if (!isset($buffer[$offset])) {
            throw new ParseException($this->getExceptionMessage());
        }

        // Invalid UTF-8
        if (($invalidSequence = utf8_get_invalid_byte_sequence($string)) !== null) {
            throw new ParseException($this->getIllFormedUtf8ExceptionMessage($invalidSequence));
        }

        // End "
        $offset++;
        return $string;
    }

    /**
     * Numbers in JSON match the regex:
     *      -?(0|[1-9]\d*)(\.\d+)?([eE][-+]?\d+)?
     *
     * @throws ParseException
     */
    private function evaluateNumber(): string
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        $number = "";
        $byte = $buffer[$offset];

        if ($byte === "-") {
            $offset++;
            $number .= $byte;
            if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
                throw new ParseException($this->getExceptionMessage());
            }
        }

        $byte = $buffer[$offset];
        if ($byte === "0") {
            $offset++;
            $number .= $byte;
        } elseif (\ctype_digit($byte)) {
            $number .= $this->scanDigits();
        } else {
            throw new ParseException($this->getExceptionMessage());
        }

        if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
            return $number;
        }

        // Fractional part.
        $byte = $buffer[$offset];
        if ($byte === ".") {
            $offset++;
            $number .= $byte;
            if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
                throw new ParseException($this->getExceptionMessage());
            }

            $digits = $this->scanDigits();
            if ($digits === "") {
                throw new ParseException($this->getExceptionMessage());
            }

            $number .= $digits;

            if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
                return $number;
            }
        }

        // Exponent.
        $byte = $buffer[$offset];
        if ($byte === "e" || $byte === "E") {
            $offset++;
            $number .= $byte;
            if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
                throw new ParseException($this->getExceptionMessage());
            }

            $byte = $buffer[$offset];
            if ($byte === "-" || $byte === "+") {
                $offset++;
                $number .= $byte;
                if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
                    throw new ParseException($this->getExceptionMessage());
                }
            }

            $digits = $this->scanDigits();
            if ($digits === "") {
                throw new ParseException($this->getExceptionMessage());
            }
            $number .= $digits;
        }

        return $number;
    }

    /**
     * Evaluates the current escaped unicode sequence
     * (beginning after leading the \u).
     *
     * @throws ParseException
     */
    private function evaluateEscapedUnicodeSequence(): string
    {
        $codepoint = (int)\hexdec($this->scanEscapedUnicodeSequence());

        switch (\IntlChar::getBlockCode($codepoint)) {
            case \IntlChar::BLOCK_CODE_HIGH_PRIVATE_USE_SURROGATES:
            case \IntlChar::BLOCK_CODE_HIGH_SURROGATES:
                $this->consumeLiteral("\\u");
                $lowSurrogate = (int)\hexdec($this->scanEscapedUnicodeSequence());

                if (\IntlChar::getBlockCode($lowSurrogate) !== \IntlChar::BLOCK_CODE_LOW_SURROGATES) {
                    throw new ParseException(\sprintf(
                            "Line %d: Expected UTF-16 low surrogate, got \\u%X.",
                            $this->line, $lowSurrogate)
                    );
                }

                $codepoint = surrogate_pair_to_code_point($codepoint, $lowSurrogate);
                break;

            case \IntlChar::BLOCK_CODE_LOW_SURROGATES:
                throw new ParseException(\sprintf(
                        "Line %d: Unexpected UTF-16 low surrogate \\u%X.",
                        $this->line, $codepoint)
                );
        }

        return \IntlChar::chr($codepoint);
    }

    private function refillBuffer(): bool
    {
        do {
            $data = $this->inputStream->read();
        } while ($data === "" && $data !== null);

        $this->buffer = (string)$data;
        $this->offset = 0;

        return $data !== null;
    }

    /**
     * Scans a single UTF-8 encoded Unicode codepoint, which can be up to four
     * bytes long.
     *
     *  0xxx xxxx   Single-byte codepoint.
     *  110x xxxx   First of a two-byte codepoint.
     *  1110 xxxx   First of three.
     *  1111 0xxx   First of four.
     *  10xx xxxx   A continuation of any of the three preceding.
     *
     * @see https://en.wikipedia.org/wiki/UTF-8#Description
     * @see http://www.unicode.org/versions/Unicode9.0.0/ch03.pdf#page=54
     *
     * @throws ParseException on ill-formed UTF-8.
     */
    private function scanCodepoint(): string
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        $codepoint = $buffer[$offset++];
        $ord = \ord($codepoint);

        if (!($ord >> 7)) {
            return $codepoint;
        } elseif (!(($ord >> 5) ^ 0b110)) {
            $expect = 1;
        } elseif (!(($ord >> 4) ^ 0b1110)) {
            $expect = 2;
        } elseif (!(($ord >> 3) ^ 0b11110)) {
            $expect = 3;
        } else {
            $expect = 0; // This'll throw in just a moment.
        }

        $continuationBytes = "";
        do {
            $temp = \substr($buffer, $offset, $expect);
            $deltaLength = \strlen($temp);
            $expect -= $deltaLength;
            $continuationBytes .= $temp;
        } while ($expect > 0 && $this->refillBuffer());

        $offset += $deltaLength;

        for (
            $i = 0, $continuationBytesLength = \strlen($continuationBytes);
            $i < $continuationBytesLength;
            $i++
        ) {
            $byte = $continuationBytes[$i];

            if ((\ord($byte) >> 6) ^ 0b10) {
                break;
            }

            $codepoint .= $byte;
        }

        $chr = \IntlChar::chr($codepoint);

        if ($chr === null) {
            throw new ParseException($this->getIllFormedUtf8ExceptionMessage($codepoint));
        }

        return $chr;
    }

    /**
     * @throws ParseException
     */
    private function getExceptionMessage(): string
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;
        $line = &$this->line;

        if (!isset($buffer[$offset])) {
            return \sprintf(
                "Line %d: Unexpected end of file.",
                $line
            );
        }

        $codepoint = $this->scanCodepoint();

        if (\IntlChar::isprint($codepoint)) {
            return \sprintf(
                "Line %d: Unexpected '%s'.",
                $line, $codepoint
            );
        }

        return \sprintf(
            "Line %d: Unexpected non-printable character \\u{%X}.",
            $line, \IntlChar::ord($codepoint)
        );
    }

    private function getIllFormedUtf8ExceptionMessage(string $string)
    {
        return \sprintf(
            "Line %d: Ill-formed UTF-8 sequence" . \str_repeat(" 0x%X", \strlen($string)) . ".",
            $this->line, ...\unpack("C*", $string)
        );
    }

    private function scanDigits(): string
    {
        static $digits = "0123456789";

        return $this->scanWhile($digits);
    }

    /**
     * Scans four hexadecimal characters.
     *
     * @throws ParseException
     */
    private function scanEscapedUnicodeSequence(): string
    {
        static $hexChars = "0123456789ABCDEFabcdef";
        static $length = 4;

        $sequence = $this->scanWhile($hexChars, $length);

        if (\strlen($sequence) < $length) {
            throw new ParseException($this->getExceptionMessage());
        }

        return $sequence;
    }

    private function scanUntil(string $mask): string
    {
        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
            return "";
        }

        $matchedLength = \strcspn($buffer, $mask, $offset);
        $matchedBytes = \substr($buffer, $offset, $matchedLength);
        $offset += $matchedLength;

        // Complete match
        if (isset($buffer[$offset]) || !$this->refillBuffer()) {
            return $matchedBytes;
        }

        // Possibly more to come
        return $matchedBytes . $this->scanUntil($mask);
    }

    private function scanWhile(string $mask, int $maxLength = null): string
    {
        \assert($maxLength >= 0, "maxLength cannot be negative");

        $buffer = &$this->buffer;
        $offset = &$this->offset;

        if (!isset($buffer[$offset]) && !$this->refillBuffer()) {
            return "";
        }

        if ($maxLength === null) {
            $matchedLength = \strspn($buffer, $mask, $offset);
        } else {
            $matchedLength = \strspn($buffer, $mask, $offset, $maxLength);
        }
        $matchedBytes = \substr($buffer, $offset, $matchedLength);
        $offset += $matchedLength;

        // Complete match
        if ($matchedLength === $maxLength || isset($buffer[$offset]) || !$this->refillBuffer()) {
            return $matchedBytes;
        }

        // Possibly more to come
        $needed = $maxLength === null ? null : $maxLength - $matchedLength;
        return $matchedBytes . $this->scanWhile($mask, $needed);
    }
}
