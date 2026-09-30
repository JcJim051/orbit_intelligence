<?php

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\PlanLabel;
use PHPUnit\Framework\TestCase;

class PlanLabelTest extends TestCase
{
    public function test_it_splits_pilar_dotted_and_trailing_dot_labels(): void
    {
        $this->assertSame(
            ['numeral' => '1', 'nombre' => 'SEGURIDAD TOTAL Y DERECHOS HUMANOS EN EL META'],
            PlanLabel::split('PILAR 1. SEGURIDAD TOTAL Y DERECHOS HUMANOS EN EL META'),
        );
        $this->assertSame(
            ['numeral' => '1.1', 'nombre' => 'EJE ESTRATÉGICO CIUDADANÍA SEGURA'],
            PlanLabel::split('1.1 EJE ESTRATÉGICO CIUDADANÍA  SEGURA'),
        );
        $this->assertSame(
            ['numeral' => '1.1.2.2', 'nombre' => 'PROGRAMA SISTEMA DEPARTAMENTAL DE DDHH Y DIH DEL META'],
            PlanLabel::split('1.1.2.2. PROGRAMA SISTEMA DEPARTAMENTAL DE DDHH Y DIH DEL META'),
        );
        $this->assertSame(
            ['numeral' => '1.1.2.3.2', 'nombre' => 'Subprograma Capacidades para la paz'],
            PlanLabel::split('1.1.2.3.2. Subprograma Capacidades para la paz'),
        );
        $this->assertSame(
            ['numeral' => null, 'nombre' => 'Adoptar e implementar el programa departamental de pago por servicios ambientales'],
            PlanLabel::split('Adoptar e implementar el programa departamental de pago por servicios ambientales'),
        );
        $this->assertSame('Gobierno Territorial', PlanLabel::sectorName('Sector 45 - Gobierno Territorial'));
        $this->assertSame('Información Estadística', PlanLabel::sectorName('Sector 04 - Información Estadística'));
    }
}
