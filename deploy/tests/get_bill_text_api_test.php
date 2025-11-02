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
    public array $requests = [];

    public function __construct(array $mockResponses)
    {
        parent::__construct(new TestLog());
        $this->mockResponses = $mockResponses;
    }

    protected function lis_api_request($path, array $query = [])
    {
        $this->requests[] = ['path' => $path, 'query' => $query];
        return $this->mockResponses[$path] ?? [];
    }
}

$mockResponse = [
    '/LegislationText/api/getlegislationtextbyidasync' => [
        'ListItems' => [
            [
                'DraftText' => '<p>Current version text</p>',
                'TextFormat' => 'HTML',
                'DocumentCode' => 'HB1H1',
                'VersionDate' => '2024-02-01T12:00:00',
                'LegislationTextID' => 200,
            ],
            [
                'DraftText' => '<p>Older version text</p>',
                'TextFormat' => 'HTML',
                'DocumentCode' => 'HB1',
                'VersionDate' => '2024-01-01T12:00:00',
                'LegislationTextID' => 100,
            ],
        ],
    ],
];

$import = new ImportTestDouble($mockResponse);
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

echo "get_bill_text_api_test: ok\n";
