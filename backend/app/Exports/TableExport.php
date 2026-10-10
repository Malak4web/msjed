<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * تصدير أي تقرير (جدول) لـ Excel: ورقة RTL، عنوان، رأس ملوّن، وصف إجمالي.
 * $table = ['title', 'subtitle', 'columns' => [['key','label','type']], 'rows', 'totals']
 */
class TableExport implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    private const HEADER_ROW = 4;

    public function __construct(private array $table, private string $mosqueName) {}

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $this->table['title']), 0, 31);
    }

    public function array(): array
    {
        $columns = $this->table['columns'];
        $out = [
            [$this->mosqueName],
            [$this->table['title']],
            [$this->table['subtitle'] ?? ''],
            array_column($columns, 'label'),
        ];

        foreach ($this->table['rows'] as $row) {
            $out[] = array_map(fn ($c) => $this->cell($row[$c['key']] ?? null, $c['type'] ?? 'text'), $columns);
        }

        if (! empty($this->table['totals'])) {
            $out[] = array_map(
                fn ($c, $i) => $i === 0 ? 'الإجمالي' : (array_key_exists($c['key'], $this->table['totals']) ? $this->cell($this->table['totals'][$c['key']], $c['type'] ?? 'text') : ''),
                $columns,
                array_keys($columns),
            );
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->setRightToLeft(true);

                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $header = 'A'.self::HEADER_ROW.':'.$lastCol.self::HEADER_ROW;

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF1F6F50');
                $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
                $sheet->getStyle('A3')->getFont()->getColor()->setARGB('FF4B5563');

                $sheet->getStyle($header)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
                $sheet->getStyle($header)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F6F50');

                foreach ($this->table['columns'] as $i => $c) {
                    if (($c['type'] ?? '') === 'money') {
                        $col = $this->letter($i);
                        $sheet->getStyle($col.(self::HEADER_ROW + 1).':'.$col.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
                    }
                }

                if (! empty($this->table['totals'])) {
                    $range = 'A'.$lastRow.':'.$lastCol.$lastRow;
                    $sheet->getStyle($range)->getFont()->setBold(true);
                    $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAF7EC');
                }

                $sheet->freezePane('A'.(self::HEADER_ROW + 1));
            },
        ];
    }

    private function cell(mixed $value, string $type): mixed
    {
        return match ($type) {
            'money', 'number' => $value === null || $value === '' ? null : (float) $value,
            default => $value,
        };
    }

    private function letter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = intdiv($index - $mod, 26);
        }

        return $letter;
    }
}
