<?php

declare(strict_types=1);

namespace ConverterService;

use DOMDocument;
use stringEncode\Exception;

/**
 * Class ConverterService
 */
class ConverterService
{
    public const TYPE_HTML = 'Html';
    public const TYPE_CSV = 'Csv';
    public const TYPE_XLS = 'Xls';
    public const TYPE_XLSX = 'Xlsx';

    /**
     * @var array[]
     */
    private $fileExtensionImages = ['png', 'jpg'];

    /**
     * @var object
     */
    private $httpClient;

    /**
     * @param object $httpClient
     * @return $this
     */
    public function setHttpClient(object $httpClient): self
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * @param object $request
     * @param ConverterFormatInterface $converter
     * @return string|null
     * @throws Exception
     */
    public function getDataFormat(object $request, ConverterFormatInterface $converter)
    {
        if ($request === null) {
            throw new Exception('Empty request');
        }

        if (method_exists($request, 'getRawBody')) {
            $data = $request->getRawBody();
        } else {
            $data = json_encode($request);
        }

        $obj = $this->getJson($data);

        $resource = tmpfile();
        $filePathHtml = stream_get_meta_data($resource)['uri'];

        if (property_exists($obj, 'html')) {
            file_put_contents($filePathHtml, str_replace('<br>', '', $obj->html));
        } else {
            file_put_contents($filePathHtml, str_replace('<br>', '', $obj));
        }

        return $converter->convertData($filePathHtml, self::TYPE_HTML, self::TYPE_CSV);
    }

    /**
     * Можно передавать файл, массив файлов, файл в base64, url
     * @param $sourcePdf
     * Формат опций массив вида
     * ['image' => 'png', 'style' => [
     *          'p' => 'position:absolute; top:70px; left:65px; white-space:nowrap',
     *      ]
     * ]
     * @param array $options
     * @return string
     * @throws Exception
     */
    public function convertPdfToHtml($sourcePdf, array $options = []): string
    {
        $outputData = null;

        $tmpDir = $this->createTemporaryDirectory();
        $sourcePdf = $this->prepareInputDataFiles($sourcePdf, $tmpDir);
        $files = $this->createFilesForConvert($sourcePdf, $tmpDir, $options['image']);

        $images = $files['images'];
        $htmls = $files['htmls'];

        if (empty($images)) {
            $outputData = $this->contentHtmlWithoutImage($htmls, $options['style']);
        } else {
            if (count($images) === count($htmls)) {
                $prepareDataConvert = array_combine($htmls, $images);
            } else {
                $prepareDataConvert = $this->matchingHtmlsAndImagesFiles($htmls, $images);
            }

            if (empty($prepareDataConvert) === false) {
                $outputData = $this->prepareContentForOutput($tmpDir, $prepareDataConvert, $options['style']);
            }
        }

        $this->clearAllFilesFromTemporaryDirectory($tmpDir);

        return $outputData !== null ? $outputData : '';
    }

    /**
     * @param $sourcePdf
     * @param string $tmpDir
     * @return array|mixed|string[]
     * @throws Exception
     */
    private function prepareInputDataFiles($sourcePdf, string $tmpDir): array
    {
        if (empty($sourcePdf)) {
            throw new Exception('Empty data');
        }

        $data = [];

        if (is_string($sourcePdf)) {
            if (filter_var($sourcePdf, FILTER_VALIDATE_URL)) {
                $data[] = $this->getDataFromUrl($sourcePdf, $tmpDir);
            } else {
                $data[] = $this->checkEncodeDataFileInput($sourcePdf, $tmpDir);
            }
        }

        if (is_array($sourcePdf)) {
            foreach ($sourcePdf as $item) {
                $data[] = $this->checkEncodeDataFileInput($item, $tmpDir);
            }
        }

        return $data;
    }

    /**
     * @param string $sourcePdf
     * @param string $tmpDir
     * @return string
     * @throws Exception
     */
    private function getDataFromUrl(string $sourcePdf, string $tmpDir): string
    {
        if ($this->httpClient) {
            $response = $this->httpClient->get($sourcePdf);
            if ($response['error']) {
                throw new Exception('Error get data from url');
            }
            $response = $response['result'];
        } else {
            $response = @file_get_contents($sourcePdf);
            if ($response === false) {
                throw new Exception("Cannot access '$sourcePdf' to read contents.");
            }
        }

        $file = $tmpDir . sha1($response) . '.pdf';
        file_put_contents($file, $response);

        return $file;
    }

    /**
     * @param string $sourcePdf
     * @param string $tmpDir
     * @return string
     */
    private function checkEncodeDataFileInput(string $sourcePdf, string $tmpDir): string
    {
        if (base64_decode($sourcePdf, true)) {
            $strFile = base64_decode($sourcePdf);
            $file = $tmpDir . sha1($strFile) . '.pdf';
            file_put_contents($file, $strFile);
            return $file;
        } else {
            return $sourcePdf;
        }
    }

    /**
     * @return string
     */
    private function createTemporaryDirectory(): string
    {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'convertpdftohtml' . DIRECTORY_SEPARATOR;

        if (file_exists($tmpDir) === false) {
            mkdir($tmpDir, 0777, true);
        }

        return $tmpDir;
    }

