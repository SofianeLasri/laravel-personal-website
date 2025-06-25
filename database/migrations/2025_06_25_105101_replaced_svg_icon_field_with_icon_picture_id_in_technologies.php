<?php

use App\Models\Picture;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;

return new class extends Migration
{
    public function up(): void
    {
        // Create a dummy picture for all technologies
        $dummyPicture = $this->createDummyPicture();

        Schema::table('technologies', function (Blueprint $table) {
            $table->unsignedBigInteger('icon_picture_id')->nullable();
            $table->foreign('icon_picture_id')->references('id')->on('pictures')->onDelete('cascade');
        });

        // Update all existing technologies to use the dummy picture
        DB::table('technologies')->update(['icon_picture_id' => $dummyPicture->id]);

        // Make the field required
        Schema::table('technologies', function (Blueprint $table) {
            $table->unsignedBigInteger('icon_picture_id')->nullable(false)->change();
        });

        // Remove the old svg_icon field
        Schema::table('technologies', function (Blueprint $table) {
            $table->dropColumn('svg_icon');
        });
    }

    public function down(): void
    {
        Schema::table('technologies', function (Blueprint $table) {
            $table->string('svg_icon');
        });

        Schema::table('technologies', function (Blueprint $table) {
            $table->dropForeign(['icon_picture_id']);
            $table->dropColumn('icon_picture_id');
        });
    }

    private function createDummyPicture(): Picture
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->create(128, 128)->fill('6B7280'); // Gray color for dummy icons
        $path = 'uploads/dummy-tech-icon-'.uniqid().'.jpg';

        // Store locally
        Storage::disk('public')->put($path, $image->toJpeg()->toString());

        // Store to CDN if configured
        if (config('app.cdn_disk')) {
            Storage::disk(config('app.cdn_disk'))->put($path, $image->toJpeg()->toString());
        }

        return Picture::create([
            'filename' => basename($path),
            'width' => 128,
            'height' => 128,
            'size' => strlen($image->toJpeg()->toString()),
            'path_original' => $path,
        ]);
    }
};
