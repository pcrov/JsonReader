<?php declare(strict_types = 1);

namespace JsonReader\Parser;

abstract class Token
{
    const STRING = 1;
    const NUMBER = 2;
    const TRUE = 3;
    const FALSE = 4;
    const NULL = 5;
    const COLON = 6;
    const COMMA = 7;
    const BEGIN_ARRAY = 8;
    const END_ARRAY = 9;
    const BEGIN_OBJECT = 10;
    const END_OBJECT = 11;

    /**
     * @ignore This should not be used in production code.
     *
     * Convenience method. Handy while debugging the lexer.
     *
     * @param int $token
     * @return string token name
     */
    public static function getTokenName(int $token) : string
    {
        return array_flip((new \ReflectionClass(__CLASS__))->getConstants())[$token];
    }
}