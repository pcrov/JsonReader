<?php

namespace pcrov\JsonReader\InputStream;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Psr7StreamTest extends TestCase
{
    public function testReadReturnsString()
    {
        $psr7 = $this->createMock(StreamInterface::class);
        $psr7->method("isReadable")->willReturn(true);
        $psr7->method("eof")->willReturn(false);
        $psr7->method("read")->willReturn("foo");

        $this->assertSame("foo", (new Psr7Stream($psr7))->read());
    }

    public function testReadEofReturnsNull()
    {
        $psr7 = $this->createMock(StreamInterface::class);
        $psr7->method("isReadable")->willReturn(true);
        $psr7->method("eof")->willReturn(true);

        $this->assertNull((new Psr7Stream($psr7))->read());
    }

    public function testRuntimeExceptionReturnsNull()
    {
        $psr7 = $this->createMock(StreamInterface::class);
        $psr7->method("isReadable")->willReturn(true);
        $psr7->method("eof")->willReturn(false);
        $psr7->method("read")->willThrowException(new \RuntimeException());

        $this->assertNull((new Psr7Stream($psr7))->read());
    }

    public function testUnreadableStreamThrows()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Stream must be readable.");

        $psr7 = $this->createMock(StreamInterface::class);
        $psr7->method("isReadable")->willReturn(false);
        new Psr7Stream($psr7);
    }
}
