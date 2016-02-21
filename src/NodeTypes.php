<?php declare(strict_types = 1);

namespace pcrov\JsonReader;

/**
 * {@internal These would live in the parser (or a parser interface, rather) if not for
 * the desire to expose them as part of the simplified reader api.}
 *
 * Interface NodeTypes
 * @package pcrov\JsonReader
 */
interface NodeTypes
{
    const NONE = 0;
    const STRING = 1;
    const NUMBER = 2;
    const BOOL = 3;
    const NULL = 4;
    const ARRAY = 5;
    const END_ARRAY = 6;
    const OBJECT = 7;
    const END_OBJECT = 8;

    const NAMES = [
        self::NONE => "NONE",
        self::STRING => "STRING",
        self::NUMBER => "NUMBER",
        self::BOOL => "BOOL",
        self::NULL => "NULL",
        self::ARRAY => "ARRAY",
        self::END_ARRAY => "END_ARRAY",
        self::OBJECT => "OBJECT",
        self::END_OBJECT => "END_OBJECT"
    ];
}
