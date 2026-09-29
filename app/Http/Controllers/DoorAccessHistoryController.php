<?php

namespace App\Http\Controllers;

use App\Models\AccessControllerSetting;
use App\Services\DahuaStandaloneAccessControllerClient;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoorAccessHistoryController extends Controller
{
    public function __invoke(Request $request, DahuaStandaloneAccessControllerClient $client): View
    {
        $validated = $request->validate([
            'door_id' => ['nullable', 'integer', 'exists:access_controller_settings,id'],
            'card_number' => ['nullable', 'string', 'max:120'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $filters = [
            'card_number' => trim((string) ($validated['card_number'] ?? '')),
            'date_from' => $validated['date_from'] ?? now()->toDateString(),
            'date_to' => $validated['date_to'] ?? now()->toDateString(),
            'limit' => (int) ($validated['limit'] ?? 50),
        ];

        $doors = AccessControllerSetting::query()
            ->where('driver', 'dahua_standalone')
            ->where('is_enabled', true)
            ->when(! empty($validated['door_id']), fn ($query) => $query->whereKey($validated['door_id']))
            ->orderBy('id')
            ->get();

        $events = collect();
        $historyErrors = collect();

        foreach ($doors as $door) {
            $result = $client->accessHistory($door, $filters);
            $body = $result->response['body'] ?? [];

            if (! $result->successful) {
                $historyErrors->push($door->displayName().': '.$result->message);

                continue;
            }

            collect($body['records'] ?? [])
                ->each(function (array $event) use ($events, $door): void {
                    $events->push([
                        ...$event,
                        'door_id' => $door->id,
                        'door_name' => $event['door_name'] ?: $door->displayName(),
                    ]);
                });
        }

        return view('access.history', [
            'doors' => AccessControllerSetting::query()
                ->where('driver', 'dahua_standalone')
                ->where('is_enabled', true)
                ->orderBy('id')
                ->get(),
            'events' => $events
                ->sortByDesc(fn (array $event): string => (string) ($event['time'] ?? ''))
                ->values(),
            'historyErrors' => $historyErrors,
            'filters' => $filters + ['door_id' => $validated['door_id'] ?? null],
        ]);
    }
}
