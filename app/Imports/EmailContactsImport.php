<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\EmailCampaign\ContactStatus;
use App\Models\EmailContact;
use App\Models\EmailContactList;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads an uploaded CSV/XLSX of contacts into `email_contacts`.
 *
 * The `email` column is required; `name` is optional; every other column is
 * kept in `custom_fields` so the campaign composer can use it as a
 * `{{ fields.<column> }}` placeholder.
 *
 * Rows are counted, not thrown away: the operator sees how many were invalid
 * or duplicated rather than silently losing them.
 */
class EmailContactsImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    private const EMAIL_HEADINGS = ['email', 'e_mail', 'mail', 'email_address', 'dia_chi_email'];

    private const NAME_HEADINGS = ['name', 'full_name', 'ho_ten', 'hoten', 'ten', 'contact_name'];

    public int $totalRows = 0;

    public int $importedCount = 0;

    public int $invalidCount = 0;

    public int $duplicateCount = 0;

    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private readonly EmailContactList $list)
    {
        $this->seen = EmailContact::query()
            ->where('email_contact_list_id', $this->list->id)
            ->pluck('email')
            ->mapWithKeys(fn (string $email): array => [mb_strtolower($email) => true])
            ->all();
    }

    public function collection(Collection $rows): void
    {
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $this->totalRows++;

            $values = collect($row)
                ->mapWithKeys(fn ($value, $key): array => [(string) $key => $value])
                ->all();

            $email = $this->pick($values, self::EMAIL_HEADINGS);
            $email = is_string($email) ? trim(mb_strtolower($email)) : '';

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->invalidCount++;

                continue;
            }

            if (isset($this->seen[$email])) {
                $this->duplicateCount++;

                continue;
            }

            $this->seen[$email] = true;

            $name = $this->pick($values, self::NAME_HEADINGS);

            $payload[] = [
                'id' => (string) Str::uuid(),
                'workspace_id' => $this->list->workspace_id,
                'email_contact_list_id' => $this->list->id,
                'email' => $email,
                'name' => is_scalar($name) && (string) $name !== '' ? (string) $name : null,
                'custom_fields' => json_encode($this->extraFields($values), JSON_UNESCAPED_UNICODE),
                'status' => ContactStatus::Active->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $this->importedCount++;
        }

        if ($payload !== []) {
            EmailContact::query()->insert($payload);
        }
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $candidates
     */
    private function pick(array $values, array $candidates): mixed
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $values) && $values[$candidate] !== null) {
                return $values[$candidate];
            }
        }

        return null;
    }

    /**
     * Every column that is neither the email nor the name, kept as template data.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, scalar|null>
     */
    private function extraFields(array $values): array
    {
        $reserved = array_merge(self::EMAIL_HEADINGS, self::NAME_HEADINGS);
        $fields = [];

        foreach ($values as $key => $value) {
            if (in_array($key, $reserved, true) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }
}
