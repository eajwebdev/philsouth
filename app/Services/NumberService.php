<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Generates continuous, per-type running document numbers (DR / WS / TS) that
 * match the pre-printed booklet numbers. Uses a row lock so concurrent posts
 * never collide or skip.
 */
class NumberService
{
    /**
     * Reserve and return the next formatted number for a document type.
     */
    public function next(string $type): string
    {
        $config = config("inventory.numbering.{$type}");

        if (! $config) {
            throw new InvalidArgumentException("Unknown numbering type [{$type}].");
        }

        return DB::transaction(function () use ($type, $config) {
            $row = DB::table('number_sequences')->where('type', $type)->lockForUpdate()->first();

            if (! $row) {
                $current = $config['start'];
                DB::table('number_sequences')->insert([
                    'type' => $type,
                    'next_no' => $current + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $current = $row->next_no;
                DB::table('number_sequences')
                    ->where('type', $type)
                    ->update(['next_no' => $current + 1, 'updated_at' => now()]);
            }

            return $config['prefix'].' '.$current;
        });
    }
}
