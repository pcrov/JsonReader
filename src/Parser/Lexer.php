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
     * @var \IteratorIterator Iterator of the $bytestream.
     */
    private $byteIterator;

    /**
     * @var int Current line number.
     */
    private $line;


    /**
     * Lexer constructor.
     *
     * @param \Traversable $bytestream Bytestream to lex. Each iteration should
     *                                 provide a single byte.
     */
    public function __construct(\Traversable $bytestream)
    {
        $this->bytestream = $bytestream;
    }

    /**
     * Reads from the bytestream and generates a token stream in the form of
     * token => value.
     *
     * @return \Generator
     * @throws ParseException
     */
    public function getIterator() : \Generator
    {
        $this->initByteIterator();
        $bytes = $this->byteIterator;
        $this->line = 1;

        while ($bytes->valid()) {
            $byte = $bytes->current();
            $bytes->next();
            switch ($byte) {
                case " ":
                case "\t":
                    break;
                case "\n":
                    $this->line++;
                    break;
                case "\r":
                    $this->line++;
                    if ($bytes->current() === "\n") {
                        $bytes->next();
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
     * Consumes and discards bytes from the byte iterator exactly matching the
     * given string.
     *
     * @param string $string
     * @throws ParseException
     */
    private function consumeLiteral(string $string)
    {
        $bytes = $this->byteIterator;
        $length = strlen($string);

        for ($i = 0; $i < $length; $i++) {
            $byte = $bytes->current();
            $bytes->next();
            if ($byte !== $string[$i]) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
        }
    }

    private function evaluateEscapeSequence() : string
    {
        $bytes = $this->byteIterator;
        $byte = $bytes->current();
        $bytes->next();

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
                return $this->evaluateEscapedUnicodeSequence();
            default:
                throw new ParseException($this->getExceptionMessage($byte));
        }

    }

    private function evaluateDoubleQuotedString() : string
    {
        $bytes = $this->byteIterator;
        $buffer = "";

        while ($bytes->valid()) {
            $byte = $bytes->current();
            $bytes->next();

            if ($byte === '"') {
                return $buffer;
            }

            if ($byte <= "\x1f") {
                throw new ParseException(
                    sprintf(
                        "Line %d: Unexpected control character \\u{%X}.",
                        $this->getLineNumber(), ord($byte)
                    )
                );
            }

            if ($byte === "\\") {
                $buffer .= $this->evaluateEscapeSequence();
            } else {
                $buffer .= $this->scanCodepoint($byte);
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
     * @param string $byte Initial byte. The bytestream cursor starts one
     *                     position ahead of this.
     * @return float|int
     * @throws ParseException
     */
    private function evaluateNumber(string $byte)
    {
        assert($byte === "-" || ctype_digit($byte));
        $bytes = $this->byteIterator;
        $buffer = "";

        if ($byte === "-") {
            $buffer .= $byte;
            $byte = $bytes->current();
            $bytes->next();
        }

        if ($byte === "0") {
            $buffer .= $byte;
        } elseif (ctype_digit($byte)) {
            $buffer .= $byte . $this->scanDigits();
        } else {
            throw new ParseException($this->getExceptionMessage($byte));
        }

        // Catch up to the cursor. From here on we have to take care not to
        // overshoot, else we risk losing the byte immediately following the
        // number.
        $byte = $bytes->current();

        // Fractional part.
        if ($byte === ".") {
            $buffer .= $byte;
            $bytes->next();
            $byte = $bytes->current();
            $bytes->next();
            if (!ctype_digit($byte)) {
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $buffer .= $byte . $this->scanDigits();
            $byte = $bytes->current();
        }

        // Exponent.
        if ($byte === "e" || $byte === "E") {
            $buffer .= $byte;
            $bytes->next();
            $byte = $bytes->current();

            if ($byte === "-" || $byte === "+") {
                $buffer .= $byte;
                $bytes->next();
                $byte = $bytes->current();
            }

            if (!ctype_digit($byte)) {
                $bytes->next();
                throw new ParseException($this->getExceptionMessage($byte));
            }
            $buffer .= $this->scanDigits();
        }

        return $buffer;
    }

    /**
     * Evaluates the current escaped unicode sequence
     * (beginning after leading the \u).
     *
     * @return string The evaluated character.
     * @throws ParseException
     */
    private function evaluateEscapedUnicodeSequence() : string
    {
        $codepoint = (int)hexdec($this->scanEscapedUnicodeSequence());

        switch (\IntlChar::getBlockCode($codepoint)) {
            case \IntlChar::BLOCK_CODE_HIGH_PRIVATE_USE_SURROGATES:
            case \IntlChar::BLOCK_CODE_HIGH_SURROGATES:
                $this->consumeLiteral("\\u");
                $lowSurrogate = (int)hexdec($this->scanEscapedUnicodeSequence());

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
     * @param string $byte Initial byte. The bytestream cursor starts one
     *                     position ahead of this.
     * @return string The scanned codepoint.
     * @throws ParseException on ill-formed UTF-8.
     */
    private function scanCodepoint(string $byte) : string
    {
        $bytes = $this->byteIterator;
        $codepoint = $byte;
        $ord = ord($codepoint);

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

        while ($bytes->valid() && $expect > 0) {
            $byte = $bytes->current();

            if ((ord($byte) >> 6) ^ 0b10) {
                break;
            }

            $codepoint .= $byte;
            $bytes->next();
            $expect--;
        }

        $chr = \IntlChar::chr($codepoint);

        if ($chr === null) {
            throw new ParseException(
                sprintf(
                    "Line %d: Ill-formed UTF-8 sequence" . str_repeat(" 0x%X", strlen($codepoint)) . ".",
                    $this->getLineNumber(), ...array_map("ord", str_split($codepoint))
                )
            );
        }

        return $chr;
    }

    private function getExceptionMessage(string $byte = null) : string
    {
        if ($byte === null) {
            return sprintf(
                "Line %d: Unexpected end of file.",
                $this->getLineNumber()
            );
        }

        $codepoint = $this->scanCodepoint($byte);

        if (\IntlChar::isprint($codepoint)) {
            return sprintf(
                "Line %d: Unexpected '%s'.",
                $this->getLineNumber(), $codepoint
            );
        } else {
            return sprintf(
                "Line %d: Unexpected non-printable character \\u{%X}.",
                $this->getLineNumber(), \IntlChar::ord($codepoint)
            );
        }
    }

    private function initByteIterator()
    {
        $bytes = new \IteratorIterator($this->bytestream);
        $bytes->rewind();
        $this->byteIterator = $bytes;
    }

    private function scanDigits() : string
    {
        $bytes = $this->byteIterator;
        $digits = "";

        while (ctype_digit($bytes->current())) {
            $digits .= $bytes->current();
            $bytes->next();
        }

        return $digits;
    }

    /**
     * Scans the current escaped unicode sequence, sans leading \u.
     *
     * An escaped unicode sequence is a string of four hexadecimal characters.
     *
     * @return string The scanned sequence.
     * @throws ParseException
     */
    private function scanEscapedUnicodeSequence() : string
    {
        $bytes = $this->byteIterator;
        $sequence = "";

        for ($i = 0; $i < 4; $i++) {
            $byte = $bytes->current();
            $bytes->next();
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
