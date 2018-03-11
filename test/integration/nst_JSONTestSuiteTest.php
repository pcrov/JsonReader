<?php

namespace pcrov\JsonReader;

use pcrov\JsonReader\Parser\ParseException;
use PHPUnit\Framework\TestCase;

// Basic accept/reject tests on data from https://github.com/nst/JSONTestSuite
class nst_JSONTestSuiteTest extends TestCase
{
    const DATA_DIR = __DIR__ . "/../../vendor/nst/JSONTestSuite/test_parsing/";

    /**
     * @doesNotPerformAssertions
     * @coversNothing
     * @dataProvider validProvider
     */
    public function testDoesNotThrowOnValidDocuments($file)
    {
        $reader = new JsonReader();
        $reader->open($file);
        while ($reader->read()) {
            //no-op
        }
        $reader->close();
    }

    /**
     * @coversNothing
     * @dataProvider invalidProvider
     */
    public function testThrowsOnInvalidDocuments($file)
    {
        $this->expectException(ParseException::class);
        $reader = new JsonReader();
        $reader->open($file);
        while ($reader->read()) {
            //no-op
        }
        $reader->close();
    }

    public function validProvider()
    {
        foreach (glob(self::DATA_DIR . "y_*") as $file) {
            $valid[\basename($file)] = [$file];
        }
        return $valid;
    }

    public function invalidProvider()
    {
        foreach (glob(self::DATA_DIR . "n_*") as $file) {
            $valid[\basename($file)] = [$file];
        }
        return $valid;
    }
}
