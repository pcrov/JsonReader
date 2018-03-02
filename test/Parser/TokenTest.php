<?php

namespace pcrov\JsonReader\Parser;

use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    public function testCreateToken()
    {
        $token = new Token(Token::T_TRUE, 42, true);
        self::assertSame(Token::T_TRUE, $token->getType());
        self::assertTrue($token->getValue());
        self::assertSame(42, $token->getLine());
    }
}
