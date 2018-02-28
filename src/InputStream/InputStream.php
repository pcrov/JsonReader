<?php declare(strict_types=1);

namespace pcrov\JsonReader\InputStream;

interface InputStream
{
    /**
     * @return string|null A chunk of bytes or null when there's nothing left to read.
     */
    public function read();
}
