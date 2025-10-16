<?php

namespace App\Filament\Pages;

use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SubtitleCleaner extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'منظف الترجمات';

    protected static ?string $title = 'منظف ملفات الترجمة';

    protected static ?string $slug = 'subtitle-cleaner';

    protected string $view = 'filament.pages.subtitle-cleaner';

    protected static string|\UnitEnum|null $navigationGroup = 'أدوات';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cleanSubtitle')
                ->label('تنظيف ملف الترجمة')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->schema([
                    FileUpload::make('subtitle_file')
                        ->label('رفع ملف SRT')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $filePath = storage_path('app/public/'.$data['subtitle_file']);
                        $content = file_get_contents($filePath);

                        // Split content into lines
                        $lines = explode("\n", $content);
                        $cleaned = [];

                        // Process lines in groups of 4 (number, timestamp, text, blank line)
                        for ($i = 0; $i < count($lines); $i++) {
                            // Skip subtitle number, timestamp and blank lines
                            if (
                                ! preg_match('/^\d+$/', trim($lines[$i])) &&
                                ! preg_match('/^\d{2}:\d{2}:\d{2},\d{3}\s-->\s\d{2}:\d{2}:\d{2},\d{3}$/', trim($lines[$i])) &&
                                trim($lines[$i]) !== ''
                            ) {
                                $cleaned[] = $lines[$i];
                            }
                        }

                        // Join lines with spaces
                        $cleaned = implode(' ', $cleaned);

                        // Clean up extra whitespace
                        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
                        $cleaned = trim($cleaned);

                        // Generate cleaned text file
                        $words = explode(' ', $cleaned);
                        $firstSixWords = implode(' ', array_slice($words, 0, 6));
                        $cleanedFileName = $firstSixWords.'.txt';
                        $cleanedFilePath = storage_path('app/public/'.$cleanedFileName);
                        file_put_contents($cleanedFilePath, $cleaned);

                        // Create download response
                        return response()->streamDownload(function () use ($cleanedFilePath) {
                            readfile($cleanedFilePath);
                        }, $cleanedFileName, [
                            'Content-Type' => 'text/plain',
                            'Content-Disposition' => 'attachment; filename='.$cleanedFileName,
                        ]);
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('خطأ في معالجة الملف')
                            ->danger()
                            ->send();

                        return null;
                    }
                }),
        ];
    }

    public function getViewData(): array
    {
        return [
            'instructions' => 'قم برفع ملف SRT لتنظيفه. سيتم إزالة التوقيتات والتنسيقات غير الضرورية.',
        ];
    }
}
