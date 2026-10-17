<?php

declare(strict_types=1);

namespace App\Jobs\EmailCampaign;

use App\Enums\EmailCampaign\ImportStatus;
use App\Imports\EmailContactsImport;
use App\Models\EmailContactList;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ImportEmailContactsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $emailContactListId)
    {
        $this->onQueue('mail-campaign');
    }

    public function handle(): void
    {
        $list = EmailContactList::query()->find($this->emailContactListId);

        if ($list === null || $list->status !== ImportStatus::Pending) {
            return;
        }

        $path = $list->source_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            $list->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'Không tìm thấy file đã tải lên.',
            ]);

            return;
        }

        $list->update(['status' => ImportStatus::Importing]);

        $import = new EmailContactsImport($list);

        try {
            Excel::import($import, Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Log::error('Email contact import failed', [
                'email_contact_list_id' => $list->id,
                'message' => $e->getMessage(),
            ]);

            $list->update([
                'status' => ImportStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            Storage::disk('local')->delete($path);

            return;
        }

        $list->update([
            'status' => ImportStatus::Completed,
            'total_rows' => $import->totalRows,
            'imported_count' => $import->importedCount,
            'invalid_count' => $import->invalidCount,
            'duplicate_count' => $import->duplicateCount,
            'error_message' => null,
        ]);

        Storage::disk('local')->delete($path);
        $list->update(['source_path' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        EmailContactList::query()
            ->whereKey($this->emailContactListId)
            ->update([
                'status' => ImportStatus::Failed->value,
                'error_message' => $exception?->getMessage(),
            ]);
    }
}
