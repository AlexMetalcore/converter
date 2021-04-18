<?php

declare(strict_types=1);

namespace ConverterService;

/**
 * Interface ConverterFormatInterface
 */
interface ConverterFormatInterface
{
    /**
     * @param string $filePath
     * @param string $fromConvertType
     * @param string $toConvertType
     * @return string
     */
    public function convertData(string $filePath, string $fromConvertType, string $toConvertType): string;
}
