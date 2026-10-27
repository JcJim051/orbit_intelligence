<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

class GeneradorFichaTecnicaIndicador
{
    private const WORD_NAMESPACE = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @var array<int, string> */
    private const ROW_FIELDS = [
        0 => 'codigo',
        1 => 'nombre',
        2 => 'convencion',
        3 => 'definicion',
        4 => 'fuente',
        5 => 'responsable',
        6 => 'formula',
        7 => 'unidad',
        8 => 'periodicidad',
        9 => 'cobertura',
        10 => 'periodo_inicial',
        11 => 'observaciones',
        12 => 'limitaciones',
        14 => 'aprobador',
        15 => 'dependencia_validacion',
        16 => 'correo_validacion',
    ];

    public function generate(IndicadorTerritorial $indicador): string
    {
        $template = resource_path('templates/indicadores-territoriales/ficha-tecnica-indicador.docx');
        if (! is_file($template)) {
            throw new RuntimeException('No se encontró la plantilla institucional F-PE-12.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión ZIP de PHP es necesaria para generar la ficha técnica.');
        }

        $indicador->loadMissing(['aprobador:id,name,email', 'datos']);
        $output = tempnam(sys_get_temp_dir(), 'siid-ficha-indicador-');
        if ($output === false || ! copy($template, $output)) {
            throw new RuntimeException('No fue posible preparar el documento de la ficha técnica.');
        }

        $zip = new ZipArchive;
        if ($zip->open($output) !== true) {
            @unlink($output);
            throw new RuntimeException('No fue posible abrir la plantilla de la ficha técnica.');
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
            if (! is_string($xml)) {
                throw new RuntimeException('La plantilla no contiene el documento principal.');
            }

            $document = new DOMDocument('1.0', 'UTF-8');
            $document->preserveWhiteSpace = true;
            if (! $document->loadXML($xml, LIBXML_NONET)) {
                throw new RuntimeException('La estructura de la plantilla no es válida.');
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', self::WORD_NAMESPACE);
            $table = $xpath->query('/w:document/w:body/w:tbl[1]')->item(0);
            if (! $table instanceof DOMElement) {
                throw new RuntimeException('No se encontró la tabla principal de la ficha técnica.');
            }

            $rows = $xpath->query('./w:tr', $table);
            $values = $this->values($indicador);
            foreach (self::ROW_FIELDS as $rowIndex => $field) {
                $row = $rows->item($rowIndex);
                $cell = $row instanceof DOMElement ? $xpath->query('./w:tc', $row)->item(1) : null;
                if (! $cell instanceof DOMElement) {
                    throw new RuntimeException("La fila {$rowIndex} de la plantilla no tiene la estructura esperada.");
                }

                $this->replaceCellText($document, $xpath, $cell, $values[$field] ?? null);
            }

            $updated = $document->saveXML();
            if (! is_string($updated) || ! $zip->addFromString('word/document.xml', $updated)) {
                throw new RuntimeException('No fue posible escribir los datos en la ficha técnica.');
            }
        } finally {
            $zip->close();
        }

        return $output;
    }

    /** @return array<string, string|null> */
    private function values(IndicadorTerritorial $indicador): array
    {
        $primerPeriodo = $indicador->linea_base_periodo
            ?: $indicador->datos->where('estado', 'aprobado')->first()?->periodo;

        return [
            'codigo' => $indicador->codigo,
            'nombre' => $indicador->nombre,
            'convencion' => $indicador->convencion,
            'definicion' => $indicador->definicion,
            'fuente' => $indicador->fuente_informacion,
            'responsable' => $indicador->responsable_informacion,
            'formula' => $indicador->formula_calculo,
            'unidad' => $indicador->unidad_medida,
            'periodicidad' => $indicador->periodicidad_original
                ?: IndicadorTerritorial::PERIODICIDADES[$indicador->periodicidad] ?? null,
            'cobertura' => $indicador->cobertura_geografica,
            'periodo_inicial' => $primerPeriodo,
            'observaciones' => $indicador->observaciones_tecnicas,
            'limitaciones' => $indicador->limitaciones,
            'aprobador' => $indicador->aprobador?->name,
            'dependencia_validacion' => (string) config('app.indicator_validation_department', 'Gerencia de Información y Estudios Económicos'),
            'correo_validacion' => $indicador->aprobador?->email,
        ];
    }

    private function replaceCellText(DOMDocument $document, DOMXPath $xpath, DOMElement $cell, ?string $value): void
    {
        $sourceParagraph = $xpath->query('./w:p[1]', $cell)->item(0);
        $paragraphProperties = $sourceParagraph instanceof DOMElement
            ? $xpath->query('./w:pPr[1]', $sourceParagraph)->item(0)?->cloneNode(true)
            : null;
        $runProperties = $sourceParagraph instanceof DOMElement
            ? $xpath->query('.//w:rPr[1]', $sourceParagraph)->item(0)?->cloneNode(true)
            : null;
        $cellProperties = $xpath->query('./w:tcPr[1]', $cell)->item(0);

        foreach (iterator_to_array($cell->childNodes) as $child) {
            if ($child !== $cellProperties) {
                $cell->removeChild($child);
            }
        }

        $paragraph = $document->createElementNS(self::WORD_NAMESPACE, 'w:p');
        if ($paragraphProperties) {
            $paragraph->appendChild($document->importNode($paragraphProperties, true));
        }

        $lines = preg_split('/\R/u', trim((string) ($value ?: 'No informado'))) ?: ['No informado'];
        foreach ($lines as $index => $line) {
            $run = $document->createElementNS(self::WORD_NAMESPACE, 'w:r');
            if ($runProperties) {
                $run->appendChild($document->importNode($runProperties, true));
            }
            if ($index > 0) {
                $run->appendChild($document->createElementNS(self::WORD_NAMESPACE, 'w:br'));
            }
            $text = $document->createElementNS(self::WORD_NAMESPACE, 'w:t');
            $text->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $text->appendChild($document->createTextNode($line));
            $run->appendChild($text);
            $paragraph->appendChild($run);
        }

        $cell->appendChild($paragraph);
    }
}
