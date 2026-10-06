<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Сохранение фото и видео, которые загружают пользователи.
 *
 * Общий для портфолио мастеров и фото в срочных заявках.
 *
 * Расширению и заголовку браузера не верим: тип определяется по содержимому.
 * Фото дополнительно открывается через GD и сохраняется заново — так файл,
 * который только притворяется картинкой, не пройдёт, а из настоящего снимка
 * уходят EXIF-данные. Это важно: телефон пишет в снимок GPS-координаты, и
 * фото «после ремонта» выдало бы адрес квартиры заказчика.
 */
class MediaStore
{
    public const PHOTO_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    /** Фото — до 10 МБ. */
    public const MAX_PHOTO_KB = 10240;

    /** Видео — до 100 МБ. */
    public const MAX_VIDEO_KB = 102400;

    /** Обложка видео из браузера — до 1 МБ. */
    public const MAX_POSTER_KB = 1024;

    /** Длинная сторона сохранённого фото. */
    private const FULL_SIDE = 2000;

    /** Длинная сторона миниатюры. */
    private const THUMB_SIDE = 480;

    /** Длинная сторона обложки видео. */
    private const POSTER_SIDE = 960;

    /**
     * Больше этого GD не открываем: 50 мегапикселей — уже ~200 МБ памяти,
     * а такой снимок с телефона не приходит.
     */
    private const MAX_PIXELS = 50_000_000;

    public function __construct(private readonly string $disk = 'public')
    {
    }

    /**
     * Тип файла по содержимому: photo, video или null — не годится.
     */
    public function kind(UploadedFile $file): ?string
    {
        $mime = $this->sniff($file);

        return match (true) {
            in_array($mime, self::PHOTO_MIMES, true) => 'photo',
            in_array($mime, self::VIDEO_MIMES, true) => 'video',
            default => null,
        };
    }

    /**
     * Сохранить фото: уменьшенная копия без EXIF и миниатюра.
     *
     * @return array{path:string, thumb_path:string, mime:string, size:int, width:int, height:int}
     *
     * @throws MediaRejected файл не открывается как изображение
     */
    public function storePhoto(UploadedFile $file, string $folder): array
    {
        $image = $this->open($file);

        $full = $this->fit($image, self::FULL_SIDE);
        $thumb = $this->fit($image, self::THUMB_SIDE);

        $name = Str::random(32);
        $path = $folder.'/'.$name.'.jpg';
        $thumbPath = $folder.'/'.$name.'_thumb.jpg';

        $this->put($path, $full, 85);
        $this->put($thumbPath, $thumb, 78);

        $result = [
            'path' => $path,
            'thumb_path' => $thumbPath,
            'mime' => 'image/jpeg',
            'size' => Storage::disk($this->disk)->size($path),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];

        imagedestroy($image);
        imagedestroy($full);
        imagedestroy($thumb);

        return $result;
    }

    /**
     * Сохранить видео как есть и, если браузер прислал, обложку к нему.
     *
     * Видео не перекодируем: для этого нужен ffmpeg, а его на сервере нет.
     * Кадр-обложку снимает браузер при выборе файла; без неё в галерее
     * будет значок ▶ на цветном фоне.
     *
     * @return array{path:string, poster_path:?string, mime:string, size:int}
     *
     * @throws MediaRejected
     */
    public function storeVideo(UploadedFile $file, string $folder, ?UploadedFile $poster = null): array
    {
        $mime = $this->sniff($file);

        if (! in_array($mime, self::VIDEO_MIMES, true)) {
            throw new MediaRejected(__('Видео в этом формате не подходит. Загрузите MP4, WEBM или MOV.'));
        }

        $extension = match ($mime) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };

        $name = Str::random(32);
        $path = $file->storeAs($folder, $name.'.'.$extension, $this->disk);

        return [
            'path' => $path,
            'poster_path' => $this->storePoster($poster, $folder, $name),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * Обложка видео — тоже через GD: присланное браузером не верим на слово.
     * Битая обложка не повод отказать в видео — просто без неё.
     */
    private function storePoster(?UploadedFile $poster, string $folder, string $name): ?string
    {
        if (! $poster || ! $poster->isValid() || $poster->getSize() > self::MAX_POSTER_KB * 1024) {
            return null;
        }

        try {
            $image = $this->open($poster);
        } catch (MediaRejected) {
            return null;
        }

        $fitted = $this->fit($image, self::POSTER_SIDE);
        $path = $folder.'/'.$name.'_poster.jpg';
        $this->put($path, $fitted, 80);

        imagedestroy($image);
        imagedestroy($fitted);

        return $path;
    }

    /** MIME по содержимому файла, а не по имени. */
    private function sniff(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        if (! $path || ! is_file($path)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) ? strtolower($mime) : null;
    }

    /**
     * Открыть изображение и повернуть, как его снял телефон.
     *
     * @throws MediaRejected
     */
    private function open(UploadedFile $file): \GdImage
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if (! $info || ! in_array($info['mime'] ?? null, self::PHOTO_MIMES, true)) {
            throw new MediaRejected(__('Файл не похож на фото. Загрузите JPG, PNG или WEBP.'));
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new MediaRejected(__('Фото слишком большого разрешения. Уменьшите его и загрузите снова.'));
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        if (! $image) {
            throw new MediaRejected(__('Фото повреждено и не открывается.'));
        }

        return $this->orient($image, $path, $info['mime']);
    }

    /**
     * Телефон часто хранит снимок «лёжа» и пишет поворот в EXIF. EXIF при
     * пересохранении пропадёт — поэтому поворачиваем сами, пока он есть.
     */
    private function orient(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * Копия, вписанная в квадрат side×side. Меньшее не увеличиваем.
     * Прозрачность (PNG, WEBP) заливаем белым: JPEG её не держит, а чёрный
     * фон по умолчанию превратил бы логотип в чёрный квадрат.
     */
    private function fit(\GdImage $image, int $side): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $side / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function put(string $path, \GdImage $image, int $quality): void
    {
        ob_start();
        imageinterlace($image, true);
        imagejpeg($image, null, $quality);

        Storage::disk($this->disk)->put($path, (string) ob_get_clean());
    }
}
