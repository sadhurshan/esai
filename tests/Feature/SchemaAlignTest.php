<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchemaAlignTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_align_reports_no_missing_sections(): void
    {
        Artisan::call('schema:align', ['--format' => 'json']);

        $output = Artisan::output();
        $this->assertNotEmpty($output, 'Schema align command produced no output');

        $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($payload);

        foreach ($payload as $row) {
            $this->assertEquals('ok', $row['status'], sprintf('Table %s reported status %s', $row['table'], $row['status']));
            $this->assertSame([], $row['missing_columns'], sprintf('Table %s missing columns: %s', $row['table'], implode(', ', $row['missing_columns'])));
            $this->assertSame([], $row['missing_indexes'], sprintf('Table %s missing indexes: %s', $row['table'], implode(', ', $row['missing_indexes'])));
        }
    }
}
