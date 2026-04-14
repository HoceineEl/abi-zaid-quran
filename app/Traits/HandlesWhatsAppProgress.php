<?php

namespace App\Traits;

use Illuminate\Support\Collection;
use App\Models\Student;

trait HandlesWhatsAppProgress
{
    /**
     * Create or update progress record when WhatsApp message is sent
     *
     * @param Student $student The student to create/update progress for
     * @param string|null $date Optional date, defaults to today
     * @return void
     */
    protected function createWhatsAppProgressRecord(Student $student, ?string $date = null): void
    {
        $date = $date ?? now()->format('Y-m-d');

        // Check if progress already exists for this date
        $existingProgress = $student->progresses()->where('date', $date)->first();

        if (!$existingProgress) {
            // Create new progress record
            $student->progresses()->create([
                'created_by' => auth()->id(),
                'date' => $date,
                'page_id' => null,
                'lines_from' => null,
                'lines_to' => null,
                'status' => null,
                'comment' => 'message_sent_whatsapp',
            ]);
        } else {
            // Update existing progress to mark that WhatsApp message was sent
            $existingProgress->update([
                'comment' => $existingProgress->comment === 'message_sent'
                    ? 'message_sent'
                    : 'message_sent_whatsapp',
            ]);
        }
    }

    /**
     * Mark multiple students with WhatsApp progress records
     *
     * @param Collection $students Collection of students
     * @param string|null $date Optional date, defaults to today
     * @return void
     */
    protected function createWhatsAppProgressRecords($students, ?string $date = null): void
    {
        foreach ($students as $student) {
            $this->createWhatsAppProgressRecord($student, $date);
        }
    }
}