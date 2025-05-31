<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SyncLocalStorageWithCdnCommand extends Command
{
    protected $signature = 'sync:local-storage-with-cdn';

    protected $description = 'Synchronize local storage images with CDN storage';

    public function handle(): void
    {
        $this->info('Starting synchronization...');

        // Disques locaux et CDN
        $localDisk = Storage::disk('public'); // Local storage
        $cdnDisk = Storage::disk('bunnycdn'); // CDN storage

        // Chemin du dossier racine des uploads
        $localPath = 'uploads';

        // Récupérer tous les fichiers locaux
        $localFiles = collect($localDisk->allFiles($localPath));
        $cdnFiles = collect($cdnDisk->allFiles($localPath));

        // Fichiers à envoyer au CDN
        $filesToUpload = $localFiles->diff($cdnFiles);

        // Fichiers à supprimer du CDN
        $filesToDelete = $cdnFiles->diff($localFiles);

        $totalFiles = $filesToUpload->count() + $filesToDelete->count();

        if ($totalFiles === 0) {
            $this->info('Nothing to synchronize. Both storages are already in sync.');

            return;
        }

        $this->withProgressBar($filesToUpload->concat($filesToDelete), function ($file) use ($filesToUpload, $filesToDelete, $localDisk, $cdnDisk) {
            if ($filesToUpload->contains($file)) {
                // Upload file to CDN
                $cdnDisk->put($file, $localDisk->get($file));
            } elseif ($filesToDelete->contains($file)) {
                // Delete file from CDN
                $cdnDisk->delete($file);
            }
        });

        $this->newLine();
        $this->info('Synchronization completed successfully.');
    }
}
