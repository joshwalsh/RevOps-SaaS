<?php

use App\Models\Organization;
use App\Models\TenantPeople;
use App\Services\IdentityResolver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    const MAX_ROW_ERRORS_SHOWN = 20;

    public Organization $organization;

    public $csvFile;

    public string $storedPath = '';

    /** @var array<int, string> */
    public array $headers = [];

    public string $emailColumnIndex = '';

    public string $firstNameColumnIndex = '';

    public string $lastNameColumnIndex = '';

    public bool $imported = false;

    public int $importedCount = 0;

    public int $skippedCount = 0;

    /** @var array<int, string> */
    public array $rowErrors = [];

    /**
     * Resolve the organization and confirm the current user may import people.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('create', [TenantPeople::class, $organization]);

        $this->organization = $organization;
    }

    /**
     * Store the uploaded CSV and read its header row for column mapping.
     */
    public function updatedCsvFile(): void
    {
        $this->validate([
            'csvFile' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $this->storedPath = $this->csvFile->store('people-imports', 'local');

        $handle = fopen(Storage::disk('local')->path($this->storedPath), 'r');
        $header = fgetcsv($handle) ?: [];
        fclose($handle);

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        $this->headers = $header;
        $this->emailColumnIndex = '';
        $this->firstNameColumnIndex = '';
        $this->lastNameColumnIndex = '';
        $this->imported = false;
    }

    /**
     * Discard the uploaded file and mapping so a different file can be chosen.
     */
    public function removeFile(): void
    {
        if ($this->storedPath !== '') {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(
            'csvFile', 'storedPath', 'headers',
            'emailColumnIndex', 'firstNameColumnIndex', 'lastNameColumnIndex',
            'imported', 'importedCount', 'skippedCount', 'rowErrors',
        );
    }

    /**
     * Import each mapped row, skipping and reporting rows without a usable email.
     */
    public function import(): void
    {
        Gate::authorize('create', [TenantPeople::class, $this->organization]);

        $this->validate([
            'storedPath' => ['required', 'string'],
            'emailColumnIndex' => ['required', 'string'],
        ], [], ['emailColumnIndex' => __('email column')]);

        $emailIndex = (int) $this->emailColumnIndex;
        $firstNameIndex = $this->firstNameColumnIndex === '' ? null : (int) $this->firstNameColumnIndex;
        $lastNameIndex = $this->lastNameColumnIndex === '' ? null : (int) $this->lastNameColumnIndex;

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $handle = fopen(Storage::disk('local')->path($this->storedPath), 'r');
        fgetcsv($handle);

        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($row === [null]) {
                continue;
            }

            $email = trim((string) ($row[$emailIndex] ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                if (count($errors) < self::MAX_ROW_ERRORS_SHOWN) {
                    $errors[] = __('Row :row: missing or invalid email.', ['row' => $rowNumber]);
                }

                continue;
            }

            $firstName = $firstNameIndex !== null ? trim((string) ($row[$firstNameIndex] ?? '')) : null;
            $lastName = $lastNameIndex !== null ? trim((string) ($row[$lastNameIndex] ?? '')) : null;

            app(IdentityResolver::class)->resolveContact(
                $this->organization->id,
                $email,
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
            );

            $imported++;
        }

        fclose($handle);
        Storage::disk('local')->delete($this->storedPath);

        $this->importedCount = $imported;
        $this->skippedCount = $skipped;
        $this->rowErrors = $errors;
        $this->imported = true;

        $this->reset('csvFile', 'storedPath', 'headers', 'emailColumnIndex', 'firstNameColumnIndex', 'lastNameColumnIndex');
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Import People') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Upload a CSV exported from another product to add its contacts to :organization.', ['organization' => $organization->name]) }}
        </p>
    </div>

    @if ($imported)
        <div class="max-w-2xl mt-6">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-900">
                    {{ trans_choice(':count person imported.|:count people imported.', $importedCount, ['count' => $importedCount]) }}
                </p>

                @if ($skippedCount > 0)
                    <p class="mt-1 text-sm text-gray-600">
                        {{ trans_choice(':count row skipped.|:count rows skipped.', $skippedCount, ['count' => $skippedCount]) }}
                    </p>

                    <ul class="mt-2 text-sm text-gray-500 list-disc list-inside">
                        @foreach ($rowErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                        @if ($skippedCount > count($rowErrors))
                            <li>{{ __('...and :count more.', ['count' => $skippedCount - count($rowErrors)]) }}</li>
                        @endif
                    </ul>
                @endif
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-secondary-button type="button" wire:click="removeFile">
                    {{ __('Import another file') }}
                </x-secondary-button>

                <a href="{{ route('organizations.people.index', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
                    {{ __('Back to People') }}
                </a>
            </div>
        </div>
    @elseif (empty($headers))
        <div class="max-w-2xl mt-6">
            <x-input-label for="csvFile" :value="__('CSV file')" />
            <input type="file" wire:model="csvFile" id="csvFile" accept=".csv,text/csv" class="block mt-1 w-full text-sm text-gray-900" />
            <p class="mt-1 text-sm text-gray-500" wire:loading wire:target="csvFile">{{ __('Uploading...') }}</p>
            <x-input-error :messages="$errors->get('csvFile')" class="mt-2" />
        </div>
    @else
        <form wire:submit="import" class="max-w-2xl mt-6">
            <p class="text-sm text-gray-600">
                {{ __('Match each CSV column to a field. Email is required so contacts can be identified.') }}
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <x-input-label for="emailColumnIndex" :value="__('Email')" />
                    <select wire:model="emailColumnIndex" id="emailColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— Select a column —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('emailColumnIndex')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="firstNameColumnIndex" :value="__('First name')" />
                    <select wire:model="firstNameColumnIndex" id="firstNameColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="lastNameColumnIndex" :value="__('Last name')" />
                    <select wire:model="lastNameColumnIndex" id="lastNameColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('Import') }}</x-primary-button>
                <x-secondary-button type="button" wire:click="removeFile">{{ __('Choose a different file') }}</x-secondary-button>
            </div>
        </form>
    @endif
</div>
