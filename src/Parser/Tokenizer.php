<?php declare(strict_types = 1);

namespace pcrov\JsonReader\Parser;

interface Tokenizer extends \Traversable
{
    const T_STRING = "T_STRING";
    const T_NUMBER = "T_NUMBER";
    const T_TRUE = "T_TRUE";
    const T_FALSE = "T_FALSE";
    const T_NULL = "T_NULL";
    const T_COLON = "T_COLON";
    const T_COMMA = "T_COMMA";
    const T_BEGIN_ARRAY = "T_BEGIN_ARRAY";
    const T_END_ARRAY = "T_END_ARRAY";
    const T_BEGIN_OBJECT = "T_BEGIN_OBJECT";
    const T_END_OBJECT = "T_END_OBJECT";

    /**
     * @return int Current line number.
     */
    public function getLineNumber() : int;
}
