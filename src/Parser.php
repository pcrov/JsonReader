<?php declare(strict_types = 1);

namespace JsonReader;

interface Parser extends \Traversable
{
    public function getValue();
}