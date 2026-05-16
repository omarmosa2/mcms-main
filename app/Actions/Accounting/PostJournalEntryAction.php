<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;

class PostJournalEntryAction extends BaseAction
{
    public function handle(
        int $clinicId,
        int $userId,
        array $lines,
        string $description,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $entryDate = null,
    ): JournalEntry {
        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new \InvalidArgumentException(
                "Journal entry is not balanced. Debit: $totalDebit, Credit: $totalCredit",
            );
        }

        $entryNumber = $this->generateEntryNumber($clinicId);

        return \DB::transaction(function () use (
            $clinicId,
            $userId,
            $lines,
            $description,
            $referenceType,
            $referenceId,
            $entryNumber,
            $entryDate,
        ) {
            $entry = JournalEntry::query()->create([
                'clinic_id' => $clinicId,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate ?? now()->toDateString(),
                'description' => $description,
                'status' => JournalEntry::STATUS_POSTED,
                'created_by' => $userId,
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    private function generateEntryNumber(int $clinicId): string
    {
        $lastEntry = JournalEntry::query()
            ->forClinic($clinicId)
            ->orderByDesc('id')
            ->first();

        $nextNumber = $lastEntry
            ? (int) str_replace('JE-', '', $lastEntry->entry_number) + 1
            : 1;

        return 'JE-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
    }
}
