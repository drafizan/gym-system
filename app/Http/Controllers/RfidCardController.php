<?php

namespace App\Http\Controllers;

use App\Enums\RfidCardStatus;
use App\Models\Member;
use App\Models\RfidCard;
use App\Support\RfidCardManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RfidCardController extends Controller
{
    public function index(Request $request): View
    {
        return $this->cardList($request, 'RFID Cards');
    }

    public function history(Request $request): View
    {
        return $this->cardList($request, 'Card History');
    }

    public function memberHistory(Request $request, Member $member): View
    {
        return $this->cardList($request, 'RFID Card History', $member);
    }

    public function create(Member $member): View
    {
        return view('rfid-cards.create', [
            'member' => $member->load('activeRfidCard'),
        ]);
    }

    public function store(Request $request, Member $member, RfidCardManager $cards): RedirectResponse
    {
        $validated = $request->validate([
            'card_number' => ['required', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $cards->assign($request, $member, $validated['card_number'], $validated['remarks'] ?? null);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'RFID card assigned successfully.');
    }

    public function replace(RfidCard $rfidCard): View
    {
        return view('rfid-cards.replace', [
            'card' => $rfidCard->load('member'),
        ]);
    }

    public function storeReplacement(Request $request, RfidCard $rfidCard, RfidCardManager $cards): RedirectResponse
    {
        $validated = $request->validate([
            'card_number' => ['required', 'string', 'max:120'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $replacement = $cards->replace($request, $rfidCard, $validated['card_number'], $validated['remarks'] ?? null);

        return redirect()
            ->route('members.show', $replacement->member_id)
            ->with('success', 'RFID card number updated successfully.');
    }

    public function deactivate(Request $request, RfidCard $rfidCard, RfidCardManager $cards): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $cards->deactivate($request, $rfidCard, $validated['remarks'] ?? null);

        return back()->with('success', 'RFID card deactivated successfully.');
    }

    public function block(Request $request, RfidCard $rfidCard, RfidCardManager $cards): RedirectResponse
    {
        $validated = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $cards->block($request, $rfidCard, $validated['remarks'] ?? null);

        return back()->with('success', 'RFID card blocked successfully.');
    }

    private function cardList(Request $request, string $pageTitle, ?Member $member = null): View
    {
        $search = trim((string) $request->query('search'));
        $status = (string) $request->query('status', '');

        $cards = RfidCard::query()
            ->with(['member', 'assignedBy', 'replacement'])
            ->when($member, fn ($query) => $query->where('member_id', $member->id))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('card_number', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($query) use ($search): void {
                            $query->where('member_no', 'like', "%{$search}%")
                                ->orWhere('full_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('rfid-cards.index', [
            'cards' => $cards,
            'member' => $member,
            'pageTitle' => $pageTitle,
            'search' => $search,
            'status' => $status,
            'statuses' => RfidCardStatus::cases(),
        ]);
    }
}
