<?php declare(strict_types=1);

namespace pcrov\JsonReader\InputStream;

final class StringInput implements InputStream
{
    private $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function read()
    {
        $string = $this->string;
        if ($string === "") {
            return null;
        }
        $this->string = "";

        return $string;
    }
}
