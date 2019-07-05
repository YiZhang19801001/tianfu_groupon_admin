<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class Sheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{

    protected $workSheet;
    protected $title;
    protected $headings;

    public function __construct(array $workSheet, $title, $headings)
    {
        $this->workSheet = $workSheet;
        $this->title = $title;
        $this->headings = $headings;
    }

    function array(): array
    {
        return $this->workSheet;
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
