<?php

namespace pcrov\JsonReader\InputStream;

class StreamTest extends \PHPUnit_Framework_TestCase
{
    public function testStreamInput()
    {
        $string = file_get_contents(__FILE__);
        $handle = fopen(__FILE__, "rb");
        $streamInput = new Stream($handle);
        $this->assertSame(str_split($string), iterator_to_array($streamInput));
        fclose($handle);
    }

    public function testNotResourceFailure()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("A valid stream resource must be provided.");

        new Stream("fail");
    }

    public function testNotStreamFailure()
    {
        $this->expectException(\InvalidArgumentException::class);
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
        $this->expectException(\InvalidArgumentException::class);
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
