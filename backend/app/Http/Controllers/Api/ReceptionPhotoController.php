<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ReceptionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateReceptionPhotoRequest;
use App\Http\Requests\UploadReceptionPhotosRequest;
use App\Http\Resources\ReceptionPhotoResource;
use App\Models\Reception;
use App\Models\ReceptionPhoto;
use App\Services\PhotoFileService;
use App\Services\ReceptionStatusService;
use App\Traits\ApiResponse;
use App\Traits\FileResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceptionPhotoController extends Controller implements HasMiddleware
{
    use ApiResponse, FileResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:receptions-photos.index', only: ['index', 'file']),
            new Middleware('permission:receptions-photos.store', only: ['store']),
            new Middleware('permission:receptions-photos.update', only: ['update']),
            new Middleware('permission:receptions-photos.destroy', only: ['destroy']),
        ];
    }

    public function index(Reception $reception)
    {
        return $this->successResponse(
            'Fotos obtenidas correctamente.',
            ReceptionPhotoResource::collection($reception->photos),
            200
        );
    }

    /** Archivo de la foto, en línea (P8, paso 2, historial 2.106). */
    public function file(Reception $reception, ReceptionPhoto $photo, PhotoFileService $photoFileService)
    {
        return $this->privateImageResponse($photoFileService->image(
            $photo->path,
            new ReceptionException('La foto no tiene un archivo disponible.', 404, 'RECEPTION_PHOTO_FILE_NOT_FOUND')
        ));
    }

    public function store(UploadReceptionPhotosRequest $request, Reception $reception, ReceptionStatusService $receptionStatusService)
    {
        $receptionStatusService->ensureEditable($reception);

        $data = $request->validated();
        $uploadedFiles = [];
        $photos = [];

        // Solo el guardado va dentro del try: si falla la respuesta, las filas ya
        // guardadas conservan sus archivos (deuda #26).
        try {
            DB::transaction(function () use ($data, $reception, &$uploadedFiles, &$photos) {
                foreach ($data['photos'] as $photoData) {
                    $file = $photoData['file'];

                    $path = $file->store('receptions', PhotoFileService::DISK);
                    $uploadedFiles[] = $path;

                    $photos[] = $reception->photos()->create([
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'description' => $photoData['description'] ?? null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($uploadedFiles as $path) {
                Storage::disk(PhotoFileService::DISK)->delete($path);
            }

            throw $e;
        }

        return $this->successResponse(
            'Fotos cargadas correctamente.',
            ReceptionPhotoResource::collection(collect($photos)),
            201
        );
    }

    public function update(UpdateReceptionPhotoRequest $request, Reception $reception, ReceptionPhoto $photo, ReceptionStatusService $receptionStatusService)
    {
        $receptionStatusService->ensureEditable($reception);

        $data = $request->validated();
        $oldPath = null;

        if ($request->hasFile('file')) {
            $oldPath = $photo->path;
            $file = $request->file('file');

            $photo->path = $file->store('receptions', PhotoFileService::DISK);
            $photo->original_name = $file->getClientOriginalName();
        }

        if (array_key_exists('description', $data)) {
            $photo->description = $data['description'];
        }

        $photo->save();

        if ($oldPath) {
            Storage::disk(PhotoFileService::DISK)->delete($oldPath);
        }

        return $this->successResponse(
            'Foto actualizada correctamente.',
            new ReceptionPhotoResource($photo),
            200
        );
    }

    public function destroy(Reception $reception, ReceptionPhoto $photo, ReceptionStatusService $receptionStatusService)
    {
        $receptionStatusService->ensureEditable($reception);

        if ($photo->path && Storage::disk(PhotoFileService::DISK)->exists($photo->path)) {
            Storage::disk(PhotoFileService::DISK)->delete($photo->path);
        }

        $photo->delete();

        return $this->successResponse(
            'Foto eliminada correctamente.'
        );
    }
}
