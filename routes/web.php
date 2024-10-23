<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/upload-arquivo', [UploadController::class, 'uploadArquivo'])->name('uploadArquivo');

