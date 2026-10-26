<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, non-unique indexes for hot read paths. No column types or data change.
 * Re-running is safe: an index that already exists is left alone, and rollback only
 * drops the indexes named here.
 *
 * - comments(event_id, created_at): a page of an event's newest comments without a filesort.
 * - users(country): the user count on every country page without a full table scan.
 */
return new class extends Migration
{
    private const INDEXES = [
        'comments' => ['scemory_comments_event_created_idx' => ['event_id', 'created_at']],
        'users' => ['scemory_users_country_idx' => ['country']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasTable($table) || ! Schema::hasColumns($table, $columns) || Schema::hasIndex($table, $name)) {
                    continue;
                }
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
                    continue;
                }
                $this->restoreForeignKeyIndex($table, $name, $columns[0]);
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
            }
        }
    }

    /**
     * MySQL drops the index it created for a foreign key once a new index can serve
     * the key, so the new index cannot be dropped alone. Recreate a plain index on
     * the key column first, under the foreign key's name as MySQL originally had it.
     */
    private function restoreForeignKeyIndex(string $table, string $name, string $column): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))->first(fn ($key) => ($key['columns'][0] ?? null) === $column);
        $covered = collect(Schema::getIndexes($table))
            ->contains(fn ($index) => $index['name'] !== $name && ($index['columns'][0] ?? null) === $column);
        if ($foreignKey && ! $covered) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index([$column], $foreignKey['name']));
        }
    }
};
