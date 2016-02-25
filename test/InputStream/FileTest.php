<?php

namespace pcrov\JsonReader\InputStream;

class FileTest extends \PHPUnit_Framework_TestCase
{
    public function testFileInput()
    {
        $string = file_get_contents(__FILE__);
        $fileInput = new File(__FILE__);
        $this->assertSame(str_split($string), iterator_to_array($fileInput));
    }

    public function testFileInputIOFailure()
    {
        $this->expectException(IOException::class);
        $fileInput = new File("_does_not_exist");
        iterator_to_array($fileInput);
    }
}
