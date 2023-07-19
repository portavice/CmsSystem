<?php

namespace Portavice\CmsSystem\Tests;

use Portavice\CmsSystem\CmsSystem;
use Portavice\CmsSystem\CmsSystemServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            CmsSystemServiceProvider::class,
        ];
    }

    public function testSplitPattern()
    {
        $input = '{{ method arg1 arg2 }} testcontent {{ endmethod }}';
        $matches = (new CmsSystem())->splitPattern($input);
        foreach ($matches as $match) {
            $this->assertEquals('method', trim($match['method']));
            $this->assertEquals('arg1 arg2', trim($match['args']));
            $this->assertEquals('testcontent', trim($match['content']));
        }
    }
}
