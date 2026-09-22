<?php

use App\Modules\Candidate\Interfaces\Http\Controllers\CreateCandidateController;
use App\Modules\Candidate\Interfaces\Http\Controllers\DeleteCandidateDocumentController;
use App\Modules\Candidate\Interfaces\Http\Controllers\DownloadCandidateDocumentController;
use App\Modules\Candidate\Interfaces\Http\Controllers\ListCandidateDocumentsController;
use App\Modules\Candidate\Interfaces\Http\Controllers\ListCandidatesController;
use App\Modules\Candidate\Interfaces\Http\Controllers\ShowCandidateController;
use App\Modules\Candidate\Interfaces\Http\Controllers\UpdateCandidateController;
use App\Modules\Candidate\Interfaces\Http\Controllers\UploadCandidateDocumentController;
use App\Modules\Organisation\Interfaces\Http\Middleware\ResolveTenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', ResolveTenantContext::class])
    ->prefix('/organisations/{organisation}')
    ->whereNumber('organisation')
    ->group(function (): void {
        Route::get('/candidates', ListCandidatesController::class)
            ->name('api.v1.candidates.index');
        Route::post('/candidates', CreateCandidateController::class)
            ->name('api.v1.candidates.store');
        Route::get('/candidates/{candidate}', ShowCandidateController::class)
            ->name('api.v1.candidates.show')
            ->whereNumber('candidate');
        Route::patch('/candidates/{candidate}', UpdateCandidateController::class)
            ->name('api.v1.candidates.update')
            ->whereNumber('candidate');
        Route::get('/candidates/{candidate}/documents', ListCandidateDocumentsController::class)
            ->name('api.v1.candidate-documents.index')
            ->whereNumber('candidate');
        Route::post('/candidates/{candidate}/documents', UploadCandidateDocumentController::class)
            ->name('api.v1.candidate-documents.store')
            ->whereNumber('candidate');
        Route::get('/candidates/{candidate}/documents/{document}/download', DownloadCandidateDocumentController::class)
            ->name('api.v1.candidate-documents.download')
            ->whereNumber(['candidate', 'document']);
        Route::delete('/candidates/{candidate}/documents/{document}', DeleteCandidateDocumentController::class)
            ->name('api.v1.candidate-documents.destroy')
            ->whereNumber(['candidate', 'document']);
    });
