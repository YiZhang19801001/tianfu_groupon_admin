<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class WorkSheetsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{

    protected $workSheets;
    protected $title;
    protected $headings;

    public function __construct(array $workSheets, $title, $headings)
    {
        $this->workSheets = $workSheets;
        $this->title = $title;
        $this->headings = $headings;
    }

    function array(): array
    {
        return $this->workSheets;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

}
