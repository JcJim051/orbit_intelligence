<?php

namespace App\Services\OpenData;

use InvalidArgumentException;

class DatosGovUrl
{
    /** @return array{dataset_id:string,landing_page_url:string} */
    public function parse(string $url): array
    {
        $parts = parse_url(trim($url));
        $host = mb_strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? null) !== 'https' || ! in_array($host, ['datos.gov.co', 'www.datos.gov.co'], true)) {
            throw new InvalidArgumentException('Use una dirección HTTPS oficial de Datos.gov.co.');
        }

        $candidate = (string) ($parts['path'] ?? '').' '.(string) ($parts['query'] ?? '');
        if (! preg_match('/(?<![a-z0-9])([a-z0-9]{4}-[a-z0-9]{4})(?![a-z0-9])/i', $candidate, $matches)) {
            throw new InvalidArgumentException('No fue posible identificar el código Socrata del conjunto.');
        }

        $datasetId = mb_strtolower($matches[1]);

        return [
            'dataset_id' => $datasetId,
            'landing_page_url' => "https://www.datos.gov.co/d/{$datasetId}",
        ];
    }
}
