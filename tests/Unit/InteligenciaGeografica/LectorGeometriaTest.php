<?php

namespace Tests\Unit\InteligenciaGeografica;

use App\Services\InteligenciaGeografica\LectorGeometria;
use PHPUnit\Framework\TestCase;

class LectorGeometriaTest extends TestCase
{
    public function test_kml_and_geojson_become_a_polygon(): void
    {
        $lector = new LectorGeometria;
        $kml = <<<'KML'
            <kml><Document><Placemark><Polygon><outerBoundaryIs><LinearRing>
            <coordinates>-73.6,4.1,0 -73.5,4.1,0 -73.5,4.2,0 -73.6,4.1,0</coordinates>
            </LinearRing></outerBoundaryIs></Polygon></Placemark></Document></kml>
        KML;

        $desdeKml = json_decode($lector->desdeKml($kml), true);
        $desdeGeoJson = json_decode($lector->desdeGeoJson(json_encode([
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[-73.6, 4.1], [-73.5, 4.1], [-73.5, 4.2], [-73.6, 4.1]]],
            ],
        ])), true);

        $this->assertSame('Polygon', $desdeKml['type']);
        $this->assertSame([-73.6, 4.1], $desdeKml['coordinates'][0][0]);
        $this->assertSame('Polygon', $desdeGeoJson['type']);
        $lector->afirmarCoordenadas(json_encode($desdeGeoJson), 4326);
    }

    public function test_projected_coordinates_are_rejected_as_geographic(): void
    {
        $lector = new LectorGeometria;

        $this->expectException(\InvalidArgumentException::class);
        $lector->afirmarCoordenadas(json_encode([
            'type' => 'Polygon',
            'coordinates' => [[[4916000, 1998000], [4916100, 1998000], [4916100, 1998100], [4916000, 1998100], [4916000, 1998000]]],
        ]), 4326);
    }
}
