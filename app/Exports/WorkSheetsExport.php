<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkSheetsExport implements WithMultipleSheets
{

    use Exportable;

    protected $workSheets;

    public function __construct(array $workSheets, $headings)
    {
        $this->workSheets = $workSheets;
        $this->headings = $headings;
    }

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->workSheets as $sheet) {
            $sheets[] = new Sheet($sheet['data'], $sheet['title'], $this->headings);
        }
        return $sheets;
    }

}
