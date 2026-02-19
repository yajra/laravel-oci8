<?php

namespace Functional;

use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Schema\OracleBlueprint as Blueprint;
use Yajra\Oci8\Tests\TestCase;

class OracleSchemaStateTest extends TestCase
{
    #[Test]
    public function it_can_dump_schema_state()
    {
        if (getenv('ORACLE_TOOLS') !== 'true') {
            $this->markTestSkipped('This is only supported when Oracle tools are available!');
        }
        if (Schema::hasTable('test_dump')) {
            Schema::drop('test_dump');
        }

        Schema::create('test_dump', function (Blueprint $blueprint) {
            $blueprint->increments('id');
        });

        $this->getConnection()->getSchemaState()->dump($this->getConnection(), '/tmp/schema.dmp');
        $this->assertFileExists('/tmp/schema.dmp');
    }

    #[Test]
    public function it_can_load_schema_state()
    {
        if (getenv('ORACLE_TOOLS') !== 'true') {
            $this->markTestSkipped('This is only supported when Oracle tools are available!');
        }

        $this->getConnection()->getSchemaState()->load('/tmp/schema.dmp');
        $this->assertTrue(Schema::hasTable('test_dump'));
    }
}
