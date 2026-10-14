<?php

namespace App\Enums;

enum OrigenGeometriaAnalisis: string
{
    case Dibujo = 'dibujo';
    case Kmz = 'kmz';
    case Kml = 'kml';
    case GeoJson = 'geojson';
    case Shapefile = 'shapefile';

    public function label(): string
    {
        return match ($this) {
            self::Dibujo => 'Polígono dibujado',
            self::Kmz => 'KMZ cargado',
            self::Kml => 'KML cargado',
            self::GeoJson => 'GeoJSON cargado',
            self::Shapefile => 'Shapefile cargado',
        };
    }
}
