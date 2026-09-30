<?php

namespace App\Services\Intelligence;

class CatalogImportResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public bool $applied,
        public int $created = 0,
        public int $updated = 0,
        public array $errors = [],
    ) {}

    /**
     * @param  list<string>  $errors
     */
    public static function failed(array $errors): self
    {
        return new self(false, errors: $errors);
    }

    public function message(): string
    {
        if ($this->created === 0 && $this->updated === 0) {
            return 'El archivo no trae filas para importar.';
        }

        return 'Se importaron '.($this->created + $this->updated).' filas: '.$this->created.' nuevas y '.$this->updated.' actualizadas.';
    }
}
