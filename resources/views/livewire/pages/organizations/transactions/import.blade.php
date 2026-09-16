<?php

use App\Enums\TransactionStatus;
use App\Models\Organization;
use App\Models\Transaction;
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

    public string $externalIdColumnIndex = '';

    public string $fullNameColumnIndex = '';

    public string $firstNameColumnIndex = '';

    public string $lastNameColumnIndex = '';

    public string $productColumnIndex = '';

    public string $subtotalColumnIndex = '';

    public string $taxColumnIndex = '';

    public string $totalColumnIndex = '';

    public string $feesColumnIndex = '';

    public string $amountColumnIndex = '';

    public string $discountColumnIndex = '';

    public string $currencyColumnIndex = '';

    public string $currency = 'USD';

    public string $statusColumnIndex = '';

    public bool $mapped = false;

    /** @var array<int, string> */
    public array $statusValues = [];

    /**
     * Same order as statusValues: which TransactionStatus each raw CSV
     * status value should be imported as. Defaults to Success for every
     * value.
     *
     * @var array<int, string>
     */
    public array $statusValueAssignments = [];

    public bool $imported = false;

    public int $importedCount = 0;

    public int $skippedCount = 0;

    public int $successCount = 0;

    public int $failCount = 0;

    public int $refundCount = 0;

    /** @var array<int, string> */
    public array $rowErrors = [];

    /**
     * Resolve the organization and confirm the current user may import transactions.
     */
    public function mount(Organization $organization): void
    {
        Gate::authorize('create', [Transaction::class, $organization]);

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

        $this->storedPath = $this->csvFile->store('transactions-imports', 'local');

        $handle = fopen(Storage::disk('local')->path($this->storedPath), 'r');
        $header = fgetcsv($handle) ?: [];
        fclose($handle);

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        }

        $this->headers = $header;
        $this->emailColumnIndex = '';
        $this->externalIdColumnIndex = '';
        $this->fullNameColumnIndex = '';
        $this->firstNameColumnIndex = '';
        $this->lastNameColumnIndex = '';
        $this->productColumnIndex = '';
        $this->subtotalColumnIndex = '';
        $this->taxColumnIndex = '';
        $this->totalColumnIndex = '';
        $this->feesColumnIndex = '';
        $this->amountColumnIndex = '';
        $this->discountColumnIndex = '';
        $this->currencyColumnIndex = '';
        $this->statusColumnIndex = '';
        $this->mapped = false;
        $this->statusValues = [];
        $this->statusValueAssignments = [];
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
            'emailColumnIndex', 'externalIdColumnIndex', 'fullNameColumnIndex', 'firstNameColumnIndex', 'lastNameColumnIndex',
            'productColumnIndex',
            'subtotalColumnIndex', 'taxColumnIndex', 'totalColumnIndex', 'feesColumnIndex', 'amountColumnIndex', 'discountColumnIndex',
            'currencyColumnIndex', 'statusColumnIndex',
            'mapped', 'statusValues', 'statusValueAssignments',
            'imported', 'importedCount', 'skippedCount', 'successCount', 'failCount', 'refundCount', 'rowErrors',
        );
    }

    /**
     * Return to the column mapping form without losing the uploaded file.
     */
    public function backToMapping(): void
    {
        $this->mapped = false;
    }

    /**
     * Validate the column mapping. When a status column was chosen, collect
     * its unique values for the status-assignment step; otherwise every row
     * is imported as Success.
     */
    public function mapColumns(): void
    {
        Gate::authorize('create', [Transaction::class, $this->organization]);

        $this->validate([
            'storedPath' => ['required', 'string'],
            'emailColumnIndex' => ['required', 'string'],
            'productColumnIndex' => ['required', 'string'],
        ], [], [
            'emailColumnIndex' => __('email column'),
            'productColumnIndex' => __('product column'),
        ]);

        if ($this->amountColumnIndex === '' && $this->totalColumnIndex === '' && $this->subtotalColumnIndex === '') {
            $this->addError('amountColumnIndex', __('Map an amount column, or a total or subtotal column to calculate it from.'));

            return;
        }

        if ($this->statusColumnIndex === '') {
            $this->statusValues = [];
            $this->statusValueAssignments = [];
            $this->import();

            return;
        }

        $statusIndex = (int) $this->statusColumnIndex;

        $values = [];

        $handle = fopen(Storage::disk('local')->path($this->storedPath), 'r');
        fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }

            $value = trim((string) ($row[$statusIndex] ?? ''));

            if ($value !== '' && ! in_array($value, $values, true)) {
                $values[] = $value;
            }
        }

        fclose($handle);

        $this->statusValues = $values;
        $this->statusValueAssignments = array_fill(0, count($values), TransactionStatus::Success->value);
        $this->mapped = true;
    }

    /**
     * Import each mapped row, matching or creating the person by email and
     * the product by name, and assigning a status based on the mapped
     * status value (or Success by default when no status column was mapped).
     */
    public function import(): void
    {
        Gate::authorize('create', [Transaction::class, $this->organization]);

        $this->validate([
            'storedPath' => ['required', 'string'],
        ]);

        $emailIndex = (int) $this->emailColumnIndex;
        $externalIdIndex = $this->externalIdColumnIndex === '' ? null : (int) $this->externalIdColumnIndex;
        $fullNameIndex = $this->fullNameColumnIndex === '' ? null : (int) $this->fullNameColumnIndex;
        $firstNameIndex = $this->firstNameColumnIndex === '' ? null : (int) $this->firstNameColumnIndex;
        $lastNameIndex = $this->lastNameColumnIndex === '' ? null : (int) $this->lastNameColumnIndex;
        $productIndex = (int) $this->productColumnIndex;
        $subtotalIndex = $this->subtotalColumnIndex === '' ? null : (int) $this->subtotalColumnIndex;
        $taxIndex = $this->taxColumnIndex === '' ? null : (int) $this->taxColumnIndex;
        $totalIndex = $this->totalColumnIndex === '' ? null : (int) $this->totalColumnIndex;
        $feesIndex = $this->feesColumnIndex === '' ? null : (int) $this->feesColumnIndex;
        $amountIndex = $this->amountColumnIndex === '' ? null : (int) $this->amountColumnIndex;
        $discountIndex = $this->discountColumnIndex === '' ? null : (int) $this->discountColumnIndex;
        $currencyIndex = $this->currencyColumnIndex === '' ? null : (int) $this->currencyColumnIndex;
        $statusIndex = $this->statusColumnIndex === '' ? null : (int) $this->statusColumnIndex;

        $statusAssignments = array_combine($this->statusValues, $this->statusValueAssignments);

        $products = $this->organization->products()->get()->keyBy(fn ($product) => mb_strtolower($product->name));

        $imported = 0;
        $skipped = 0;
        $success = 0;
        $fail = 0;
        $refund = 0;
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
            $productName = trim((string) ($row[$productIndex] ?? ''));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;

                if (count($errors) < self::MAX_ROW_ERRORS_SHOWN) {
                    $errors[] = __('Row :row: missing or invalid email.', ['row' => $rowNumber]);
                }

                continue;
            }

            if ($productName === '') {
                $skipped++;

                if (count($errors) < self::MAX_ROW_ERRORS_SHOWN) {
                    $errors[] = __('Row :row: missing product.', ['row' => $rowNumber]);
                }

                continue;
            }

            $amountCents = null;

            if ($amountIndex !== null) {
                $cleanedAmount = preg_replace('/[^0-9.\-]/', '', trim((string) ($row[$amountIndex] ?? '')));

                if ($cleanedAmount === '' || ! is_numeric($cleanedAmount)) {
                    $skipped++;

                    if (count($errors) < self::MAX_ROW_ERRORS_SHOWN) {
                        $errors[] = __('Row :row: missing or invalid amount.', ['row' => $rowNumber]);
                    }

                    continue;
                }

                $amountCents = (int) round(abs((float) $cleanedAmount) * 100);
            }

            $fullName = $fullNameIndex !== null ? trim((string) ($row[$fullNameIndex] ?? '')) : null;
            $firstName = $firstNameIndex !== null ? trim((string) ($row[$firstNameIndex] ?? '')) : null;
            $lastName = $lastNameIndex !== null ? trim((string) ($row[$lastNameIndex] ?? '')) : null;

            $person = app(IdentityResolver::class)->resolveContact(
                $this->organization->id,
                $email,
                $firstName !== '' ? $firstName : null,
                $lastName !== '' ? $lastName : null,
                $fullName !== '' ? $fullName : null,
            );

            $product = $products->get(mb_strtolower($productName));

            $rowCurrency = $currencyIndex !== null ? trim((string) ($row[$currencyIndex] ?? '')) : $this->currency;
            $rowCurrency = strtoupper($rowCurrency !== '' ? $rowCurrency : $this->currency);

            $rawStatus = $statusIndex !== null ? trim((string) ($row[$statusIndex] ?? '')) : null;
            $status = $rawStatus !== null && isset($statusAssignments[$rawStatus])
                ? TransactionStatus::from($statusAssignments[$rawStatus])
                : TransactionStatus::Success;

            $externalId = $externalIdIndex !== null ? trim((string) ($row[$externalIdIndex] ?? '')) : '';

            $attributes = [
                'person_id' => $person->id,
                'product_id' => $product?->id,
                'product_name' => $productName,
                'external_id' => $externalId !== '' ? $externalId : null,
                'amount_cents' => $amountCents,
                'currency' => $rowCurrency,
                'status' => $status,
                'subtotal_cents' => $this->parseOptionalCents($subtotalIndex !== null ? ($row[$subtotalIndex] ?? null) : null),
                'tax_cents' => $this->parseOptionalCents($taxIndex !== null ? ($row[$taxIndex] ?? null) : null),
                'total_cents' => $this->parseOptionalCents($totalIndex !== null ? ($row[$totalIndex] ?? null) : null),
                'fees_cents' => $this->parseOptionalCents($feesIndex !== null ? ($row[$feesIndex] ?? null) : null),
                'discount_cents' => $this->parseOptionalCents($discountIndex !== null ? ($row[$discountIndex] ?? null) : null),
            ];

            if ($externalId !== '') {
                $this->organization->transactions()->updateOrCreate(['external_id' => $externalId], $attributes);
            } else {
                $this->organization->transactions()->create($attributes);
            }

            $imported++;

            match ($status) {
                TransactionStatus::Success => $success++,
                TransactionStatus::Fail => $fail++,
                TransactionStatus::Refund => $refund++,
            };
        }

        fclose($handle);
        Storage::disk('local')->delete($this->storedPath);

        $this->importedCount = $imported;
        $this->skippedCount = $skipped;
        $this->successCount = $success;
        $this->failCount = $fail;
        $this->refundCount = $refund;
        $this->rowErrors = $errors;
        $this->imported = true;

        $this->reset(
            'csvFile', 'storedPath', 'headers',
            'emailColumnIndex', 'externalIdColumnIndex', 'fullNameColumnIndex', 'firstNameColumnIndex', 'lastNameColumnIndex',
            'productColumnIndex',
            'subtotalColumnIndex', 'taxColumnIndex', 'totalColumnIndex', 'feesColumnIndex', 'amountColumnIndex', 'discountColumnIndex',
            'currencyColumnIndex', 'statusColumnIndex',
            'mapped', 'statusValues', 'statusValueAssignments',
        );
    }

    /**
     * Parse an optional accounting column's raw CSV value into cents, or
     * null if it's blank or unparseable. Unlike the amount column, these
     * breakdown fields are always optional — a bad or missing value just
     * leaves the field unset rather than skipping the row. Always returns a
     * positive value: a source system showing a refund as a negative number
     * is instead expressed via the row's status, not the sign of an amount.
     */
    private function parseOptionalCents(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', trim($raw));

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        return (int) round(abs((float) $cleaned) * 100);
    }
}; ?>

