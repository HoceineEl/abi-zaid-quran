<?php

namespace App\Enums;

use App\Models\Attendance;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Single source of truth for attendance status resolution, labels, icons, and colors.
 *
 * Used across: Teacher relation manager, Score relation manager,
 * Export service, and Blade views.
 */
enum AttendanceStatus: string implements HasColor, HasLabel, HasIcon
{
    case PRESENT = 'present';
    case ABSENT_JUSTIFIED = 'absent_justified';
    case ABSENT_UNJUSTIFIED = 'absent_unjustified';
    case UNMARKED = 'unmarked';

    // ─── Resolution ────────────────────────────────────────────────────

    /**
     * Resolve the attendance status from a nullable Attendance model.
     */
    public static function resolve(?Attendance $attendance): self
    {
        if (! $attendance) {
            return self::UNMARKED;
        }

        if ($attendance->check_in_time === null) {
            return $attendance->absence_justified
                ? self::ABSENT_JUSTIFIED
                : self::ABSENT_UNJUSTIFIED;
        }

        return self::PRESENT;
    }

    /**
     * Resolve the display state for columns that show both attendance status and scores.
     *
     * Returns the MemorizationScore value when the student is present with a score,
     * otherwise returns the AttendanceStatus value.
     */
    public static function resolveDisplayState(?Attendance $attendance): string
    {
        $status = self::resolve($attendance);

        if ($status === self::PRESENT && $attendance?->score instanceof MemorizationScore) {
            return $attendance->score->value;
        }

        return $status->value;
    }

    // ─── Labels ────────────────────────────────────────────────────────

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PRESENT => 'حاضر',
            self::ABSENT_JUSTIFIED => 'غائب مبرر',
            self::ABSENT_UNJUSTIFIED => 'غائب غير مبرر',
            self::UNMARKED => 'غ.م',
        };
    }

    /**
     * Shorter label used in compact badge contexts (e.g. score grid).
     */
    public function getShortLabel(): string
    {
        return match ($this) {
            self::PRESENT => 'حاضر',
            self::ABSENT_JUSTIFIED => 'غائب مبرر',
            self::ABSENT_UNJUSTIFIED => 'غائب',
            self::UNMARKED => 'غ.م',
        };
    }

    /**
     * Label used in export cells (matrix + detail sheets).
     */
    public function getExportLabel(): string
    {
        return match ($this) {
            self::PRESENT => 'حاضر',
            self::ABSENT_JUSTIFIED => 'غائب مبرر',
            self::ABSENT_UNJUSTIFIED => 'غائب غير مبرر',
            self::UNMARKED => 'غ.م',
        };
    }

    // ─── Icons ─────────────────────────────────────────────────────────

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PRESENT => 'heroicon-o-check-circle',
            self::ABSENT_JUSTIFIED => 'heroicon-o-shield-check',
            self::ABSENT_UNJUSTIFIED => 'heroicon-o-x-circle',
            self::UNMARKED => 'heroicon-o-question-mark-circle',
        };
    }

    public function getSolidIcon(): string
    {
        return match ($this) {
            self::PRESENT => 'heroicon-s-check-circle',
            self::ABSENT_JUSTIFIED => 'heroicon-s-shield-check',
            self::ABSENT_UNJUSTIFIED => 'heroicon-s-x-circle',
            self::UNMARKED => 'heroicon-s-question-mark-circle',
        };
    }

    // ─── Filament Colors ───────────────────────────────────────────────

    /**
     * Filament semantic color name (for ->color() on actions, columns, etc.).
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PRESENT => 'success',
            self::ABSENT_JUSTIFIED => 'warning',
            self::ABSENT_UNJUSTIFIED => 'danger',
            self::UNMARKED => 'gray',
        };
    }

    /**
     * Filament Color constant for badge contexts (score grid).
     */
    public function getBadgeColor(): array
    {
        return match ($this) {
            self::PRESENT => Color::Green,
            self::ABSENT_JUSTIFIED => Color::Orange,
            self::ABSENT_UNJUSTIFIED => Color::Red,
            self::UNMARKED => Color::Gray,
        };
    }

    // ─── Export (Excel) Colors ──────────────────────────────────────────

    public function getExportFillColor(): string
    {
        return match ($this) {
            self::PRESENT => 'BBF7D0',
            self::ABSENT_JUSTIFIED => 'FDE68A',
            self::ABSENT_UNJUSTIFIED => 'FECACA',
            self::UNMARKED => 'E5E7EB',
        };
    }

    public function getExportFontColor(): string
    {
        return match ($this) {
            self::PRESENT => '166534',
            self::ABSENT_JUSTIFIED => '92400E',
            self::ABSENT_UNJUSTIFIED => '991B1B',
            self::UNMARKED => '374151',
        };
    }

    // ─── CSS (Blade views) ─────────────────────────────────────────────

    public function getCssStatusClass(): string
    {
        return match ($this) {
            self::PRESENT => 'status-present',
            self::ABSENT_JUSTIFIED => 'status-justified',
            self::ABSENT_UNJUSTIFIED => 'status-absent',
            self::UNMARKED => 'status-unmarked',
        };
    }

    public function getCssIconColor(): string
    {
        return match ($this) {
            self::PRESENT => 'text-success-500',
            self::ABSENT_JUSTIFIED => 'text-warning-500',
            self::ABSENT_UNJUSTIFIED => 'text-danger-500',
            self::UNMARKED => 'text-gray-500',
        };
    }

    // ─── Polymorphic Display Helpers ────────────────────────────────────
    // These accept a string state that could be either an AttendanceStatus
    // value or a MemorizationScore value, providing one source of truth for
    // columns that render both.

    public static function getDisplayLabel(string $state): string
    {
        $status = self::tryFrom($state);
        if ($status) {
            return $status->getShortLabel();
        }

        $score = MemorizationScore::tryFrom($state);
        if ($score) {
            return $score->getLabel();
        }

        return $state;
    }

    public static function getDisplayColor(string $state): string|array|null
    {
        $status = self::tryFrom($state);
        if ($status) {
            return $status->getBadgeColor();
        }

        $score = MemorizationScore::tryFrom($state);
        if ($score) {
            return $score->getColor();
        }

        return Color::Gray;
    }

    public static function getDisplayIcon(string $state): ?string
    {
        $status = self::tryFrom($state);
        if ($status) {
            return $status->getIcon();
        }

        $score = MemorizationScore::tryFrom($state);
        if ($score) {
            return $score->getIcon();
        }

        return null;
    }
}
