<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BulkGroupStudentsExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, \App\Models\Group>  $groups
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
