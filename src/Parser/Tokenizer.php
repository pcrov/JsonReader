<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

interface Tokenizer
{
    public function read(): Token;
}
