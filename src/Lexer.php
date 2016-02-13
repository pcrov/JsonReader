<?php declare(strict_types = 1);

namespace JsonReader;

/**
 * Class Lexer
 *
 * Does most scanning and evaluation in the same pass.
 *
 * @package JsonReader
 */
class Lexer implements \IteratorAggregate
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
                    yield Token::COLON => null;
                    $iterator->next();
                    break;
                case ",":
                    yield Token::COMMA => null;
                    $iterator->next();
                    break;
                case "[":
                    yield Token::BEGIN_ARRAY => null;
                    $iterator->next();
                    break;
                case "]":
                    yield Token::END_ARRAY => null;
                    $iterator->next();
                    break;
                case "{":
                    yield Token::BEGIN_OBJECT => null;
                    $iterator->next();
                    break;
                case "}":
                    yield Token::END_OBJECT => null;
                    $iterator->next();
                    break;
                case "t":
                    $this->consumeString("true");
                    yield Token::TRUE => true;
                    break;
                case "f":
                    $this->consumeString("false");
                    yield Token::FALSE => false;
                    break;
                case "n":
                    $this->consumeString("null");
                    yield Token::NULL => null;
                    break;
                case '"':
                    yield Token::STRING => $this->evaluateDoubleQuotedString();
                    break;
                default:
                    if (ctype_digit($byte) || $byte === "-") {
                        yield Token::NUMBER => $this->evaluateNumber();
                    } else {
                        throw new ParseException($this->getExceptionMessage($byte));
                    }
            }
        }
    }

    /**
     * @return int Current line number.
     */
    public function getLineNumber() : int
    {
        return $this->line;
    }

    /**
     * Do late initialization as some bytestreams may want to wait to open resources until they're needed.
     */
    private function initByteIterator()
    {
        $bytestream = $this->bytestream;

        /** @var \Iterator $iterator */
        $iterator = ($bytestream instanceof \IteratorAggregate) ? $bytestream->getIterator() : $bytestream;
        $iterator->rewind();
        $this->byteIterator = $iterator;
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

    /**
     * Consumes the current \r as well as a single immediately
     * following \n in order to treat \r\n as a single newline.
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

    private function evaluateEscapeSequence() : string
    {
        $iterator = $this->byteIterator;
        $iterator->next(); //Skip initial \
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

        if ($byte === "." ) {
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

        throw new ParseException($this->getExceptionMessage("EOF"));
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

    private function getExceptionMessage(string $byte = null)
    {
        if ($byte === null) {
            return sprintf(
                "Line %d: Unexpected end of file.",
                $this->line
            );
        }

        //TODO: Grab any non-printable here, not just low control characters.
        if ($byte < "\x1f") {
            return sprintf(
                "Line %d: Unexpected control character 0x%x.",
                $this->line, ord($byte)
            );
        }

        /*
         * TODO: Fill multi-byte character.
         *
         * We've only got one byte here, which isn't so useful in
         * an exception message if it's a multi-byte character.
         *
         * UTF-8 makes it easy to tell if we need more and how much:
         *      0xxx xxxx   Single-byte character
         *      110x xxxx   One more byte to come
         *      1110 xxxx   Two more
         *      1111 0xxx   Three more
         *      10xx xxxx   A continuation of any of the three preceding
         */
        return sprintf(
            "Line %d: Unexpected '%s'.",
            $this->line, $byte
        );
    }
}