<?php

declare(strict_types=1);

use App\Enums\EmailCampaign\ContactStatus;
use App\Enums\EmailCampaign\ImportStatus;
use App\Enums\UserWorkspace\Role;
use App\Jobs\EmailCampaign\ImportEmailContactsJob;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'account_id' => $this->user->account_id,
        'user_id' => $this->user->id,
    ]);
    $this->workspace->members()->attach($this->user->id, ['role' => Role::Admin->value]);
    $this->user->update(['current_workspace_id' => $this->workspace->id]);
});

$csv = function (string $body): UploadedFile {
    return UploadedFile::fake()->createWithContent('contacts.csv', $body);
};

it('stores the upload and queues the import job', function () use ($csv): void {
    Queue::fake();
    Storage::fake('local');

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.lists.store'), [
            'name' => 'Khách tháng 10',
            'file' => $csv("email,name\na@example.com,A\n"),
        ])
        ->assertRedirect();

    $list = EmailContactList::query()->firstOrFail();

    expect($list->name)->toBe('Khách tháng 10')
        ->and($list->status)->toBe(ImportStatus::Pending)
        ->and($list->workspace_id)->toBe($this->workspace->id);

    Queue::assertPushed(
        ImportEmailContactsJob::class,
        fn (ImportEmailContactsJob $job): bool => $job->emailContactListId === $list->id,
    );
});

it('rejects a file type that is not a spreadsheet', function (): void {
    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.lists.store'), [
            'name' => 'Sai định dạng',
            'file' => UploadedFile::fake()->create('photo.png', 10, 'image/png'),
        ])
        ->assertSessionHasErrors('file');
});

it('imports valid rows and counts invalid and duplicate ones', function () use ($csv): void {
    Storage::fake('local');

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.lists.store'), [
            'name' => 'Danh sách hỗn hợp',
            'file' => $csv(
                "email,name,city\n".
                "a@example.com,A,Hanoi\n".
                "b@example.com,B,HCM\n".
                "a@example.com,A duplicate,Hanoi\n".
                "khong-phai-email,C,Hue\n".
                ",D,Danang\n",
            ),
        ])
        ->assertRedirect();

    $list = EmailContactList::query()->firstOrFail();

    expect($list->fresh()->status)->toBe(ImportStatus::Completed)
        ->and($list->fresh()->imported_count)->toBe(2)
        ->and($list->fresh()->duplicate_count)->toBe(1)
        ->and($list->fresh()->invalid_count)->toBe(2);

    $contact = EmailContact::query()->where('email', 'a@example.com')->firstOrFail();

    expect($contact->name)->toBe('A')
        ->and($contact->status)->toBe(ContactStatus::Active)
        ->and($contact->custom_fields)->toBe(['city' => 'Hanoi'])
        ->and($contact->workspace_id)->toBe($this->workspace->id);
});

it('deletes the uploaded file once the import finishes', function () use ($csv): void {
    Storage::fake('local');

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.lists.store'), [
            'name' => 'Dọn file tạm',
            'file' => $csv("email\na@example.com\n"),
        ]);

    expect(EmailContactList::query()->firstOrFail()->source_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles('email-imports'))->toBe([]);
});

it('keeps the uploaded file when the import fails so it can be inspected or retried', function () use ($csv): void {
    Storage::fake('local');
    Excel::shouldReceive('import')->once()->andThrow(new RuntimeException('Import failed'));

    $this->actingAs($this->user->fresh())
        ->post(route('app.email-campaigns.lists.store'), [
            'name' => 'Import lỗi',
            'file' => $csv("email\na@example.com\n"),
        ]);

    $list = EmailContactList::query()->firstOrFail()->fresh();

    expect($list->status)->toBe(ImportStatus::Failed)
        ->and($list->source_path)->not->toBeNull()
        ->and(Storage::disk('local')->exists($list->source_path))->toBeTrue();
});
