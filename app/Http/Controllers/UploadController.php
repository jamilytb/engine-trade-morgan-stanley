<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function uploadArquivo(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:txt',
        ]);

        // Armazena o arquivo em storage/app/arquivos
        $request->file('arquivo')->store('arquivos');

        return 'Arquivo enviado com sucesso!';
    }
}