<div>
    <div class="max-w-2xl">
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Import Transactions') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Upload a CSV exported from another product to add its transactions to :organization.', ['organization' => $organization->name]) }}
        </p>
    </div>

    @if ($imported)
        <div class="max-w-2xl mt-6">
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-900">
                    {{ trans_choice(':count transaction imported.|:count transactions imported.', $importedCount, ['count' => $importedCount]) }}
                </p>

                @if ($importedCount > 0)
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __(':success succeeded, :fail failed, :refund refunded.', ['success' => $successCount, 'fail' => $failCount, 'refund' => $refundCount]) }}
                    </p>
                @endif

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

                <a href="{{ route('organizations.transactions', $organization) }}" wire:navigate class="text-sm font-medium text-blue-700 hover:text-blue-800">
                    {{ __('Back to Transactions') }}
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
    @elseif (! $mapped)
        <form wire:submit="mapColumns" class="max-w-2xl mt-6">
            <p class="text-sm text-gray-600">
                {{ __('Match each CSV column to a field. Email and product are required; an amount is required unless a total or subtotal is mapped below.') }}
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
                    <x-input-label for="externalIdColumnIndex" :value="__('External ID')" />
                    <select wire:model="externalIdColumnIndex" id="externalIdColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-500">{{ __("The source system's transaction/order ID, if it has one. Re-importing will update the matching transaction instead of creating a duplicate. Without it, every row is always inserted fresh.") }}</p>
                </div>

                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-gray-900">
                            <svg class="hi-mini hi-user-circle inline-block size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-5.5-2.5a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zM10 12a5.99 5.99 0 00-4.793 2.39A6.483 6.483 0 0010 16.5a6.483 6.483 0 004.793-2.11A5.99 5.99 0 0010 12z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ __('Name') }}</span>
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Map a single combined column, or separate first/last columns.') }}</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div>
                            <x-input-label for="fullNameColumnIndex" :value="__('Full name')" />
                            <select wire:model="fullNameColumnIndex" id="fullNameColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">{{ __("Use this if the CSV has one combined name column. It's split on the first space.") }}</p>
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
                </div>

                <div>
                    <x-input-label for="productColumnIndex" :value="__('Product')" />
                    <select wire:model="productColumnIndex" id="productColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— Select a column —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('productColumnIndex')" class="mt-2" />
                </div>

                <div class="rounded-lg border border-gray-200 overflow-hidden">
                    <div class="bg-gray-50 px-4 py-3">
                        <h3 class="flex items-center gap-2 text-sm font-medium text-gray-900">
                            <svg class="hi-mini hi-credit-card inline-block size-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2.5 4A1.5 1.5 0 001 5.5V6h18v-.5A1.5 1.5 0 0017.5 4h-15zM19 8.5H1v6A1.5 1.5 0 002.5 16h15a1.5 1.5 0 001.5-1.5v-6zM3 13.25a.75.75 0 01.75-.75h1.5a.75.75 0 010 1.5h-1.5a.75.75 0 01-.75-.75zm4.75-.75a.75.75 0 000 1.5h3.5a.75.75 0 000-1.5h-3.5z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ __('Accounting breakdown') }}</span>
                        </h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Listed in the order they\'re calculated: total is subtotal plus tax, and the (net) amount is total minus fees. Map whichever of amount/total/subtotal your source has — the rest fill in automatically. All amounts are stored as positive numbers; use the status column below for refunds.') }}</p>
                    </div>

                    <div class="p-4 space-y-4">
                        <div>
                            <x-input-label for="subtotalColumnIndex" :value="__('Subtotal')" />
                            <select wire:model="subtotalColumnIndex" id="subtotalColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="taxColumnIndex" :value="__('Tax')" />
                            <select wire:model="taxColumnIndex" id="taxColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="totalColumnIndex" :value="__('Total')" />
                            <select wire:model="totalColumnIndex" id="totalColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None (= subtotal + tax) —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="feesColumnIndex" :value="__('Fees')" />
                            <select wire:model="feesColumnIndex" id="feesColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="amountColumnIndex" :value="__('Amount (net)')" />
                            <select wire:model="amountColumnIndex" id="amountColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None (= total − fees) —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('amountColumnIndex')" class="mt-2" />
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <x-input-label for="discountColumnIndex" :value="__('Discount')" />
                            <select wire:model="discountColumnIndex" id="discountColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                                <option value="">{{ __('— None —') }}</option>
                                @foreach ($headers as $index => $header)
                                    <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-sm text-gray-500">{{ __("Informational only — it's already reflected in subtotal, so it isn't used in any calculation. Stored as a positive number no matter how the source shows it.") }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <x-input-label for="currencyColumnIndex" :value="__('Currency column')" />
                    <select wire:model="currencyColumnIndex" id="currencyColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— None —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-sm text-gray-500">{{ __('Used when no currency column is mapped.') }}</p>
                    <x-text-input wire:model="currency" class="mt-1 block w-24" />
                </div>

                <div>
                    <x-input-label for="statusColumnIndex" :value="__('Status column')" />
                    <select wire:model="statusColumnIndex" id="statusColumnIndex" class="block mt-1 w-full rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                        <option value="">{{ __('— None (mark everything Success) —') }}</option>
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}">{{ $header !== '' ? $header : __('Column :n', ['n' => $index + 1]) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('Continue') }}</x-primary-button>
                <x-secondary-button type="button" wire:click="removeFile">{{ __('Choose a different file') }}</x-secondary-button>
            </div>
        </form>
    @else
        <form wire:submit="import" class="max-w-2xl mt-6">
            <p class="text-sm text-gray-600">
                {{ __('Choose which outcome each status value represents.') }}
            </p>

            <div class="mt-4 divide-y divide-gray-200 border-t border-b border-gray-200">
                @foreach ($statusValues as $index => $value)
                    <div class="flex items-center justify-between gap-4 py-3">
                        <span class="text-sm text-gray-700">{{ $value }}</span>
                        <select wire:model="statusValueAssignments.{{ $index }}" class="rounded-lg border border-gray-200 px-3 py-2 leading-6 focus:border-blue-500 focus:outline-none focus:ring-3 focus:ring-blue-500/50">
                            <option value="success">{{ __('Success') }}</option>
                            <option value="fail">{{ __('Fail') }}</option>
                            <option value="refund">{{ __('Refund') }}</option>
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center gap-4">
                <x-primary-button>{{ __('Import') }}</x-primary-button>
                <x-secondary-button type="button" wire:click="backToMapping">{{ __('Back') }}</x-secondary-button>
            </div>
        </form>
    @endif
</div>
