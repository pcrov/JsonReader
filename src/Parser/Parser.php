<?php declare(strict_types=1);

namespace pcrov\JsonReader\Parser;

interface Parser
{
    /**
     * @return array|null Tuples in the form of [$type, $name, $value, $depth], null when finished.
     */
    public function read();
}
