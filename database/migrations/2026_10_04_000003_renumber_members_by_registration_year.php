<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $members = DB::table('members')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get(['id', 'created_at']);
            $sequences = [];
            $numbers = [];

            foreach ($members as $member) {
                $year = Carbon::parse($member->created_at)->timezone(config('app.timezone'))->format('y');
                $sequence = $sequences[$year] = ($sequences[$year] ?? 0) + 1;
                if ($sequence > 99999) {
                    throw new OverflowException('The yearly member number limit has been reached.');
                }
                $numbers[$member->id] = 'GMG'.$year.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            }

            // Temporary unique values avoid collisions while existing numbers are reassigned.
            $prefix = 'renumber-'.bin2hex(random_bytes(8)).'-';
            foreach ($numbers as $id => $number) {
                DB::table('members')->where('id', $id)->update(['member_no' => $prefix.$id]);
            }
            foreach ($numbers as $id => $number) {
                DB::table('members')->where('id', $id)->update(['member_no' => $number]);
            }
        });
    }

    public function down(): void
    {
        // Member numbers remain stable when rolling back unrelated schema changes.
    }
};
