<?php

namespace pcrov\JsonReader\InputStream;

use pcrov\JsonReader\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StreamTest extends TestCase
{
    public function testStreamInput()
    {
        $string = file_get_contents(__FILE__);
        $handle = fopen(__FILE__, "rb");
        $stream = new Stream($handle);

        $buffer = "";
        while (($data = $stream->read()) !== null) {
            $buffer .= $data;
        }
        $this->assertSame($string, $buffer);
        fclose($handle);
    }

    public function testNotResourceFailure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A valid stream resource must be provided.");

        new Stream("fail");
    }

    public function testNotStreamFailure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A valid stream resource must be provided.");

        try {
            $handle = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            new Stream($handle);
        } finally {
            socket_close($handle);
        }
    }

    public function testDirStreamFailure()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("A valid stream resource must be provided.");

        try {
            $handle = opendir(__DIR__);
            new Stream($handle);
        } finally {
            closedir($handle);
        }
    }

    public function testUnreadableStreamFailure()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Stream must be readable. Given stream opened in mode: a");

        try {
            $handle = fopen(__FILE__, "a");
            new Stream($handle);
        } finally {
            fclose($handle);
        }
    }
}
