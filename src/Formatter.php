<?php

declare(strict_types=1);

namespace ConverterService;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Class Formatter
 * @package ConverterService
 */
class Formatter implements ConverterFormatInterface
{
    /**
     * @param string $filePath
     * @param string $fromConvertType
     * @param string $toConvertType
     * @return string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function convertData(string $filePath, string $fromConvertType, string $toConvertType): string
    {
        $reader = IOFactory::createReader($fromConvertType);
        if ($reader->canRead($filePath)) {
            $spreadsheet = $reader->load($filePath);
            $spreadsheet->setMinimized(true);
            $writer = IOFactory::createWriter($spreadsheet, $toConvertType);

            $fromConvertType = mb_strtolower($fromConvertType);
            $toConvertType = mb_strtolower($toConvertType);

            if (($fromConvertType === 'csv' || $toConvertType === 'html') && ($fromConvertType === 'html' || $toConvertType === 'csv')) {
                $writer->setSheetIndex(0)->setDelimiter(',');
            }
            ob_start();
            $writer->save('php://output');
            $data = ob_get_contents();
            ob_end_clean();
            return $data;
        }
    }
}