    /**
     * @param array $htmls
     * @param array|null $options
     * @return string
     */
    private function contentHtmlWithoutImage(array $htmls, ?array $options = []): string
    {
        $dataContent = [];

        foreach ($htmls as $html) {
            if (empty($options) === false) {
                $html = $this->replaceTagsOptions($html, $options);
            }
            $dataContent[] = file_get_contents($html);
        }

        return implode('', $dataContent);
    }

    /**
     * @param string $html
     * @param array $options
     * @return string
     */
    private function replaceTagsOptions(string $html, array $options): string
    {
        $tagsReplace = $options['allTagsReplace'] ? -1 : 1;
        foreach ($options as $tag => $properties) {
            $strHtml = file_get_contents($html);
            if ($tag === 'body') {
                file_put_contents($html, preg_replace("/(<" . $tag . " bgcolor=\")(.*?)(\")/", "$1$properties$3", $strHtml, $tagsReplace));
            } else {
                file_put_contents($html, preg_replace("/(<" . $tag . " style=\")(.*?)(\")/", "$1$properties$3", $strHtml, $tagsReplace));
            }
        }
        return $html;
    }

    /**
     * @param $sourcePdf
     * @param string $tmpDir
     * @param string|null $options
     * @return array
     * @throws Exception
     */
    private function createFilesForConvert($sourcePdf, string $tmpDir, string $options = null): array
    {
        foreach ($sourcePdf as $item) {
            if (file_exists($item)) {
                $outputFile = $tmpDir . sha1(uniqid() . $item);
                if (empty($options) === false) {
                    if (in_array($options , $this->fileExtensionImages) === false) {
                        throw new Exception('Incorrect extension file to convert');
                    }
                    exec('/usr/bin/pdftohtml -c -s -fmt ' . $options . ' ' . $item . ' ' . $outputFile);
                } else {
                    exec('/usr/bin/pdftohtml -c -s -i ' . $item . ' ' . $outputFile);
                }
            } else {
                throw new Exception('File pdf does not exists');
            }
        }

        $images = glob($tmpDir . "*.$options");
        $htmls = glob($tmpDir . "*.html");

        if (empty($images) && empty($htmls)) {
            throw new Exception('Files does not exists');
        }

        return [
            'images' => $images,
            'htmls' => $htmls
        ];
    }

    /**
     * @param string $tmpDir
     * @param array $prepareDataConvert
     * @param array|null $options
     * @return string
     */
    private function prepareContentForOutput(string $tmpDir, array $prepareDataConvert, ?array $options = []): string
    {
        $dataContent = [];

        foreach ($prepareDataConvert as $html => $images) {

            $strHtml = file_get_contents($html);

            if (is_array($images) && count($images) > 1) {
                $baseEncodeImages = [];

                foreach ($images as $item) {
                    $filePath = $tmpDir . $item;
                    $data = file_get_contents($filePath);
                    $baseEncodeImages[] = 'data:' . mime_content_type($filePath) . ';base64,' . base64_encode($data);
                }

                $dom = new DOMDocument();
                @$dom->loadHTML($strHtml);

                $images = $dom->getElementsByTagName('img');

                if (\count($images) !== 0) {
                    foreach ($images as $key => $image) {
                        $image->setAttribute('src', $baseEncodeImages[$key]);
                    }
                }
                $dataContent[] = @$dom->saveHTML();
            } else {
                $image = is_array($images) ? current($images) : basename($images);
                $filePath = $tmpDir . $image;
                $data = file_get_contents($filePath);
                $baseEncodeImage = 'data:' . mime_content_type($filePath) . ';base64,' . base64_encode($data);
                $tagsReplace = $options['allTagsReplace'] ? -1 : 1;
                $patternSrc = "!(src=\")(.*?)(\")!si";
                if (empty($options) === false) {
                    $html = $this->replaceTagsOptions($html, $options);
                    $strHtml = file_get_contents($html);
                }
                file_put_contents($html, preg_replace($patternSrc, "$1$baseEncodeImage$3", $strHtml, $tagsReplace));
                $dataContent[] = file_get_contents($html);
            }
        }

        return implode('', $dataContent);
    }

    /**
     * @param string $tmpDir
     */
    private function clearAllFilesFromTemporaryDirectory(string $tmpDir): void
    {
        $files = glob($tmpDir . '*', GLOB_MARK);

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        rmdir($tmpDir);
    }

    /**
     * @param array $htmls
     * @param array $images
     * @return array
     */
    private function matchingHtmlsAndImagesFiles(array $htmls, array $images): array
    {
        $imagesBaseNameFiles = [];
        $prepareDataConvert = [];

        foreach ($images as $image) {
            $imagesBaseNameFiles[] = pathinfo($image)['basename'];
        }

        foreach ($htmls as $html) {
            $prepareDataConvert[$html] = preg_grep("/^" . explode('-', pathinfo($html)['filename'])[0] . "/", $imagesBaseNameFiles);
        }

        return $prepareDataConvert;
    }

    /**
     * @param string $string
     * @return mixed
     * @throws Exception
     */
    private function getJson(string $string)
    {
        $data = json_decode($string);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        } else {
            throw new Exception("Invalid or malformed JSON");
        }
    }
}
