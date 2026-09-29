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
        if (! $this->isPgsql() && ! $this->isMariaDb()) {
            $this->assertIsResource($storedContents);
            $this->assertSame('stream', get_resource_type($storedContents));
        }

        if (is_resource($storedContents)) {
            $stream = $storedContents;
            try {
                $this->assertTrue(rewind($stream));
                $storedContents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
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
        if (! $this->isPgsql() && ! $this->isMariaDb()) {
            $this->assertIsResource($storedContents);
            $this->assertSame('stream', get_resource_type($storedContents));
        }

        if (is_resource($storedContents)) {
            $stream = $storedContents;
            try {
                $this->assertTrue(rewind($stream));
                $storedContents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
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
        if (! $this->isPgsql() && ! $this->isMariaDb()) {
            $this->assertIsResource($storedContents);
            $this->assertSame('stream', get_resource_type($storedContents));
        }

        if (is_resource($storedContents)) {
            $stream = $storedContents;
            try {
                $this->assertTrue(rewind($stream));
                $storedContents = stream_get_contents($stream);
            } finally {
                fclose($stream);
            }
        }

        $this->assertIsString($storedContents);
        $this->assertSame(strlen($contents), strlen($storedContents));
        $this->assertSame(hash('sha256', $contents), hash('sha256', $storedContents));
    }

    #[Test]
    public function it_can_stream_a_512_mb_file_into_a_blob_with_bounded_memory(): void
    {
        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->markTestSkipped('The large BLOB stream test is Oracle-specific.');
        }

        $stream = tmpfile();
        $this->assertIsResource($stream);
        $this->assertTrue(ftruncate($stream, 512 * 1024 * 1024));
        rewind($stream);

        memory_reset_peak_usage();
        $memoryBefore = memory_get_usage(true);

        try {
            $inserted = DB::table('blob_files')->insert([
                'filename' => 'large-payload.bin',
                'contents' => $stream,
            ]);
            $memoryIncrease = memory_get_peak_usage(true) - $memoryBefore;
        } finally {
            fclose($stream);
        }

        $storedBlob = (array) DB::selectOne(
            'select filename, DBMS_LOB.GETLENGTH(contents) as content_length from blob_files where filename = ?',
            ['large-payload.bin']
        );

        $this->assertTrue($inserted);
        $this->assertSame('large-payload.bin', $storedBlob['filename']);
        $this->assertSame(512 * 1024 * 1024, (int) $storedBlob['content_length']);
        $this->assertLessThanOrEqual(64 * 1024 * 1024, $memoryIncrease);
    }

    #[Test]
    public function it_can_upload_and_download_a_1_gb_blob_as_a_stream_with_a_256_mb_memory_limit(): void
    {
        if ($this->isPgsql() || $this->isMariaDb()) {
            $this->markTestSkipped('The large BLOB stream test is Oracle-specific.');
        }

        $previousMemoryLimit = ini_get('memory_limit');
        $upload = $download = $blob = null;

        try {
            $this->assertNotFalse(ini_set('memory_limit', '256M'));
            $this->assertSame('256M', ini_get('memory_limit'));
            memory_reset_peak_usage();
            $memoryBefore = memory_get_usage(true);

            $upload = tmpfile();
            $download = tmpfile();
            $this->assertIsResource($upload);
            $this->assertIsResource($download);

            $fileSize = 1024 * 1024 * 1024;
            $chunk = random_bytes(1024 * 1024);
            $expectedHash = hash_init('sha256');

            for ($written = 0; $written < $fileSize; $written += strlen($chunk)) {
                $this->assertSame(strlen($chunk), fwrite($upload, $chunk));
                hash_update($expectedHash, $chunk);
            }
            unset($chunk);
            $this->assertTrue(rewind($upload));

            $this->assertTrue(DB::table('blob_files')->insert([
                'filename' => 'one-gb-payload.bin',
                'contents' => $upload,
            ]));

            $storedBlobFile = DB::table('blob_files')
                ->where('filename', 'one-gb-payload.bin')
                ->first();

            $this->assertNotNull($storedBlobFile);
            $blob = $storedBlobFile->contents;
            $this->assertIsResource($blob);
            $this->assertSame('stream', get_resource_type($blob));
            $this->assertSame(0, ftell($blob));
            $this->assertSame($fileSize, stream_copy_to_stream($blob, $download));
            $this->assertSame($fileSize, fstat($download)['size']);

            $this->assertTrue(rewind($download));
            $actualHash = hash_init('sha256');
            $this->assertSame($fileSize, hash_update_stream($actualHash, $download));
            $this->assertSame(hash_final($expectedHash), hash_final($actualHash));
            $this->assertLessThanOrEqual(
                64 * 1024 * 1024,
                memory_get_peak_usage(true) - $memoryBefore
            );
        } finally {
            foreach ([$blob, $download, $upload] as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            ini_set('memory_limit', $previousMemoryLimit);
        }
    }
}

class BlobFile extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
