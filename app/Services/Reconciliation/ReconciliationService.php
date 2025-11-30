<?php

namespace App\Services\Reconciliation;

use App\Models\V1\Financial\MobilePayment;
use App\Models\V1\Financial\ReconciliationImport;
use App\Models\V1\Financial\ReconciliationTransaction;
use App\Models\V1\SIS\Student\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReconciliationService
{
    /**
     * Process imported statement
     */
    public function processImport(ReconciliationImport $import, string $filePath): void
    {
        try {
            $transactions = $this->parseStatementFile($filePath, $import->provider);

            DB::beginTransaction();

            $total = count($transactions);
            $matched = 0;
            $unmatched = 0;
            $pending = $total;

            foreach ($transactions as $transactionData) {
                $transaction = ReconciliationTransaction::create([
                    'reconciliation_import_id' => $import->id,
                    'transaction_id' => $transactionData['transaction_id'],
                    'amount' => $transactionData['amount'],
                    'phone' => $this->normalizePhone($transactionData['phone']),
                    'transaction_date' => $transactionData['date'],
                    'description' => $transactionData['description'] ?? null,
                    'match_status' => 'pending',
                ]);

                // Try to auto-match
                $match = $this->autoMatch($transaction, $import->school_id);

                if ($match) {
                    $transaction->update([
                        'match_status' => 'matched',
                        'matched_student_id' => $match['student_id'],
                        'matched_payment_id' => $match['payment_id'] ?? null,
                        'confidence' => $match['confidence'],
                        'match_details' => $match['details'],
                    ]);
                    $matched++;
                    $pending--;
                } else {
                    $unmatched++;
                }
            }

            $import->update([
                'status' => 'completed',
                'total_transactions' => $total,
                'matched' => $matched,
                'unmatched' => $unmatched,
                'pending' => $pending,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reconciliation import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Auto-match transaction to student/payment
     */
    protected function autoMatch(ReconciliationTransaction $transaction, ?int $schoolId): ?array
    {
        // Match by phone number and amount
        $student = Student::where('phone', $transaction->phone)
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->first();

        if ($student) {
            // Try to find matching mobile payment
            $payment = MobilePayment::where('student_id', $student->id)
                ->where('amount', $transaction->amount)
                ->where('status', 'completed')
                ->whereDate('completed_at', $transaction->transaction_date->toDateString())
                ->first();

            $confidence = 'high';
            if (!$payment) {
                // Check if amount matches within 1 day
                $payment = MobilePayment::where('student_id', $student->id)
                    ->where('amount', $transaction->amount)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [
                        $transaction->transaction_date->subDay(),
                        $transaction->transaction_date->addDay(),
                    ])
                    ->first();
                $confidence = $payment ? 'medium' : 'low';
            }

            return [
                'student_id' => $student->id,
                'payment_id' => $payment?->id,
                'confidence' => $confidence,
                'details' => [
                    'matched_by' => 'phone_and_amount',
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                ],
            ];
        }

        // Match by amount and date only (lower confidence)
        $payment = MobilePayment::where('amount', $transaction->amount)
            ->where('status', 'pending')
            ->whereDate('initiated_at', $transaction->transaction_date->toDateString())
            ->when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->first();

        if ($payment && $payment->student) {
            return [
                'student_id' => $payment->student_id,
                'payment_id' => $payment->id,
                'confidence' => 'medium',
                'details' => [
                    'matched_by' => 'amount_and_date',
                    'student_name' => $payment->student->first_name . ' ' . $payment->student->last_name,
                ],
            ];
        }

        return null;
    }

    /**
     * Parse statement file (Excel/CSV)
     */
    protected function parseStatementFile(string $filePath, string $provider): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $transactions = [];

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                // Skip header row
                array_shift($rows);

                foreach ($rows as $row) {
                    if (empty($row[0])) {
                        continue;
                    }

                    $transactions[] = [
                        'transaction_id' => $row[0] ?? '',
                        'date' => $this->parseDate($row[1] ?? ''),
                        'amount' => (float) ($row[2] ?? 0),
                        'phone' => $row[3] ?? '',
                        'description' => $row[4] ?? '',
                    ];
                }
            } elseif ($extension === 'csv') {
                $file = fopen($filePath, 'r');
                $header = fgetcsv($file); // Skip header

                while (($row = fgetcsv($file)) !== false) {
                    $transactions[] = [
                        'transaction_id' => $row[0] ?? '',
                        'date' => $this->parseDate($row[1] ?? ''),
                        'amount' => (float) ($row[2] ?? 0),
                        'phone' => $row[3] ?? '',
                        'description' => $row[4] ?? '',
                    ];
                }
                fclose($file);
            }
        } catch (\Exception $e) {
            Log::error('Failed to parse statement file', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Failed to parse statement file: ' . $e->getMessage());
        }

        return $transactions;
    }

    /**
     * Parse date from various formats
     */
    protected function parseDate(string $dateString): \Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            return now();
        }
    }

    /**
     * Normalize phone number
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (!str_starts_with($phone, '258')) {
            if (str_starts_with($phone, '0')) {
                $phone = '258' . substr($phone, 1);
            } elseif (strlen($phone) === 9) {
                $phone = '258' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Confirm matches
     */
    public function confirmMatches(ReconciliationImport $import, array $matches): void
    {
        DB::beginTransaction();
        try {
            foreach ($matches as $match) {
                $transaction = ReconciliationTransaction::where('reconciliation_import_id', $import->id)
                    ->where('transaction_id', $match['transaction_id'])
                    ->first();

                if ($transaction) {
                    $transaction->update([
                        'match_status' => 'confirmed',
                        'matched_student_id' => $match['student_id'],
                    ]);

                    // Update student balance if payment exists
                    if ($transaction->matched_payment_id) {
                        // Payment already reconciled, no action needed
                    } else {
                        // Create payment record if not exists
                        // This would typically link to an invoice
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

