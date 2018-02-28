<?php

namespace pcrov\JsonReader\InputStream;

class StringInputTest extends \PHPUnit_Framework_TestCase
{
    public function testStringInput()
    {
        $string = file_get_contents(__FILE__);
        $stringInput = new StringInput($string);
        $this->assertSame($string, $stringInput->read());
    }
}
