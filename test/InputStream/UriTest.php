<?php

namespace pcrov\JsonReader\InputStream;

class UriTest extends \PHPUnit_Framework_TestCase
{
    public function testUriInput()
    {
        $string = file_get_contents(__FILE__);
        $uriInput = new Uri(__FILE__);
        $this->assertSame(str_split($string), iterator_to_array($uriInput));
    }

    public function testUriInputIOFailure()
    {
        $this->expectException(IOException::class);
        $this->expectExceptionMessage("Failed to open URI: _does_not_exist");
        $uriInput = new Uri("_does_not_exist");
        iterator_to_array($uriInput);
    }
}
