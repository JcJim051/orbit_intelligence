<?php

namespace App\Services\Intelligence\Catalogs;

class CatalogField
{
    /**
     * @param  array<string, string>|null  $options
     * @param  list<string>  $extraRules
     */
    public function __construct(
        public string $column,
        public string $label,
        public string $input,
        public bool $required = false,
        public bool $naturalKey = false,
        public bool $listed = true,
        public bool $onForm = true,
        public bool $onExcel = true,
        public ?int $maxLength = 255,
        public ?int $minNumber = null,
        public ?int $maxNumber = null,
        public ?string $excelHeader = null,
        public mixed $example = null,
        public ?string $hint = null,
        public ?array $options = null,
        public array $extraRules = [],
        public ?string $lookupModel = null,
        public string $lookupColumn = 'codigo',
        public ?string $lookupRelation = null,
        public string $lookupDisplay = 'nombre',
        public ?string $dependsOn = null,
        public ?string $parentAttribute = null,
        public bool $unique = false,
    ) {}

    public function excelHeader(): string
    {
        return $this->excelHeader ?? $this->column;
    }

    public function formKey(): string
    {
        return $this->input === 'dependencia' ? 'dependencia_id' : $this->column;
    }
}
