<?php declare(strict_types = 1);

namespace pcrov\JsonReader\Parser;

/**
 * Class Lexer
 *
 * Does most scanning and evaluation in the same pass.
 *
 * @package JsonReader
 */
final class Lexer implements \IteratorAggregate, Tokenizer
{
    /**
     * @var \Traversable
     */
    private $bytestream;

    /**
     * @var \IteratorIterator Iterator of the $bytestream
     */
    private $byteIterator;

    /**
     * @var int Current line number.
     */
    private $line;


    /**
     * Lexer constructor.
     * @param \Traversable $bytestream Bytestream to lex. Each iteration should provide a single byte.
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
            $iterator->next();
            switch ($byte) {
                case " ":
                case "\t":
                    break;
                case "\n":
                    $this->line++;
                    break;
                case "\r":
                    $this->line++;
                    if ($iterator->current() === "\n") {
                        $iterator->next();
                    }
                    break;
                case ":":
                    yield self::T_COLON => null;
                    break;
                case ",":
                    yield self::T_COMMA => null;
                    break;
                case "[":
                    yield self::T_BEGIN_ARRAY => null;
                    break;
                case "]":
                    yield self::T_END_ARRAY => null;
                    break;
                case "{":
                    yield self::T_BEGIN_OBJECT => null;
                    break;
                case "}":
                    yield self::T_END_OBJECT => null;
                    break;
                case "t":
                    $this->consumeLiteral("rue");
                    yield self::T_TRUE => true;
                    break;
                case "f":
                    $this->consumeLiteral("alse");
                    yield self::T_FALSE => false;
                    break;
                case "n":
                    $this->consumeLiteral("ull");
                    yield self::T_NULL => null;
                    break;
                case '"':
                    yield self::T_STRING => $this->evaluateDoubleQuotedString();
                    break;
                default:
                    if ($byte === "-" || ctype_digit($byte)) {
                        yield self::T_NUMBER => $this->evaluateNumber($byte);
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
     * Consumes and discards bytes from the byte iterator exactly matching the given string.
     *
     * @param string $string
     * @throws ParseException
     */
    private function consumeLiteral(string $string)
    {
        $iterator = $this->byteIterator;
        $length = strlen($string);

        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i < $length; $i++) {
            $byte = $iterator->current();
            $iterator->next();
            if ($byte !== $string[$i]) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
        }
    }

    private function evaluateEscapeSequence() : string
    {
        $iterator = $this->byteIterator;
        $byte = $iterator->current();
        $iterator->next();

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
                return $this->evaluateUnicodeSequence();
            default:
                throw new ParseException($this->getExceptionMessage($byte));
        }

    }

    private function evaluateDoubleQuotedString() : string
    {
        $iterator = $this->byteIterator;
        $buffer = "";

        while ($iterator->valid()) {
            $byte = $iterator->current();
            $iterator->next();

            if ($byte === '"') {
                return $buffer;
            }

            if ($byte <= "\x1f") {
                throw new ParseException($this->getExceptionMessage($byte));
            }

            if ($byte === "\\") {
                $buffer .= $this->evaluateEscapeSequence();
            } else {
                $buffer .= $byte;
            }
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
     * @param string $byte Initial byte. The bytestream cursor starts one position ahead of this.
     * @return float|int
     * @throws ParseException
     */
    private function evaluateNumber(string $byte)
    {
        $iterator = $this->byteIterator;
        assert($byte === "-" || ctype_digit($byte));
        $buffer = "";

        if ($byte === "-") {
            $buffer .= $byte;
            $byte = $iterator->current();
            $iterator->next();
        }

        if ($byte === "0") {
            $buffer .= $byte;
        } elseif (ctype_digit($byte)) {
            $buffer .= $byte . $this->scanDigits();
        } else {
            throw new ParseException($this->getExceptionMessage($byte));
        }

        /**
         * Catch up to the cursor. From here on we have to take care not to overshoot,
         * else we risk losing the byte immediately following the number.
         */
        $byte = $iterator->current();

        if ($byte === ".") {
            $buffer .= $byte;
            $iterator->next();
            $byte = $iterator->current();
            $iterator->next();
            if (!ctype_digit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $buffer .= $byte . $this->scanDigits();
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
                $iterator->next();
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
                $this->consumeLiteral("\\u");
                $lowSurrogate = hexdec($this->scanUnicodeSequence());

                if (\IntlChar::getBlockCode($lowSurrogate) !== \IntlChar::BLOCK_CODE_LOW_SURROGATES) {
                    throw new ParseException(sprintf(
                            "Line %d: Expected UTF-16 low surrogate, got \\u%X.",
                            $this->getLineNumber(), $lowSurrogate)
                    );
                }

                $codepoint = $this->surrogatePairToCodepoint($codepoint, $lowSurrogate);
                break;

            case \IntlChar::BLOCK_CODE_LOW_SURROGATES:
                throw new ParseException(sprintf(
                        "Line %d: Unexpected UTF-16 low surrogate \\u%X.",
                        $this->getLineNumber(), $codepoint)
                );
        }

        return \IntlChar::chr($codepoint);
    }

    /**
     * Scans a single UTF-8 codepoint, which can be up to four bytes long.
     *
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
     * @param string $byte Initial byte. The bytestream cursor starts one position ahead of this.
     * @return string The scanned full or partial codepoint, or invalid UTF-8 byte.
     */
    private function fillCodepoint(string $byte) : string
    {
        $iterator = $this->byteIterator;
        $codepoint = $byte;
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

    private function getExceptionMessage(string $byte = null) : string
    {
        if ($byte === null) {
            return sprintf(
                "Line %d: Unexpected end of file.",
                $this->getLineNumber()
            );
        }

        $codepoint = $this->fillCodepoint($byte);
        $ord = \IntlChar::ord($codepoint);

        if ($ord === null) {
            return sprintf(
                "Line %d: Malformed UTF-8 sequence" . str_repeat(" 0x%X", strlen($codepoint)) . ".",
                $this->getLineNumber(), ...array_map("ord", str_split($codepoint))
            );
        }

        if (\IntlChar::isprint($codepoint)) {
            return sprintf(
                "Line %d: Unexpected '%s'.",
                $this->getLineNumber(), $codepoint
            );
        }

        return sprintf(
            "Line %d: Unexpected control character \\u{%X}.",
            $this->getLineNumber(), $ord
        );
    }

    private function initByteIterator()
    {
        $iterator = new \IteratorIterator($this->bytestream);
        $iterator->rewind();
        $this->byteIterator = $iterator;
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
            $iterator->next();
            if (!ctype_xdigit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $sequence .= $byte;
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
