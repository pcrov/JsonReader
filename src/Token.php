<?php declare(strict_types = 1);

namespace JsonReader;

class Token
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
}