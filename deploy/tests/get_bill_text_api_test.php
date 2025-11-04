<?php

if (!defined('SESSION_LIS_ID')) {
    define('SESSION_LIS_ID', '251');
}
if (!defined('LIS_KEY')) {
    define('LIS_KEY', 'test-key');
}

require_once __DIR__ . '/../../htdocs/includes/class.Log.php';
require_once __DIR__ . '/../../htdocs/includes/class.Import.php';

class TestLog extends Log
{
    public function __construct()
    {
        // Skip parent constructor to avoid external dependencies.
    }

    public function put($message, $level)
    {
        // No-op for tests.
    }
}

class ImportTestDouble extends Import
{
    private array $mockResponses;
    private array $mockBinaryResponses;
    private array $mockHttpDownloads;
    public array $requests = [];
    public array $binaryRequests = [];
    public array $httpDownloads = [];

    public function __construct(array $mockResponses, array $mockBinaryResponses = [], array $mockHttpDownloads = [])
    {
        parent::__construct(new TestLog());
        $this->mockResponses = $mockResponses;
        $this->mockBinaryResponses = $mockBinaryResponses;
        $this->mockHttpDownloads = $mockHttpDownloads;
    }

    protected function lis_api_request($path, array $query = [])
    {
        $this->requests[] = ['path' => $path, 'query' => $query];
        return $this->mockResponses[$path] ?? [];
    }

    protected function lis_api_request_binary($path, array $query = [], string $accept = 'application/octet-stream')
    {
        $this->binaryRequests[] = ['path' => $path, 'query' => $query, 'accept' => $accept];
        return $this->mockBinaryResponses[$path] ?? false;
    }

    protected function performHttpDownload(string $url)
    {
        $this->httpDownloads[] = $url;
        return $this->mockHttpDownloads[$url] ?? false;
    }
}

$mockResponse = [
    '/LegislationText/api/getlegislationtextbyidasync' => [
        'ListItems' => [
            [
                'DraftText' => '<p>Current version text</p>',
                'TextFormat' => 'HTML',
                'TextFormatID' => 2,
                'DocumentCode' => 'HB1H1',
                'VersionDate' => '2024-02-01T12:00:00',
                'LegislationTextID' => 200,
            ],
            [
                'DraftText' => '<p>Older version text</p>',
                'TextFormat' => 'HTML',
                'TextFormatID' => 2,
                'DocumentCode' => 'HB1',
                'VersionDate' => '2024-01-01T12:00:00',
                'LegislationTextID' => 100,
            ],
            [
                'DraftText' => null,
                'TextFormat' => 'PDF',
                'TextFormatID' => 1,
                'DocumentCode' => 'HB1H1',
                'VersionDate' => '2024-02-01T12:30:00',
                'LegislationTextID' => 201,
                'PDFFile' => [
                    [
                        'FileURL' => 'https://example.com/mock.pdf',
                    ],
                ],
            ],
        ],
    ],
];

$mockBinaryResponses = [
    '/LegislationText/api/getdrafttextbylegislationtextidasync' => 'PDFDATA',
];

$mockHttpDownloads = [
    'https://example.com/mock.pdf' => [
        'body' => '{"TextsList":[{"DraftText":"<p>Not a PDF</p>"}]}',
        'content_type' => 'application/json',
        'status' => 200,
    ],
];

$import = new ImportTestDouble($mockResponse, $mockBinaryResponses, $mockHttpDownloads);
$import->bill_number = 'HB1';
$import->document_number = 'HB1H1';
$import->get_bill_text_api();

$expected = '<p>Current version text</p>';

if ($import->text !== $expected) {
    throw new RuntimeException('Expected DraftText not stored on Import::$text');
}

if ($import->document_number !== 'HB1H1') {
    throw new RuntimeException('Document code was not preserved after API call');
}

if (($import->requests[0]['query']['legislationNumber'] ?? null) !== 'HB1') {
    throw new RuntimeException('legislationNumber not passed to API');
}
if (empty($import->requests)) {
    throw new RuntimeException('Expected lis_api_request to be invoked');
}

$pdf = $import->get_bill_pdf_api();
if ($pdf !== 'PDFDATA') {
    throw new RuntimeException('PDF data not returned from get_bill_pdf_api');
}

if (($import->binaryRequests[0]['query']['legislationTextID'] ?? null) !== 201) {
    throw new RuntimeException('legislationTextID not passed when requesting PDF');
}

if ($import->pdf !== 'PDFDATA') {
    throw new RuntimeException('PDF content was not cached in Import::$pdf');
}

$expectedDownloads = ['https://example.com/mock.pdf'];
if ($import->httpDownloads !== $expectedDownloads) {
    throw new RuntimeException('Expected PDF FileURL to be attempted before falling back to binary endpoint');
}

$import->binaryRequests = [];
$tempFile = tempnam(sys_get_temp_dir(), 'bill-pdf-');
$resultPath = $import->get_bill_pdf_api($tempFile);
if ($resultPath !== $tempFile) {
    throw new RuntimeException('Destination path not returned when saving PDF');
}
if (!is_file($tempFile) || file_get_contents($tempFile) !== 'PDFDATA') {
    throw new RuntimeException('PDF file was not written as expected');
}
unlink($tempFile);

echo "get_bill_text_api_test: ok\n";
