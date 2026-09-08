<?php

use App\Models\CanonicalEvent;
use App\Models\Event;
use App\Models\EventNameMapping;
use App\Models\Organization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Organization $organization;

    public ?string $mappingTarget = null;

    public string $selectedCanonicalEventId = '';

    public string $newCanonicalEventName = '';

    /**
     * Resolve the organization and confirm the current user may view its events.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('viewAny', [CanonicalEvent::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * Open the inline mapping form for a raw event name, pre-filling its
     * current mapping if it has one.
     */
    public function startMapping(string $eventName): void
    {
        $this->mappingTarget = $eventName;

        $existing = $this->organization->eventNameMappings()->where('event_name', $eventName)->first();
        $this->selectedCanonicalEventId = $existing ? (string) $existing->canonical_event_id : '';
        $this->newCanonicalEventName = '';
    }

    /**
     * Dismiss the inline mapping form without saving.
     */
    public function cancelMapping(): void
    {
        $this->reset('mappingTarget', 'selectedCanonicalEventId', 'newCanonicalEventName');
    }

    /**
     * Map (or remap) the target raw event name to a canonical event,
     * creating the canonical event first if a new name was given instead of
     * an existing one being selected.
     */
    public function saveMapping(): void
    {
        Gate::authorize('create', [EventNameMapping::class, $this->organization]);

        $rules = ['newCanonicalEventName' => ['nullable', 'string', 'max:255']];

        if ($this->selectedCanonicalEventId !== '') {
            $rules['selectedCanonicalEventId'] = [
                'string',
                Rule::exists('canonical_events', 'id')->where('organization_id', $this->organization->id),
            ];
        }

        $this->validate($rules);

        if ($this->selectedCanonicalEventId === '' && trim($this->newCanonicalEventName) === '') {
            $this->addError('newCanonicalEventName', __('Choose an existing canonical event or name a new one.'));

            return;
        }

        $canonicalEvent = $this->selectedCanonicalEventId !== ''
            ? $this->organization->canonicalEvents()->findOrFail($this->selectedCanonicalEventId)
            : $this->organization->canonicalEvents()->firstOrCreate(['name' => trim($this->newCanonicalEventName)]);

        $this->organization->eventNameMappings()->updateOrCreate(
            ['event_name' => $this->mappingTarget],
            ['canonical_event_id' => $canonicalEvent->id],
        );

        $this->cancelMapping();
    }

    /**
     * Remove the mapping for a raw event name, reverting it to unmapped.
     * The canonical event itself (and any other names mapped to it) is untouched.
     */
    public function unmap(string $eventName): void
    {
        $mapping = $this->organization->eventNameMappings()->where('event_name', $eventName)->first();

        if ($mapping === null) {
            return;
        }

        Gate::authorize('delete', $mapping);

        $mapping->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $rawEvents = Event::query()
            ->forOrganization($this->organization)
            ->selectRaw('event_name, COUNT(*) as occurrences, MAX(created_at) as last_seen_at')
            ->groupBy('event_name')
            ->orderByDesc('last_seen_at')
            ->get()
            ->each(fn (Event $event) => $event->last_seen_at = \Illuminate\Support\Carbon::parse($event->last_seen_at));

        $mappings = $this->organization->eventNameMappings()->get()->keyBy('event_name');

        $canonicalEvents = $this->organization->canonicalEvents()->orderBy('name')->get();

        $groupedCanonicalEvents = $canonicalEvents->map(function (CanonicalEvent $canonicalEvent) use ($mappings, $rawEvents) {
            $names = $mappings->where('canonical_event_id', $canonicalEvent->id)->keys();

            return [
                'canonicalEvent' => $canonicalEvent,
                'eventNames' => $names,
                'occurrences' => $rawEvents->whereIn('event_name', $names)->sum('occurrences'),
            ];
        })->filter(fn (array $group) => $group['eventNames']->isNotEmpty())->values();

        return [
            'rawEvents' => $rawEvents,
            'mappings' => $mappings,
            'canonicalEvents' => $canonicalEvents,
            'groupedCanonicalEvents' => $groupedCanonicalEvents,
        ];
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Events') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('The raw event names recorded for :organization, and the canonical events they map to.', ['organization' => $organization->name]) }}
        </p>
    </div>

    @if ($groupedCanonicalEvents->isNotEmpty())
        <div class="max-w-2xl mt-10">
            <h3 class="text-lg font-medium text-gray-900">
                {{ __('Canonical Events') }}
            </h3>

            <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
                @foreach ($groupedCanonicalEvents as $group)
                    <div class="py-4">
                        <div class="text-sm font-medium text-gray-900">{{ $group['canonicalEvent']->name }}</div>
                        <div class="text-sm text-gray-500">
                            {{ trans_choice(':count occurrence|:count occurrences', $group['occurrences'], ['count' => $group['occurrences']]) }}
                            &middot;
                            {{ $group['eventNames']->implode(', ') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="max-w-2xl mt-10">
        <h3 class="text-lg font-medium text-gray-900">
            {{ __('Raw Event Names') }}
        </h3>

        <div class="mt-6 divide-y divide-gray-200 border-t border-gray-200">
            @forelse ($rawEvents as $rawEvent)
                @php $mapping = $mappings->get($rawEvent->event_name); @endphp
                <div class="py-4">
                    @if ($mappingTarget === $rawEvent->event_name)
                        <form wire:submit="saveMapping" wire:key="mapping-{{ $rawEvent->event_name }}" class="flex flex-wrap items-end gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $rawEvent->event_name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ trans_choice(':count occurrence|:count occurrences', $rawEvent->occurrences, ['count' => $rawEvent->occurrences]) }}
                                </div>
                            </div>

                            <div>
                                <x-input-label for="selectedCanonicalEventId" :value="__('Map to existing')" />
                                <select wire:model="selectedCanonicalEventId" id="selectedCanonicalEventId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">{{ __('— none —') }}</option>
                                    @foreach ($canonicalEvents as $canonicalEvent)
                                        <option value="{{ $canonicalEvent->id }}">{{ $canonicalEvent->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('selectedCanonicalEventId')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="newCanonicalEventName" :value="__('Or create new')" />
                                <x-text-input wire:model="newCanonicalEventName" id="newCanonicalEventName" class="block mt-1 w-full" :placeholder="__('e.g. Page View')" />
                                <x-input-error :messages="$errors->get('newCanonicalEventName')" class="mt-2" />
                            </div>

                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <x-secondary-button type="button" wire:click="cancelMapping">{{ __('Cancel') }}</x-secondary-button>
                        </form>
                    @else
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900">{{ $rawEvent->event_name }}</div>
                                <div class="text-sm text-gray-500">
                                    {{ trans_choice(':count occurrence|:count occurrences', $rawEvent->occurrences, ['count' => $rawEvent->occurrences]) }}
                                    &middot;
                                    {{ __('Last seen :date', ['date' => $rawEvent->last_seen_at->format('M j, Y')]) }}
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                @if ($mapping)
                                    <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                        {{ $mapping->canonicalEvent->name }}
                                    </span>
                                @else
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ __('Unmapped') }}
                                    </span>
                                @endif

                                @can('create', [\App\Models\EventNameMapping::class, $organization])
                                    <button type="button" wire:click="startMapping('{{ $rawEvent->event_name }}')" class="text-sm font-medium text-blue-700 hover:text-blue-800">
                                        {{ $mapping ? __('Change') : __('Map') }}
                                    </button>

                                    @if ($mapping)
                                        <button type="button" wire:click="unmap('{{ $rawEvent->event_name }}')" class="text-sm font-medium text-rose-700 hover:text-rose-800">
                                            {{ __('Unmap') }}
                                        </button>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-gray-500">{{ __('No events recorded yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>
