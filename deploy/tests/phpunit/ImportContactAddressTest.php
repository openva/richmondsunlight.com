<?php

use PHPUnit\Framework\TestCase;

/**
 * The district office addresses that Import reads from LIS's contact API, in the form that
 * Legislator::get() breaks into lines: "street, city, ST ZIP".
 */
class ImportContactAddressTest extends TestCase
{
    private function districtAddress(array $office): ?string
    {
        $response = ['MemberContactInformationList' => [[
            'MemberNumber' => 'H0123',
            'ContactInformation' => [$office + [
                'ContactType' => 'Mailing',
                'Address1' => null,
                'Address2' => null,
                'Address3' => null,
                'City' => null,
                'StateCode' => null,
                'ZipCode' => null,
            ]],
        ]]];

        $method = new ReflectionMethod(Import::class, 'extract_contact_details_from_api');
        $details = $method->invoke(new Import(new Log()), $response, 'H0123');

        return $details['address_district'] ?? null;
    }

    public function testTheStateAndZipCodeAreSeparatedBySpaceNotAComma(): void
    {
        $this->assertSame('P.O. Box 257, Keswick, VA 22947', $this->districtAddress([
            'Address1' => 'P.O. Box 257', 'City' => 'Keswick', 'StateCode' => 'VA', 'ZipCode' => '22947',
        ]));
    }

    public function testEachStreetLineIsKept(): void
    {
        $this->assertSame('732 Eden Way North, Suite E,#183, Chesapeake, VA 23320', $this->districtAddress([
            'Address1' => '732 Eden Way North', 'Address2' => 'Suite E,#183',
            'City' => 'Chesapeake', 'StateCode' => 'VA', 'ZipCode' => '23320',
        ]));
    }

    /**
     * As the API lists some offices: empty strings but for the state.
     */
    public function testAnOfficeWithNoStreetOrCityHasNoAddress(): void
    {
        $this->assertNull($this->districtAddress([
            'Address1' => '', 'City' => '', 'StateCode' => 'VA', 'ZipCode' => '',
        ]));
    }

    public function testAnAddressWithoutAZipCodeEndsWithTheState(): void
    {
        $this->assertSame('100 Main Street, Keswick, VA', $this->districtAddress([
            'Address1' => '100 Main Street', 'City' => 'Keswick', 'StateCode' => 'VA',
        ]));
    }

    /**
     * Legislator::get() puts a line break before the city, splitting on the last two commas, as
     * it does here. (The extra space is HTML's to collapse.)
     */
    public function testTheWebsiteBreaksTheAddressBeforeTheCity(): void
    {
        $address = $this->districtAddress([
            'Address1' => 'P.O. Box 257', 'City' => 'Keswick', 'StateCode' => 'VA', 'ZipCode' => '22947',
        ]);

        $this->assertSame(
            'P.O. Box 257<br /> Keswick,  VA 22947',
            preg_replace('/^(.*),(.*),(.*)$/D', '\\1<br />\\2, \\3', $address)
        );
    }
}
