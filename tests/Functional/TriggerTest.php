<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class TriggerTest extends TestCase
{
    #[Test]
    public function it_can_generate_a_trigger()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();

        $connection->getSchemaBuilder()->dropIfExists('triggers');
        $connection->getSchemaBuilder()
            ->create('triggers', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email');
                $table->timestamps();
            });

        $trigger = $connection->getTrigger();
        $trigger->autoIncrement('triggers', 'id', 'triggers_id_trg_manual', 'triggers_id_seq');
    }

    #[Test]
    public function it_can_use_schema_with_db_prefix()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();
        try {
            $connection->statement('alter session set "_oracle_script"=true');
            $connection->statement('grant all privileges to issue905 identified by oracle container=ALL');
        } catch (\Exception) {
            $connection->statement('grant all privileges to issue905 identified by oracle');
        }

        $connection->setSchemaPrefix('issue905');
        $connection->setTablePrefix('my_');
        $connection->enableQueryLog();

        $schema = $connection->getSchemaBuilder();
        $schema->dropIfExists('bugs');

        $lastSql = array_slice($connection->getQueryLog(), -1)[0];
        $this->assertStringContainsString('drop table "ISSUE905"."MY_BUGS"', $lastSql['query']);

        $connection->getSchemaBuilder()
            ->create('bugs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email');
                $table->timestamps();
            });

        $lastSql = array_slice($connection->getQueryLog(), -1)[0];
        $this->assertStringContainsString('create trigger "ISSUE905"."MY_BUGS_ID_TRG"', $lastSql['query']);
    }
}
