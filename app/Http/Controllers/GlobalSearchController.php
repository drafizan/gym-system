<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $like = '%'.mb_strtolower($query).'%';

        $members = collect();
        $sales = collect();

        if ($query !== '') {
            $members = Member::query()
                ->where(function (Builder $builder) use ($like): void {
                    $builder->whereRaw('LOWER(member_no) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(full_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(phone) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(rfid_card_number) LIKE ?', [$like])
                        ->orWhereHas('rfidCards', fn (Builder $rfidQuery) => $rfidQuery->whereRaw('LOWER(card_number) LIKE ?', [$like]));
                })
                ->latest()
                ->limit(8)
                ->get();

            $sales = Sale::query()
                ->with(['member', 'payments'])
                ->where(function (Builder $builder) use ($like): void {
                    $builder->whereRaw('LOWER(receipt_no) LIKE ?', [$like])
                        ->orWhereHas('member', function (Builder $memberQuery) use ($like): void {
                            $memberQuery->whereRaw('LOWER(member_no) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(full_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(phone) LIKE ?', [$like]);
                        });
                })
                ->latest('completed_at')
                ->limit(8)
                ->get();
        }

        return view('search.index', [
            'query' => $query,
            'members' => $members,
            'sales' => $sales,
        ]);
    }
}
