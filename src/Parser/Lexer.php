<?php declare(strict_types = 1);

namespace pcrov\JsonReader\Parser;

/**
 * Class Lexer
 *
 * Does most scanning and evaluation in the same pass.
 *
 * @package JsonReader
 */
class Lexer implements \IteratorAggregate, Tokenizer
{
    /**
     * @var \Traversable
     */
    private $bytestream;

    /**
     * @var \Iterator Iterator provided by the $bytestream, which might be the bytestream itself.
     */
    private $byteIterator;

    /**
     * @var int Current line number.
     */
    private $line;


    /**
     * Lexer constructor.
     * @param \Traversable $bytestream Byte stream to lex. Each iteration should provide a single byte.
     */
    public function __construct(\Traversable $bytestream)
    {
        $this->bytestream = $bytestream;
    }

    /**
     * Reads from the bytestream and generates a token stream in the form of token => value.
     *
     * @return \Generator
     * @throws ParseException
     */
    public function getIterator() : \Generator
    {
        $this->initByteIterator();
        $iterator = $this->byteIterator;
        $this->line = 1;

        while ($iterator->valid()) {
            $byte = $iterator->current();
            switch ($byte) {
                case " ":
                case "\t":
                    $iterator->next();
                    break;
                case "\n":
                    $this->line++;
                    $iterator->next();
                    break;
                case "\r":
                    $this->consumeCarriageReturn();
                    break;
                case ":":
                    yield self::T_COLON => null;
                    $iterator->next();
                    break;
                case ",":
                    yield self::T_COMMA => null;
                    $iterator->next();
                    break;
                case "[":
                    yield self::T_BEGIN_ARRAY => null;
                    $iterator->next();
                    break;
                case "]":
                    yield self::T_END_ARRAY => null;
                    $iterator->next();
                    break;
                case "{":
                    yield self::T_BEGIN_OBJECT => null;
                    $iterator->next();
                    break;
                case "}":
                    yield self::T_END_OBJECT => null;
                    $iterator->next();
                    break;
                case "t":
                    $this->consumeString("true");
                    yield self::T_TRUE => true;
                    break;
                case "f":
                    $this->consumeString("false");
                    yield self::T_FALSE => false;
                    break;
                case "n":
                    $this->consumeString("null");
                    yield self::T_NULL => null;
                    break;
                case '"':
                    yield self::T_STRING => $this->evaluateDoubleQuotedString();
                    break;
                default:
                    if (ctype_digit($byte) || $byte === "-") {
                        yield self::T_NUMBER => $this->evaluateNumber();
                    } else {
                        throw new ParseException($this->getExceptionMessage($byte));
                    }
            }
        }
    }

    public function getLineNumber() : int
    {
        return $this->line;
    }

    /**
     * Consumes the current \r as well as a single immediately
     * following \n in order to treat \r\n as one newline.
     */
    private function consumeCarriageReturn()
    {
        $iterator = $this->byteIterator;
        assert($iterator->current() === "\r");

        $this->line++;
        $iterator->next();
        if ($iterator->current() === "\n") {
            $iterator->next();
        }
    }

