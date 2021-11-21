<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

interface Tokenizer
{
    public const T_STRING = "T_STRING";
    public const T_NUMBER = "T_NUMBER";
    public const T_TRUE = "T_TRUE";
    public const T_FALSE = "T_FALSE";
    public const T_NULL = "T_NULL";
    public const T_COLON = "T_COLON";
    public const T_COMMA = "T_COMMA";
    public const T_BEGIN_ARRAY = "T_BEGIN_ARRAY";
    public const T_END_ARRAY = "T_END_ARRAY";
    public const T_BEGIN_OBJECT = "T_BEGIN_OBJECT";
    public const T_END_OBJECT = "T_END_OBJECT";
    public const T_EOF = "T_EOF";

    /**
     * @return array Tuples in the form of [string type, mixed value, int line]
     */
    public function read(): array;
}
