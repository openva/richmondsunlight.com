<?php

use PHPUnit\Framework\TestCase;

// Test doubles (PHP doesn't support inner classes, so these live at file scope)

class TestLog extends Log
{
    public function __construct()
    {
        // Skip parent constructor to avoid external dependencies.
    }

    public function put($message, $level = 3)
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

class GetBillTextApiTest extends TestCase
{
    private function createImport(): ImportTestDouble
    {
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
                            ['FileURL' => 'https://example.com/mock.pdf'],
                        ],
                    ],
                ],
            ],
        ];

        $mockBinaryResponses = [
            '/LegislationText/api/getdrafttextbylegislationtextidasync' => "%PDF-FAKE\n%%EOF",
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
        return $import;
    }

    // --- get_bill_text_api() ---

    public function testGetBillTextApiStoresDraftText(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $this->assertSame('<p>Current version text</p>', $import->text, 'Expected DraftText not stored on Import::$text');
    }

    public function testGetBillTextApiPreservesDocumentCode(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $this->assertSame('HB1H1', $import->document_number, 'Document code was not preserved after API call');
    }

    public function testGetBillTextApiPassesLegislationNumber(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $this->assertSame('HB1', $import->requests[0]['query']['legislationNumber'] ?? null, 'legislationNumber not passed to API');
    }

    public function testGetBillTextApiInvokesLisApiRequest(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $this->assertNotEmpty($import->requests, 'Expected lis_api_request to be invoked');
    }

    // --- get_bill_pdf_api() ---

    public function testGetBillPdfApiReturnsPdfData(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $this->assertSame("%PDF-FAKE\n%%EOF", $import->get_bill_pdf_api(), 'PDF data not returned from get_bill_pdf_api');
    }

    public function testGetBillPdfApiPassesLegislationTextId(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $import->get_bill_pdf_api();
        $this->assertSame(201, $import->binaryRequests[0]['query']['legislationTextID'] ?? null, 'legislationTextID not passed when requesting PDF');
    }

    public function testGetBillPdfApiCachesPdf(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $import->get_bill_pdf_api();
        $this->assertSame("%PDF-FAKE\n%%EOF", $import->pdf, 'PDF content was not cached in Import::$pdf');
    }

    public function testGetBillPdfApiAttemptsPdfFileUrlFirst(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $import->get_bill_pdf_api();
        $this->assertSame(['https://example.com/mock.pdf'], $import->httpDownloads, 'Expected PDF FileURL to be attempted before falling back to binary endpoint');
    }

    // --- get_bill_pdf_api() with destination path ---

    public function testGetBillPdfApiWithDestinationPathReturnsPath(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $tempFile = tempnam(sys_get_temp_dir(), 'bill-pdf-');
        try {
            $resultPath = $import->get_bill_pdf_api($tempFile);
            $this->assertSame($tempFile, $resultPath, 'Destination path not returned when saving PDF');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testGetBillPdfApiWithDestinationPathSavesFile(): void
    {
        $import = $this->createImport();
        $import->get_bill_text_api();
        $tempFile = tempnam(sys_get_temp_dir(), 'bill-pdf-');
        try {
            $import->get_bill_pdf_api($tempFile);
            $this->assertFileExists($tempFile);
            $this->assertSame("%PDF-FAKE\n%%EOF", file_get_contents($tempFile), 'PDF file was not written as expected');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
