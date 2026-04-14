<?php

namespace App\Exports;

use App\Models\Group;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BulkGroupStudentsExport implements WithMultipleSheets
{
    /**
     * @param Collection<int, Group> $groups
     */
    public function __construct(
        protected Collection $groups,
    ) {}

    public function sheets(): array
    {
        return $this->groups
            ->map(fn ($group) => new GroupStudentsSheetExport($group))
            ->all();
    }
}
