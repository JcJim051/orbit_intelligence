<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Siid\ComplementaryMetadataParser;
use App\Services\Siid\ListadoMaestroParser;
use App\Services\Siid\TableroEmbedValidator;
use Tests\TestCase;

class SiidCatalogSyncTest extends TestCase
{
    public function test_parser_finds_dynamic_header_and_accepts_sheet_aliases(): void
    {
        $result = app(ListadoMaestroParser::class)->parse('Economicos LOOKER', [
            ['Listado maestro de indicadores económicos'],
            [],
            ['indicador', 'Descripción del indicador', 'URL_indicador', 'Area_tematica', 'Ficha', 'url ficha', 'Enlace de la fuente', 'Enlace o UUID del tablero', 'Tipo_tablero'],
            ['Producto interno bruto', 'Valor total de la producción económica.', 'https://docs.google.com/data', 'Economía', ' IE- 001 ', 'https://docs.google.com/ficha', 'https://www.dane.gov.co/fuente', '123e4567-e89b-42d3-a456-426614174000', 'Superset'],
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('IE- 001', $result['rows'][0]['codigo']);
        $this->assertSame('Producto interno bruto', $result['rows'][0]['nombre']);
        $this->assertSame('Valor total de la producción económica.', $result['rows'][0]['descripcion']);
        $this->assertSame('https://docs.google.com/data', $result['rows'][0]['url_datos']);
        $this->assertSame('https://docs.google.com/ficha', $result['rows'][0]['url_ficha']);
        $this->assertSame('https://www.dane.gov.co/fuente', $result['rows'][0]['url_fuente']);
        $this->assertSame('123e4567-e89b-42d3-a456-426614174000', $result['rows'][0]['url_tablero']);
    }

    public function test_looker_and_superset_references_are_restricted(): void
    {
        config(['siid.looker.allowed_hosts' => ['lookerstudio.google.com', 'datastudio.google.com']]);
        $validator = app(TableroEmbedValidator::class);

        $this->assertTrue($validator->looker('https://lookerstudio.google.com/embed/reporting/abc/page/p_1'));
        $this->assertTrue($validator->looker('https://datastudio.google.com/embed/reporting/abc/page/p_1'));
        $this->assertFalse($validator->looker('https://lookerstudio.google.com/reporting/abc'));
        $this->assertFalse($validator->looker('https://example.com/embed/reporting/abc'));
        $this->assertTrue($validator->superset('123e4567-e89b-42d3-a456-426614174000'));
        $this->assertFalse($validator->superset('dashboard-1'));
    }

    public function test_webmaster_only_manages_publication(): void
    {
        $webmaster = new User(['role' => UserRole::Webmaster, 'active' => true]);

        $this->assertTrue($webmaster->canViewSiidCatalog());
        $this->assertTrue($webmaster->canManageSiidPublication());
        $this->assertFalse($webmaster->canSynchronizeSiidCatalog());
        $this->assertFalse($webmaster->canCoordinateTerritorialIndicators());
    }

    public function test_complementary_tabs_enrich_catalog_without_overwriting_main_values(): void
    {
        $records = [[
            'codigo' => 'ID-01',
            'nombre' => 'Población Total',
            'fuente' => null,
            'descripcion' => 'Descripción principal',
        ]];
        $sheets = [
            'INDICE SOCIODEMOGRAFICO' => [
                ['ÁREA TEMÁTICA', 'INDICADOR', 'FICHA TÉCNICA INDICADOR', 'ICET', 'ODS', 'PET', 'AÑO ACTUALIZACION', 'FUENTES'],
                ['Demografía', 'Población Total', 'ID-01', 'SI', 'NO', 'NO', '2026*', 'DANE - Proyecciones de población'],
            ],
            'ODS' => [
                ['Codigo del ODS', 'Nombre del ODS', 'Codigo del Indicador', 'Nombre del Indicador', 'Explicación del Indicador', 'Meta del ODS', 'Ficha Técnica del Indicador', 'Frecuencia', 'Unidad de Medida', 'Fuente de Informacion', 'Responsable del Reporte', 'Indicador SIID', 'Eje', 'Publicado', 'Link Publicacion SIID', 'Ficha'],
                ['1', 'Fin de la pobreza', '1_3_1_C', 'Indicador ODS', 'Explicación ODS', 'Meta ODS', '', 'Anual', 'Número', 'Fuente ODS', '', 'Población Total', '', 'Si', '', 'ID-01'],
            ],
        ];

        $result = app(ComplementaryMetadataParser::class)->enrich($records, $sheets)[0];

        $this->assertSame('DANE - Proyecciones de población', $result['fuente']);
        $this->assertSame('2026*', $result['anio_actualizacion']);
        $this->assertSame('SI', $result['icet']);
        $this->assertSame('Descripción principal', $result['descripcion']);
        $this->assertSame('Anual', $result['periodicidad']);
        $this->assertSame('Número', $result['unidad_medida']);
        $this->assertSame('1_3_1_C', $result['ods_relaciones'][0]['codigo']);
    }
}
