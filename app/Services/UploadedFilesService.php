<?php

namespace App\Services;

use App\Jobs\PictureJob;
use App\Models\Picture;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UploadedFilesService
{
    /**
     * Store an uploaded picture and optimize it
     *
     * @param  UploadedFile  $uploadedFile  The uploaded file
     * @return Picture The stored picture
     *
     * @throws Exception
     */
    public function storeAndOptimizeUploadedPicture(UploadedFile $uploadedFile): Picture
    {
        if (! in_array($uploadedFile->extension(), config('app.supported_image_formats'))) {
            throw new Exception('The specified file is not a picture or is not supported');
        }

        $folderName = 'uploads/'.Carbon::now()->format('Y/m/d');
        $fileName = $uploadedFile->hashName();

        $fileHasBeenSavedToLocal = $uploadedFile->store($folderName, 'public');

        if (! $fileHasBeenSavedToLocal) {
            $isFolderWritable = is_writable(Storage::disk('public')->path($folderName));

            throw new Exception('Error while saving the uploaded file. Is the folder writable? '.$isFolderWritable);
        }

        if (config('app.cdn_disk')) {
            $fileHasBeenSavedToCdn = $uploadedFile->store($folderName, config('app.cdn_disk'));

            if (! $fileHasBeenSavedToCdn) {
                throw new Exception('Error while saving the uploaded file to the CDN');
            }
        }

        $pictureInstance = Picture::create([
            'filename' => $fileName,
            'size' => $uploadedFile->getSize(),
            'path_original' => $folderName.'/'.$fileName,
        ]);

        PictureJob::dispatch($pictureInstance);

        return $pictureInstance;
    }
}
