<?php declare(strict_types = 1);

namespace JsonReader\Parser;

interface Tokenizer
{
    const T_STRING = 1;
    const T_NUMBER = 2;
    const T_TRUE = 3;
    const T_FALSE = 4;
    const T_NULL = 5;
    const T_COLON = 6;
    const T_COMMA = 7;
    const T_BEGIN_ARRAY = 8;
    const T_END_ARRAY = 9;
    const T_BEGIN_OBJECT = 10;
    const T_END_OBJECT = 11;

    const NAMES = [
        self::T_STRING => "T_STRING",
        self::T_NUMBER => "T_NUMBER",
        self::T_TRUE => "T_TRUE",
        self::T_FALSE => "T_FALSE",
        self::T_NULL => "T_NULL",
        self::T_COLON => "T_COLON",
        self::T_COMMA => "T_COMMA",
        self::T_BEGIN_ARRAY => "T_BEGIN_ARRAY",
        self::T_END_ARRAY => "T_END_ARRAY",
        self::T_BEGIN_OBJECT => "T_BEGIN_OBJECT",
        self::T_END_OBJECT => "T_END_OBJECT"
    ];

    /**
     * @return int Current line number.
     */
    public function getLineNumber() : int;
}