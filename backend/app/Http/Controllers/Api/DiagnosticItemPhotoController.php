<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DiagnosticException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDiagnosticItemPhotoRequest;
use App\Http\Requests\UploadDiagnosticItemPhotosRequest;
use App\Http\Resources\DiagnosticItemPhotoResource;
use App\Models\DiagnosticItem;
use App\Models\DiagnosticItemPhoto;
use App\Services\DiagnosticService;
use App\Services\PhotoFileService;
use App\Traits\ApiResponse;
use App\Traits\FileResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DiagnosticItemPhotoController extends Controller implements HasMiddleware
{
    use ApiResponse, FileResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:diagnostic-item-photos.index', only: ['index', 'file']),
            new Middleware('permission:diagnostic-item-photos.store', only: ['store']),
            new Middleware('permission:diagnostic-item-photos.update', only: ['update']),
            new Middleware('permission:diagnostic-item-photos.destroy', only: ['destroy']),
        ];
    }

    public function index(DiagnosticItem $diagnosticItem)
    {

        $photos = $diagnosticItem
            ->photos()
            ->latest()
            ->get();

        return $this->successResponse(
            'Fotos obtenidas correctamente.',
            DiagnosticItemPhotoResource::collection($photos)
        );
    }

    /** Archivo de la foto, en línea (P8, paso 2, historial 2.106). */
    public function file(DiagnosticItem $diagnosticItem, DiagnosticItemPhoto $photo, PhotoFileService $photoFileService)
    {
        return $this->privateImageResponse($photoFileService->image(
            $photo->path,
            new DiagnosticException('La foto no tiene un archivo disponible.', 404, 'DIAGNOSTIC_ITEM_PHOTO_FILE_NOT_FOUND')
        ));
    }

    public function store(UploadDiagnosticItemPhotosRequest $request, DiagnosticItem $diagnosticItem, DiagnosticService $diagnosticService)
    {
        $diagnosticService->ensureItemsEditable($diagnosticItem->diagnostic);

        $data = $request->validated();

        $photos = [];
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $data,
                $diagnosticItem,
                &$photos,
                &$storedPaths
            ) {
                foreach ($data['photos'] as $photoData) {
                    $file = $photoData['file'];

                    $path = $file->store(
                        "diagnostic-items/{$diagnosticItem->id}",
                        PhotoFileService::DISK
                    );

                    $storedPaths[] = $path;

                    $photos[] = DiagnosticItemPhoto::create([
                        'diagnostic_item_id' => $diagnosticItem->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'description' => $photoData['description'] ?? null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk(PhotoFileService::DISK)->delete($path);
            }

            throw $e;
        }

        return $this->successResponse(
            'Fotos cargadas correctamente.',
            DiagnosticItemPhotoResource::collection(
                collect($photos)
            ),
            201
        );
    }

    public function update(UpdateDiagnosticItemPhotoRequest $request, DiagnosticItem $diagnosticItem, DiagnosticItemPhoto $photo, DiagnosticService $diagnosticService)
    {
        $diagnosticService->ensureItemsEditable($diagnosticItem->diagnostic);

        $data = $request->validated();

        if (
            ! array_key_exists('file', $data) &&
            ! array_key_exists('description', $data)
        ) {
            return $this->errorResponse(
                'No se proporcionaron datos para actualizar.',
                422,
                'DIAGNOSTIC_ITEM_PHOTO_NOTHING_TO_UPDATE'
            );
        }

        $oldPath = $photo->path;
        $newPath = null;

        try {
            DB::transaction(function () use (
                $data,
                $photo,
                $diagnosticItem,
                &$newPath
            ) {
                if (array_key_exists('file', $data)) {
                    $file = $data['file'];

                    $newPath = $file->store(
                        "diagnostic-items/{$diagnosticItem->id}",
                        PhotoFileService::DISK
                    );

                    $photo->path = $newPath;
                    $photo->original_name =
                        $file->getClientOriginalName();
                }

                if (array_key_exists('description', $data)) {
                    $photo->description =
                        $data['description'];
                }

                $photo->save();
            });

        } catch (\Throwable $e) {

            if ($newPath) {
                Storage::disk(PhotoFileService::DISK)->delete($newPath);
            }

            throw $e;
        }

        if (
            $newPath &&
            $oldPath &&
            $oldPath !== $newPath
        ) {
            Storage::disk(PhotoFileService::DISK)->delete($oldPath);
        }

        return $this->successResponse(
            'Foto actualizada correctamente.',
            new DiagnosticItemPhotoResource(
                $photo
            )
        );
    }

    public function destroy(DiagnosticItem $diagnosticItem, DiagnosticItemPhoto $photo, DiagnosticService $diagnosticService)
    {
        $diagnosticService->ensureItemsEditable($diagnosticItem->diagnostic);

        $path = $photo->path;

        if ($path) {
            Storage::disk(PhotoFileService::DISK)->delete($path);
        }

        $photo->delete();

        return $this->successResponse(
            'Foto eliminada correctamente.'
        );
    }
}
