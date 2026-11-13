<?php

namespace App\Filament\Resources\IndicadorPublicos\Pages;

use App\Filament\Resources\IndicadorPublicos\IndicadorPublicoResource;
use App\Models\MicrositioIndicador;
use App\Models\User;
use App\Services\Indicators\IndicatorSeriesManager;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManageIndicatorSeries extends Page
{
    use InteractsWithRecord;
    use WithFileUploads;

    protected static string $resource = IndicadorPublicoResource::class;

    protected string $view = 'filament.resources.indicador-publicos.pages.manage-indicator-series';

    /** @var list<array<string, mixed>> */
    public array $columns = [];

    public mixed $seriesFile = null;

    public function mount(int|string $record, IndicatorSeriesManager $manager): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);

        /** @var MicrositioIndicador $indicator */
        $indicator = $this->getRecord();
        $this->columns = $manager->schema($indicator);
        if ($this->columns === []) {
            $this->columns = [
                ['key' => 'periodo', 'label' => 'Periodo', 'type' => 'date', 'required' => true],
                ['key' => 'valor', 'label' => 'Valor', 'type' => 'decimal', 'required' => true],
            ];
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Constructor de serie de datos';
    }

    public function addColumn(): void
    {
        $this->columns[] = ['key' => '', 'label' => '', 'type' => 'text', 'required' => false];
    }

    public function removeColumn(int $index): void
    {
        unset($this->columns[$index]);
        $this->columns = array_values($this->columns);
    }

    public function saveSchema(IndicatorSeriesManager $manager): void
    {
        /** @var MicrositioIndicador $indicator */
        $indicator = $this->getRecord();
        /** @var User $user */
        $user = auth()->user();
        $manager->saveSchema($indicator, $this->columns, $user);
        $indicator->unsetRelation('dataSource');
        $this->columns = $manager->schema($indicator);

        Notification::make()->success()->title('Estructura guardada')->body('La plantilla ya está disponible para descargar.')->send();
    }

    public function uploadSeries(IndicatorSeriesManager $manager): void
    {
        $this->validate(['seriesFile' => ['required', 'file', 'mimes:csv,xlsx', 'max:20480']]);
        /** @var MicrositioIndicador $indicator */
        $indicator = $this->getRecord();
        /** @var User $user */
        $user = auth()->user();
        $version = $manager->import($indicator, $this->seriesFile, $user);
        $this->reset('seriesFile');
        $indicator->unsetRelation('dataSource');

        Notification::make()->success()->title('Serie cargada')->body("Versión {$version->version}: {$version->row_count} registros validados.")->send();
    }

    public function downloadTemplate(IndicatorSeriesManager $manager): BinaryFileResponse
    {
        /** @var MicrositioIndicador $indicator */
        $indicator = $this->getRecord();
        $path = $manager->template($indicator);

        return response()->download($path, 'plantilla-serie-'.$indicator->codigo.'.xlsx')->deleteFileAfterSend(true);
    }
}
