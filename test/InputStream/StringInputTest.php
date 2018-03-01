<?php

namespace pcrov\JsonReader\InputStream;

use PHPUnit\Framework\TestCase;

class StringInputTest extends TestCase
{
    public function testStringInput()
    {
        $string = file_get_contents(__FILE__);
        $stringInput = new StringInput($string);
        $this->assertSame($string, $stringInput->read());
    }
}
