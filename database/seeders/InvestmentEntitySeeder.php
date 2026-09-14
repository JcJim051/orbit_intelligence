<?php

namespace Database\Seeders;

use App\Models\InvestmentEntity;
use Illuminate\Database\Seeder;

class InvestmentEntitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $source = 'https://meta.gov.co/mapa-del-sitio';
        $entities = [
            ['aim', 'Agencia para la Infraestructura del Meta', 'AIM', ['Agencia para la Infraestructura del Meta', 'AIM']],
            ['casa-cultura', 'Casa de la Cultura del Meta', null, ['Casa de la Cultura del Meta', 'Casa de la Cultura']],
            ['edesa', 'Empresa de Servicios Públicos del Meta', 'EDESA', ['Empresa de Servicios Públicos del Meta', 'EDESA', 'EDESA S.A. E.S.P.']],
            ['idermeta', 'Instituto de Deporte y Recreación del Meta', 'IDERMETA', ['Instituto de Deporte y Recreación del Meta', 'Instituto del Deporte y Recreación del Meta', 'IDERMETA']],
            ['instituto-cultura', 'Instituto de Cultura del Meta', null, ['Instituto de Cultura del Meta', 'Instituto Departamental de Cultura del Meta']],
            ['instituto-turismo', 'Instituto de Turismo del Meta', null, ['Instituto de Turismo del Meta', 'Instituto Departamental de Turismo del Meta']],
            ['transito-transporte', 'Instituto Departamental de Tránsito y Transporte del Meta', 'IDTTM', ['Instituto Departamental de Tránsito y Transporte del Meta', 'Instituto de Tránsito y Transporte del Meta', 'IDTTM']],
            ['iraca', 'Institución Educativa Iracá', 'IRACÁ', ['Institución Educativa Iracá', 'Instituto Iracá', 'Iracá']],
            ['loteria-meta', 'Lotería del Meta', null, ['Lotería del Meta']],
            ['unidad-licores', 'Unidad de Licores del Meta', 'ULM', ['Unidad de Licores del Meta']],
        ];

        foreach ($entities as $index => [$slug, $name, $acronym, $aliases]) {
            InvestmentEntity::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'acronym' => $acronym, 'aliases' => $aliases, 'source_url' => $source, 'active' => true, 'sort_order' => $index + 1]
            );
        }
    }
}
