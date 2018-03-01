<?php

namespace pcrov\JsonReader\InputStream;

use PHPUnit\Framework\TestCase;

class UriTest extends TestCase
{
    public function testUriInput()
    {
        $string = file_get_contents(__FILE__);
        $uriInput = new Uri(__FILE__);

        $buffer = "";
        while (($data = $uriInput->read()) !== null) {
            $buffer .= $data;
        }
        $this->assertSame($string, $buffer);
    }

    public function testUriInputIOFailure()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Failed to open URI: _does_not_exist");
        $uriInput = new Uri("_does_not_exist");
        iterator_to_array($uriInput);
    }
}
