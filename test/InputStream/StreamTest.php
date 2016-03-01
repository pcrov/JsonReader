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

    public function testStreamInputIOFailure()
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
