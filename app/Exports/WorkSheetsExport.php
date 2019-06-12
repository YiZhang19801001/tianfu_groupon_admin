<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class WorkSheetsExport implements FromArray, WithHeadings, WithTitle
{

    protected $workSheets;
    protected $title;

    public function __construct(array $workSheets, $title)
    {
        $this->workSheets = $workSheets;
        $this->title = $title;
    }

    function array(): array
    {
        return $this->workSheets;
    }

    public function headings(): array
    {
        return [
            'product_id',
            'product_name',
            'image',
            'price',
            'store_name',
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

}
