<?php

namespace App\Filament\Resources\AnalisisAreaResource\Pages;

use App\Filament\Resources\AnalisisAreaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAnalisisArea extends ViewRecord
{
    protected static string $resource = AnalisisAreaResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->redirect(AnalisisAreaResource::getUrl('index', [
            'analisis' => $this->getRecord()->getKey(),
        ]));
    }
}
