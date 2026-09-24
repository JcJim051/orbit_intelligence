<?php

namespace Tests\Unit;

use App\Services\OpenData\DatosGovUrl;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatosGovUrlTest extends TestCase
{
    #[DataProvider('validUrls')]
    public function test_extracts_dataset_id_only_from_official_urls(string $url): void
    {
        $parsed = (new DatosGovUrl)->parse($url);

        $this->assertSame('abcd-1234', $parsed['dataset_id']);
        $this->assertSame('https://www.datos.gov.co/d/abcd-1234', $parsed['landing_page_url']);
    }

    public static function validUrls(): array
    {
        return [
            ['https://www.datos.gov.co/Agricultura/EVA/abcd-1234'],
            ['https://datos.gov.co/d/abcd-1234'],
            ['https://www.datos.gov.co/resource/abcd-1234.json?$limit=10'],
        ];
    }

    public function test_rejects_non_official_hosts_even_when_the_path_contains_an_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new DatosGovUrl)->parse('https://evil.example/abcd-1234');
    }
}
