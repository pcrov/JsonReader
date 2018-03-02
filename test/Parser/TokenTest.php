<?php

namespace pcrov\JsonReader\Parser;

use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testCreateToken()
    {
        $token = new Token(Token::T_TRUE, true, 42);
        self::assertSame("T_TRUE", $token->getType());
        self::assertTrue($token->getValue());
        self::assertSame(42, $token->getLine());
    }
}
