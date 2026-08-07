<?php

namespace Yajra\Oci8\Tests\Functional\Compatibility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class BlobFileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('blob_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->binary('contents');
        });
    }

    protected function tearDown(): void
    {
        Schema::drop('blob_files');

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_and_get_a_file_stored_in_a_blob(): void
    {
        $contents = str_repeat("\x00\xFFLaravel OCI8 BLOB\x10", 3000);
        $file = UploadedFile::fake()->createWithContent('payload.bin', $contents);
        $stream = fopen($file->getRealPath(), 'rb');

        try {
            $blobFile = BlobFile::create([
                'filename' => $file->getClientOriginalName(),
                'contents' => $stream,
            ]);
        } finally {
            fclose($stream);
        }
        $storedBlobFile = BlobFile::findOrFail($blobFile->id);

        $this->assertSame('payload.bin', $storedBlobFile->filename);
        $storedContents = $storedBlobFile->contents;
        if (is_resource($storedContents)) {
            rewind($storedContents);
            $storedContents = stream_get_contents($storedContents);
        }
        $this->assertIsString($storedContents);
        $this->assertSame(strlen($contents), strlen($storedContents));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $storedContents));
    }

    #[Test]
    public function it_can_insert_and_get_a_stream_stored_in_a_blob(): void
    {
        $contents = str_repeat("\x00\xFFLaravel OCI8 BLOB\x10", 3000);
        $file = UploadedFile::fake()->createWithContent('inserted-payload.bin', $contents);
        $stream = fopen($file->getRealPath(), 'rb');

        try {
            $inserted = DB::table('blob_files')->insert([
                'filename' => $file->getClientOriginalName(),
                'contents' => $stream,
            ]);
        } finally {
            fclose($stream);
        }
        $storedBlobFile = DB::table('blob_files')
            ->where('filename', 'inserted-payload.bin')
            ->first();

        $this->assertTrue($inserted);
        $this->assertNotNull($storedBlobFile);
        $this->assertSame('inserted-payload.bin', $storedBlobFile->filename);
        $storedContents = $storedBlobFile->contents;
        if (is_resource($storedContents)) {
            rewind($storedContents);
            $storedContents = stream_get_contents($storedContents);
        }
        $this->assertIsString($storedContents);
        $this->assertSame(strlen($contents), strlen($storedContents));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $storedContents));
    }

    #[Test]
    public function it_can_insert_and_get_a_short_stream_stored_in_a_blob(): void
    {
        $contents = str_repeat("\x00\xFFLaravel OCI8 BLOB\x10", 100);
        $file = UploadedFile::fake()->createWithContent('short-payload.bin', $contents);
        $stream = fopen($file->getRealPath(), 'rb');

        $this->assertLessThanOrEqual(3999, strlen($contents));

        try {
            $inserted = DB::table('blob_files')->insert([
                'filename' => $file->getClientOriginalName(),
                'contents' => $stream,
            ]);
        } finally {
            fclose($stream);
        }
        $storedBlobFile = DB::table('blob_files')
            ->where('filename', 'short-payload.bin')
            ->first();

        $this->assertTrue($inserted);
        $this->assertNotNull($storedBlobFile);
        $this->assertSame('short-payload.bin', $storedBlobFile->filename);
        $storedContents = $storedBlobFile->contents;
        if (is_resource($storedContents)) {
            rewind($storedContents);
            $storedContents = stream_get_contents($storedContents);
        }
        $this->assertIsString($storedContents);
        $this->assertSame(strlen($contents), strlen($storedContents));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $storedContents));
    }
}

class BlobFile extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
