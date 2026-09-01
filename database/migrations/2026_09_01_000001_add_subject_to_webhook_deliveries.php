<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Names the object a delivery was about.
 *
 * One row per attempt already exists; what it lacked was a way to find every
 * attempt from the payment, offer or entry it concerned. `trigger_reference`
 * is whatever the trigger happened to carry and is not indexed. `subject_type`
 * plus `subject_id` are resolved once at snapshot time (by the addon's
 * `SubjectResolver` service) and indexed together, so a "webhook deliveries
 * for this object" panel is one lookup.
 *
 * Every step is guarded, so an interrupted run — or a second one — is a no-op
 * rather than a `duplicate column` error about a step that already succeeded.
 */
return new class extends Migration
{
    private const TABLE = 'webhook_deliveries';

    private const INDEX = 'webhook_deliveries_subject_index';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, 'subject_type')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('subject_type', 64)->nullable()->after('trigger_reference');
            });
        }

        if (! Schema::hasColumn(self::TABLE, 'subject_id')) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->string('subject_id', 64)->nullable()->after('subject_type');
            });
        }

        if (! $this->hasIndex(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['subject_type', 'subject_id'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex(self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }

        foreach (['subject_id', 'subject_type'] as $column) {
            if (Schema::hasColumn(self::TABLE, $column)) {
                Schema::table(self::TABLE, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Whether the table already carries this index. Under `migrate --pretend`
     * every read answers empty, which resolves to "not there yet" — the state
     * this file was written for, and the one worth compiling.
     */
    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes(self::TABLE))->contains(
            fn (array $index) => $index['name'] === $name
        );
    }
};
