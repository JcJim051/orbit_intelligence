<?php

namespace App\Filament\Resources;

use App\Enums\BankRequestStatus;
use App\Filament\Resources\BankRequestResource\Pages\ListBankRequests;
use App\Filament\Resources\BankRequestResource\Pages\ViewBankRequest;
use App\Models\BankRequest;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BankRequestResource extends Resource
{
    protected static ?string $model = BankRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Bancos de proyectos';

    protected static ?string $navigationLabel = 'Solicitudes';

    protected static ?string $modelLabel = 'solicitud de banco';

    protected static ?string $pluralModelLabel = 'Solicitudes de banco';

    protected static ?string $slug = 'solicitudes-banco';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->puedeRevisarBancos() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('dependencia');
        $user = auth()->user();

        if ($user instanceof User && $user->isBankReviewer()) {
            $ids = $user->dependenciaIdsAsignadas();
            $query->whereIn('dependencia_id', $ids === [] ? [0] : $ids);
        }

        return $query;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            ViewEntry::make('expediente')->view('filament.bancos.expediente')->columnSpanFull()
                ->viewData(fn (BankRequest $record): array => ['solicitud' => $record]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_seguimiento')->label('Seguimiento')->searchable()->sortable(),
                TextColumn::make('dependencia.nombre')->label('Dependencia')->placeholder('Sin asignar')->searchable(),
                TextColumn::make('official_name')->label('Funcionario')->searchable(),
                TextColumn::make('subject')->label('Asunto')->limit(40)->searchable(),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn (BankRequestStatus $state): string => $state->label())->color(fn (BankRequestStatus $state): string => match ($state) {
                    BankRequestStatus::Aceptado => 'success',
                    BankRequestStatus::EnProceso => 'warning',
                    BankRequestStatus::Devuelto => 'danger',
                    BankRequestStatus::Recibido => 'info',
                }),
                TextColumn::make('created_at')->label('Radicada')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(collect(BankRequestStatus::cases())->mapWithKeys(
                    fn (BankRequestStatus $estado): array => [$estado->value => $estado->label()],
                )->all()),
            ])
            ->recordActions([
                ViewAction::make()->label('Abrir'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankRequests::route('/'),
            'view' => ViewBankRequest::route('/{record}'),
        ];
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record instanceof BankRequest ? $record->numero_seguimiento : null;
    }
}
