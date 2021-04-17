<?php

declare(strict_types=1);

namespace ConverterService;

/**
 * Interface ConverterServicesInterface
 */
interface ConverterServicesInterface
{
    /**
     * @param string $filePath
     * @param string $fromConvertType
     * @param string $toConvertType
     * @return string
     */
    public function convertData(string $filePath, string $fromConvertType, string $toConvertType): string;
}
