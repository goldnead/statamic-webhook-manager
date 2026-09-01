<?php

namespace Goldnead\WebhookManager\Tests\Migrations;

use Goldnead\WebhookManager\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

/**
 * The subject migration, run a second time.
 *
 * A migration that adds columns unconditionally dies on its second run with
 * `duplicate column`, and its second run is exactly what an operator gets
 * after an aborted first one: nothing was rolled back, the migration is not
 * recorded, and `php artisan migrate` picks it up again. Every step of the
 * subject migration is therefore guarded. This runs `up()` over a schema that
 * already went through it and requires the result to be the same schema —
 * both columns there, the index there once.
 */
class SubjectMigrationIsIdempotentTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = __DIR__.'/../../database/migrations/2026_09_01_000001_add_subject_to_webhook_deliveries.php';

    public function test_running_up_again_changes_nothing(): void
    {
        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_type'));
        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_id'));
        $this->assertSame(1, $this->subjectIndexCount(), 'the first run did not leave exactly one subject index');

        (require self::MIGRATION)->up();

        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_type'));
        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_id'));
        $this->assertSame(1, $this->subjectIndexCount(), 'a second run duplicated or dropped the subject index');
    }

    public function test_down_then_up_restores_the_schema(): void
    {
        $migration = require self::MIGRATION;

        $migration->down();

        $this->assertFalse(Schema::hasColumn('webhook_deliveries', 'subject_type'));
        $this->assertFalse(Schema::hasColumn('webhook_deliveries', 'subject_id'));
        $this->assertSame(0, $this->subjectIndexCount());

        // A second down() over the already-reverted schema is a no-op as well.
        $migration->down();

        $migration->up();

        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_type'));
        $this->assertTrue(Schema::hasColumn('webhook_deliveries', 'subject_id'));
        $this->assertSame(1, $this->subjectIndexCount());
    }

    private function subjectIndexCount(): int
    {
        return collect(Schema::getIndexes('webhook_deliveries'))
            ->filter(fn (array $index) => $index['name'] === 'webhook_deliveries_subject_index')
            ->count();
    }
}
