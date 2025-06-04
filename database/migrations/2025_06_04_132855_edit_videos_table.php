<?php

use App\Enums\VideoStatus;
use App\Enums\VideoVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('videos_cover_picture_id_foreign');
            }

            $table->unsignedBigInteger('cover_picture_id')->nullable()->change();
            $table->foreign('cover_picture_id')->references('id')->on('pictures');

            $table->enum('status', [VideoStatus::PENDING->value, VideoStatus::TRANSCODING->value, VideoStatus::READY->value, VideoStatus::ERROR->value])->default(VideoStatus::PENDING->value);
            $table->enum('visibility', [VideoVisibility::Public->value, VideoVisibility::Private->value])->default(VideoVisibility::Private->value);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign('videos_cover_picture_id_foreign');
            }

            $table->unsignedBigInteger('cover_picture_id')->nullable(false)->change();
            $table->foreign('cover_picture_id')->references('id')->on('pictures');

            $table->dropColumn(['status', 'visibility']);
        });
    }
};
