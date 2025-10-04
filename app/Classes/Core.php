<?php

namespace App\Classes;

use App\Models\Group;
use App\Models\Message;
use App\Models\Student;
use App\Models\User;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Filament\Notifications\Notification;

class Core
{
    public static function sendMessageToAbsence(?Group $group = null): void
    {
        $whatsAppService = new WhatsAppService();
        if ($group !== null) {
            $students = $group->students()->with(['progresses' => function ($query) {
                $query->where('date', '>=', Carbon::now()->subDays(3)->toDateString())
                    ->orderBy('date', 'desc');
            }])->get();
        } else {
            $students = Student::with(['progresses' => function ($query) {
                $query->where('date', '>=', Carbon::now()->subDays(3)->toDateString())
                    ->orderBy('date', 'desc');
            }])->get();
        }
        $res = null;
        foreach ($students as $student) {
            $progresses = $student->progresses;
            if ($progresses->isEmpty()) {
                continue;
            }
            $absentCount = $progresses->where('status', 'absent')->count();

            if ($absentCount == 1) {
                $res = $whatsAppService->sendMessage($student);
            }
            if ($absentCount == 2) {
                $res = $whatsAppService->sendMessage($student);
            }

            if ($absentCount >= 3) {
                $res = $whatsAppService->sendMessage($student);
            }
            // if (isset($res['contacts'])) {
            //     Notification::make()
            //         ->title('تم إرسال رسالة واتساب للطالب '.$student->name)
            //         ->color('success')
            //         ->icon('heroicon-o-check-circle')
            //         ->send();
            // }
        }
        if (! $res) {
            Notification::make()
                ->title('ليس هناك طلاب غائبين اليوم ولله الحمد')
                ->color('info')
                ->icon('heroicon-o-information-circle')
                ->send();
        }
    }

    public static function sendMessageToSpecific($data, $type = 'student'): void
    {
        $whatsAppService = new WhatsAppService();
        $message = $data['message_type'] === 'custom' ? $data['message'] : Message::find($data['message'])->content;
        foreach ($data['students'] as $studentId) {
            if ($type === 'student') {
                $user = Student::find($studentId);
            } else {
                $user = User::find($studentId);
            }
            $res = $whatsAppService->sendCustomMessage($user, $message);
            if (isset($res['contacts'])) {
                // Notification::make()
                //     ->title('تم إرسال رسالة واتساب  ل'.$user->name)
                //     ->color('success')
                //     ->icon('heroicon-o-check-circle')
                //     ->send();
            } else {
                Notification::make()
                    ->title('حدث خطأ أثناء إرسال رسالة واتساب ل ' . $user->name)
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->send();
            }
        }
    }

    public static function sendMessageToStudent(Student $student)
    {
        $whatsAppService = new WhatsAppService();
        $res = $whatsAppService->sendMessage($student);
        if (isset($res['contacts'])) {
            // Notification::make()
            //     ->title('تم إرسال رسالة واتساب للطالب ' . $student->name)
            //     ->color('success')
            //     ->icon('heroicon-o-check-circle')
            //     ->send();
        } else {
            Notification::make()
                ->title('حدث خطأ أثناء إرسال رسالة واتساب للطالب ' . $student->name)
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->send();
        }
    }

    public static function sendSpecifMessageToStudent(Student $student, $message)
    {
        $whatsAppService = new WhatsAppService();
        $res = $whatsAppService->sendCustomMessage($student, $message);
        if (isset($res['contacts'])) {
            // Notification::make()
            //     ->title('تم إرسال رسالة واتساب للطالب ' . $student->name)
            //     ->color('success')
            //     ->icon('heroicon-o-check-circle')
            //     ->send();
        } else {
            Notification::make()
                ->title('حدث خطأ أثناء إرسال رسالة واتساب للطالب ' . $student->name)
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->send();
        }
    }

    /**
     * Process a message template by replacing variables with actual values
     *
     * @param string $template The message template with variables
     * @param Student $student The student object
     * @param Group|null $group The group object (optional)
     * @return string The processed message with variables replaced
     */
    public static function processMessageTemplate(string $template, Student $student, ?Group $group = null): string
    {
        // Set locale to Arabic
        Carbon::setLocale('ar');

        // Get variable values from the GroupMessageTemplate model
        if ($group) {
            $replacements = \App\Models\GroupMessageTemplate::getVariableValues($student, $group);
        } else {
            // Only replace student_name and curr_date if no group is provided
            $replacements = [
                '{student_name}' => trim($student->name),
                '{curr_date}' => Carbon::now()->translatedFormat('l d F Y'),
            ];
        }

        // Replace only the specified variables in the template
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    public static function canChange(): bool
    {
        return auth()->user()->isAdministrator();
    }
}
