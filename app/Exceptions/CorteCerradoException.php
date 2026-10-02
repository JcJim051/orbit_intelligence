<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Se lanza cuando se intenta crear, modificar o borrar información de un seguimiento cerrado.
 */
class CorteCerradoException extends RuntimeException
{
    public function __construct(string $message = 'El seguimiento está cerrado: su información quedó congelada y no se puede modificar.')
    {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 423);
        }

        return back()->withErrors(['seguimiento' => $this->getMessage()]);
    }
}
