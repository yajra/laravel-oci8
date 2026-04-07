<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class SchemaTest extends TestCase
{
    protected function tearDown(): void
    {
        if (Schema::hasTable('rename_index_table')) {
            Schema::drop('rename_index_table');
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_rename_index()
    {
        Schema::create('rename_index_table', function (Blueprint $table) {
            $table->integer('id');
            $table->string('name')->index();
        });

        Schema::table('rename_index_table', function (Blueprint $table) {
            $table->renameIndex('rename_index_table_name_index', 'rename_index_table_name_idx');
        });

        $indexes = array_column(Schema::getIndexes('rename_index_table'), 'name');

        $this->assertContains('rename_index_table_name_idx', $indexes);
        $this->assertNotContains('rename_index_table_name_index', $indexes);
    }
}
