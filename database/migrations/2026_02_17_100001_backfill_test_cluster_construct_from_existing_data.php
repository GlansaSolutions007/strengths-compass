<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Populate test_cluster_construct from existing data so existing tests keep working:
     * For each test, for each cluster attached to the test, for each construct that has
     * that cluster_id, insert (test_id, cluster_id, construct_id).
     */
    public function up(): void
    {
        $pivot = DB::table('test_cluster')
            ->join('constructs', 'constructs.cluster_id', '=', 'test_cluster.cluster_id')
            ->select('test_cluster.test_id', 'test_cluster.cluster_id', 'constructs.id as construct_id')
            ->whereNotNull('constructs.cluster_id')
            ->distinct()
            ->get();

        $now = now();
        $rows = $pivot->map(function ($row) use ($now) {
            return [
                'test_id' => $row->test_id,
                'cluster_id' => $row->cluster_id,
                'construct_id' => $row->construct_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->unique(function ($row) {
            return $row['test_id'] . '-' . $row['construct_id'];
        })->values()->all();

        if (!empty($rows)) {
            DB::table('test_cluster_construct')->insert($rows);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('test_cluster_construct')->truncate();
    }
};
