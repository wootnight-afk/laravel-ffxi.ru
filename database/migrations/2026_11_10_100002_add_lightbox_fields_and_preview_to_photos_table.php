<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Отдельное превью (WebP 480×480, без кропа). NULL — используется thumb.
            $table->string('path_preview', 255)->nullable()->after('path_thumb');

            // Название — заголовок над изображением в PhotoSwipe.
            $table->string('title', 150)->nullable()->after('caption_en');
            $table->string('title_en', 150)->nullable()->after('title');

            // Краткое описание — текст под изображением в PhotoSwipe.
            $table->string('summary', 300)->nullable()->after('title_en');
            $table->string('summary_en', 300)->nullable()->after('summary');

            // Дополнительное описание — страница «Подробнее», над комментариями.
            $table->text('details')->nullable()->after('summary_en');
            $table->text('details_en')->nullable()->after('details');

            // Независимые переключатели вывода в PhotoSwipe. Текст не удаляют.
            $table->boolean('show_title_in_lightbox')->default(false)->after('details_en');
            $table->boolean('show_summary_in_lightbox')->default(false)->after('show_title_in_lightbox');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'path_preview',
                'title',
                'title_en',
                'summary',
                'summary_en',
                'details',
                'details_en',
                'show_title_in_lightbox',
                'show_summary_in_lightbox',
            ]);
        });
    }
};
