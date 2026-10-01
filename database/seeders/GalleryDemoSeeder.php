<?php

namespace Database\Seeders;

use App\Models\Album;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        if ($admin === null) {
            $this->command->warn('Нет администратора — GalleryDemoSeeder пропущен.');

            return;
        }

        $processor = app(ImageProcessor::class);

        $albumsData = [
            [
                'title' => 'Linkshell members',
                'slug' => 'linkshell-members',
                'sort_order' => 10,
                'photos' => [
                    ['caption' => 'Bugor', 'color' => [100, 150, 200]],
                    ['caption' => 'Scaevola', 'color' => [180, 120, 80]],
                    ['caption' => 'Drakonus', 'color' => [90, 160, 130]],
                    ['caption' => 'Jumxi', 'color' => [150, 90, 160]],
                    ['caption' => 'Oleg', 'color' => [200, 160, 80]],
                    ['caption' => 'Monarch', 'color' => [80, 100, 180]],
                ],
            ],
            [
                'title' => 'Memories',
                'slug' => 'memories',
                'sort_order' => 20,
                'photos' => [
                    ['caption' => '14.05.2005', 'color' => [120, 80, 60]],
                    ['caption' => '28.02.2006', 'color' => [70, 120, 150]],
                    ['caption' => '28.02.2006 forum', 'color' => [140, 140, 90]],
                    ['caption' => 'Users 1', 'color' => [100, 100, 140]],
                    ['caption' => 'Users 2', 'color' => [160, 110, 110]],
                    ['caption' => 'Users 3', 'color' => [80, 130, 100]],
                ],
            ],
            [
                'title' => 'Areas',
                'slug' => 'areas',
                'sort_order' => 30,
                'photos' => [
                    ['caption' => 'Temenos', 'color' => [60, 80, 130]],
                    ['caption' => "Escha - Ru'Aun", 'color' => [120, 100, 70]],
                    ['caption' => 'Reisenjima HELM NMs', 'color' => [90, 140, 110]],
                    ['caption' => 'Sortie', 'color' => [130, 70, 70]],
                    ['caption' => 'Ambuscade', 'color' => [100, 110, 150]],
                ],
            ],
        ];

        $tmpDir = storage_path('app/gallery-seed');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        foreach ($albumsData as $albumData) {
            $album = Album::updateOrCreate(
                ['slug' => $albumData['slug']],
                [
                    'user_id' => $admin->id,
                    'scope' => Album::SCOPE_SITE,
                    'title' => $albumData['title'],
                    'sort_order' => $albumData['sort_order'],
                    'is_published' => true,
                ],
            );

            foreach ($albumData['photos'] as $index => $photoData) {
                $tmpPath = $tmpDir.'/'.uniqid('seed-', true).'.jpg';

                $this->generatePlaceholder(
                    $tmpPath,
                    $photoData['color'],
                    $index + 1,
                );

                $file = new UploadedFile(
                    $tmpPath,
                    'seed.jpg',
                    'image/jpeg',
                    null,
                    true,
                );

                $photo = $processor->process($file, $album, $admin->id);
                $photo->forceFill([
                    'caption' => $photoData['caption'],
                    'sort_order' => $index + 1,
                ])->save();

                @unlink($tmpPath);
            }

            // Обложка — первое фото
            $firstPhoto = $album->photos()->orderBy('sort_order')->first();
            if ($firstPhoto !== null) {
                $album->forceFill(['cover_photo_id' => $firstPhoto->id])->save();
            }
        }

        // Чистим временный каталог
        Storage::disk('local')->deleteDirectory('gallery-seed');
    }

    /**
     * @param  array{int, int, int}  $rgb
     */
    private function generatePlaceholder(string $path, array $rgb, int $number): void
    {
        [$r, $g, $b] = $rgb;

        $width = 800;
        $height = 600;

        $image = imagecreatetruecolor($width, $height);

        // Градиент от цвета к более тёмному оттенку
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) ($r * (1 - $ratio * 0.4)),
                (int) ($g * (1 - $ratio * 0.4)),
                (int) ($b * (1 - $ratio * 0.4)),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        // Номер в центре
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $text = '#'.$number;
        $fontSize = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($text);
        $textHeight = imagefontheight($fontSize);

        imagestring(
            $image,
            $fontSize,
            (int) (($width - $textWidth) / 2),
            (int) (($height - $textHeight) / 2),
            $text,
            $textColor,
        );

        imagejpeg($image, $path, 85);
        imagedestroy($image);
    }
}