    /**
     * Consumes and discards bytes from the byte iterator exactly matching the given string.
     *
     * @param string $string
     * @throws ParseException
     */
    private function consumeString(string $string)
    {
        $iterator = $this->byteIterator;
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $byte = $iterator->current();
            if ($byte !== $string[$i]) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $iterator->next();
        }
    }

    private function evaluateEscapeSequence() : string
    {
        $iterator = $this->byteIterator;
        assert($iterator->current() === "\\");
        $iterator->next();
        $byte = $iterator->current();

        switch ($byte) {
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
                $iterator->next();
                return $this->evaluateUnicodeSequence();
            default:
                throw new ParseException($this->getExceptionMessage($byte));
        }

    }

    private function evaluateDoubleQuotedString() : string
    {
        $iterator = $this->byteIterator;
        $buffer = "";
        assert($iterator->current() === '"');
        $iterator->next(); //Skip initial "

        while ($iterator->valid()) {
            $byte = $iterator->current();

            if ($byte === '"') {
                $iterator->next();
                return $buffer;
            }

            if ($byte < "\x1f") {
                throw new ParseException($this->getExceptionMessage($byte));
            }

            $buffer .= ($byte === "\\") ? $this->evaluateEscapeSequence() : $byte;

            $iterator->next();
        }

        // Unexpected end of file.
        throw new ParseException($this->getExceptionMessage());
    }

    /**
     * Scans and evaluates the current number.
     *
     * Numbers in JSON match the regex:
     *      -?(0|[1-9]\d*)(\.\d+)?([eE][-+]?\d+)?
     *
     * Doing it byte by byte is less fun, but here we are.
     *
     * @return int|float
     * @throws ParseException
     */
    private function evaluateNumber()
    {
        $iterator = $this->byteIterator;
        $byte = $iterator->current();
        assert($byte === "-" || ctype_digit($byte));
        $buffer = "";

        if ($byte === "-") {
            $buffer .= $byte;
            $iterator->next();
            $byte = $iterator->current();
        }

        if ($byte === "0") {
            $buffer .= $byte;
            $iterator->next();
            $byte = $iterator->current();
        } elseif (ctype_digit($byte)) {
            $buffer .= $this->scanDigits();
            $byte = $iterator->current();
        } else {
            throw new ParseException($this->getExceptionMessage($byte));
        }

        if ($byte === ".") {
            $buffer .= $byte;
            $iterator->next();
            $byte = $iterator->current();
            if (!ctype_digit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $buffer .= $this->scanDigits();
            $byte = $iterator->current();
        }

        if ($byte === "e" || $byte === "E") {
            $buffer .= $byte;
            $iterator->next();
            $byte = $iterator->current();

            if ($byte === "-" || $byte === "+") {
                $buffer .= $byte;
                $iterator->next();
                $byte = $iterator->current();
            }

            if (!ctype_digit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $buffer .= $this->scanDigits();
        }

        /** @noinspection PhpWrongStringConcatenationInspection
         *
         * `+ 0` automatically casts to float or int, as appropriate.
         */
        return $buffer + 0;
    }

    private function evaluateUnicodeSequence() : string
    {
        $codepoint = hexdec($this->scanUnicodeSequence());

        switch (\IntlChar::getBlockCode($codepoint)) {
            case \IntlChar::BLOCK_CODE_HIGH_PRIVATE_USE_SURROGATES:
            case \IntlChar::BLOCK_CODE_HIGH_SURROGATES:
                $this->consumeString("\\u");
                $lowSurrogate = hexdec($this->scanUnicodeSequence());

                if (\IntlChar::getBlockCode($lowSurrogate) !== \IntlChar::BLOCK_CODE_LOW_SURROGATES) {
                    throw new ParseException(sprintf(
                            "Line %d: Expected UTF-16 low surrogate, got \\u%x.",
                            $this->line, $lowSurrogate)
                    );
                }

                $codepoint = $this->surrogatePairToCodepoint($codepoint, $lowSurrogate);
                break;

            case \IntlChar::BLOCK_CODE_LOW_SURROGATES:
                throw new ParseException(sprintf(
                        "Line %d: Unexpected UTF-16 low surrogate \\u%x.",
                        $this->line, $codepoint)
                );
                break;
        }

        return \IntlChar::chr($codepoint);
    }

    private function getExceptionMessage(string $byte = null) : string
    {
        if ($byte === null) {
            return sprintf(
                "Line %d: Unexpected end of file.",
                $this->line
            );
        }

        $codepoint = $this->scanCodepoint();
        $ord = \IntlChar::ord($codepoint);

        if ($ord === null) {
            return sprintf(
                "Line %d: Malformed UTF-8 sequence" . str_repeat(" 0x%X", strlen($codepoint)) . ".",
                $this->line, ...array_map("ord", str_split($codepoint))
            );
        }

        if (\IntlChar::isprint($codepoint)) {
            return sprintf(
                "Line %d: Unexpected '%s'.",
                $this->line, $codepoint
            );
        }

        return sprintf(
            "Line %d: Unexpected control character \\u{%x}.",
            $this->line, $ord
        );
    }

    private function initByteIterator()
    {
        $bytestream = $this->bytestream;

        /** @var \Iterator $iterator */
        $iterator = ($bytestream instanceof \IteratorAggregate) ? $bytestream->getIterator() : $bytestream;
        $iterator->rewind();
        $this->byteIterator = $iterator;
    }

    /**
     * Scans a single UTF-8 codepoint, which can be up to four bytes long.
     *
     * The cursor should be at the beginning of a valid UTF-8 sequence.
     * A partial codepoint or invalid UTF-8 byte will be returned as-is.
     *
     *  0xxx xxxx   Single-byte codepoint.
     *  110x xxxx   First of a two-byte codepoint.
     *  1110 xxxx   First of three.
     *  1111 0xxx   First of four.
     *  10xx xxxx   A continuation of any of the three preceding.
     *
     * @see https://en.wikipedia.org/wiki/UTF-8#Description
     *
     * @return string The scanned full or partial codepoint, or invalid UTF-8 byte.
     */
    private function scanCodepoint() : string
    {
        $iterator = $this->byteIterator;
        $codepoint = $iterator->current();
        $iterator->next();
        $ord = ord($codepoint);

        if (!(($ord >> 5) ^ 0b110)) {
            $expect = 1;
        } elseif (!(($ord >> 4) ^ 0b1110)) {
            $expect = 2;
        } elseif (!(($ord >> 3) ^ 0b11110)) {
            $expect = 3;
        } else {
            return $codepoint;
        }

        while ($iterator->valid() && $expect > 0) {
            $byte = $iterator->current();

            if ((ord($byte) >> 6) ^ 0b10) {
                break;
            }

            $codepoint .= $byte;
            $iterator->next();
            $expect--;
        }

        return $codepoint;
    }

    private function scanDigits() : string
    {
        $iterator = $this->byteIterator;
        $digits = "";

        while (ctype_digit($iterator->current())) {
            $digits .= $iterator->current();
            $iterator->next();
        }

        return $digits;
    }

    /**
     * Scans the current unicode sequence.
     *
     * A unicode sequence is a string of four hexadecimal characters.
     *
     * @return string The scanned sequence.
     * @throws ParseException
     */
    private function scanUnicodeSequence() : string
    {
        $iterator = $this->byteIterator;
        $sequence = "";

        for ($i = 0; $i < 4; $i++) {
            $byte = $iterator->current();
            if (!ctype_xdigit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $sequence .= $byte;

            $iterator->next();
        }

        return $sequence;
    }

    /**
     * Translates a UTF-16 surrogate pair into a single codepoint.
     *
     * Example: \uD852\uDF62 == \u{24B62} == 𤭢
     *
     * @param int $high high surrogate
     * @param int $low low surrogate
     * @return int codepoint
     */
    private function surrogatePairToCodepoint(int $high, int $low) : int
    {
        assert($high >= 0xd800 && $high <= 0xdbff, "High surrogate out of range.");
        assert($low >= 0xdc00 && $low <= 0xdfff, "Low surrogate out of range.");

        return 0x10000 + (($high & 0x03ff) << 10) + ($low & 0x03ff);
    }
}
