<?php
/**
 * Video Processing with FFmpeg
 * Supports multiple formats and adaptive bitrate
 */

class VideoProcessor
{
    private static $ffmpegPath = '/usr/bin/ffmpeg';
    private static $ffprobePath = '/usr/bin/ffprobe';

    public static function process(string $inputPath, array $options = []): array
    {
        $config = App::$config['video'];

        $result = [
            'formats' => [],
            'duration' => 0,
            'width' => 0,
            'height' => 0,
            'thumbnail' => '',
            'status' => 'pending',
        ];

        try {
            $info = self::getVideoInfo($inputPath);

            $result['duration'] = $info['duration'];
            $result['width'] = $info['width'];
            $result['height'] = $info['height'];

            $result['thumbnail'] = self::generateThumbnail($inputPath, $info);

            foreach ($config['formats'] as $format) {
                foreach ($config['resolutions'] as $res) {
                    if ($res <= $info['height']) {
                        $out = self::transcode($inputPath, $format, $res, $config);
                        if ($out) {
                            $result['formats'][] = [
                                'format' => $format,
                                'resolution' => $res,
                                'path' => $out,
                                'size' => filesize(
                                    App::$config['paths']['uploads'] . '/videos/' . $out
                                ),
                            ];
                        }
                    }
                }
            }

            if ($config['watermark']['enabled'] && $config['watermark']['path']) {
                self::addWatermark($inputPath, $config['watermark']);
            }

            $result['status'] = 'completed';

        } catch (Exception $e) {
            $result['status'] = 'failed';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    private static function getVideoInfo(string $path): array
    {
        $cmd = sprintf(
            '%s -v quiet -print_format json -show_format -show_streams %s',
            escapeshellarg(self::$ffprobePath),
            escapeshellarg($path)
        );

        exec($cmd, $out, $code);
        if ($code !== 0) {
            throw new Exception('ffprobe failed');
        }

        $data = json_decode(implode('', $out), true);

        foreach ($data['streams'] as $stream) {
            if ($stream['codec_type'] === 'video') {
                return [
                    'duration' => (float)$data['format']['duration'],
                    'width' => (int)$stream['width'],
                    'height' => (int)$stream['height'],
                ];
            }
        }

        throw new Exception('No video stream');
    }

    private static function generateThumbnail(string $path, array $info): string
    {
        $dir = App::$config['paths']['uploads'] . '/thumbnails';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $name = 'thumb_' . uniqid() . '.jpg';
        $full = $dir . '/' . $name;
        $time = $info['duration'] * 0.25;

        $cmd = sprintf(
            '%s -ss %s -i %s -vframes 1 -q:v 2 %s 2>&1',
            escapeshellarg(self::$ffmpegPath),
            escapeshellarg($time),
            escapeshellarg($path),
            escapeshellarg($full)
        );

        exec($cmd);
        return $name;
    }

    private static function transcode(string $in, string $format, int $res, array $cfg): ?string
    {
        $dir = App::$config['paths']['uploads'] . '/videos';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $name = 'video_' . uniqid() . "_{$res}p.$format";
        $full = $dir . '/' . $name;
        $bitrate = self::getBitrateForResolution($res, $cfg['bitrates']);

        $cmd = sprintf(
            '%s -i %s -vf "scale=-2:%d" -c:v libx264 -b:v %s -c:a aac -b:a 128k %s 2>&1',
            escapeshellarg(self::$ffmpegPath),
            escapeshellarg($in),
            $res,
            $bitrate,
            escapeshellarg($full)
        );

        exec($cmd, $out, $code);
        return ($code === 0 && file_exists($full)) ? $name : null;
    }

    private static function addWatermark(string $path, array $wm): void
    {
        $out = $path . '.wm.mp4';

        $cmd = sprintf(
            '%s -i %s -i %s -filter_complex "overlay=10:10" %s',
            escapeshellarg(self::$ffmpegPath),
            escapeshellarg($path),
            escapeshellarg($wm['path']),
            escapeshellarg($out)
        );

        exec($cmd);
        if (file_exists($out)) rename($out, $path);
    }

    private static function getBitrateForResolution(int $res, array $bitrates): string
    {
        return match (true) {
            $res <= 360 => $bitrates[0] ?? '500k',
            $res <= 720 => $bitrates[1] ?? '1000k',
            default => end($bitrates) ?: '2500k',
        };
    }
}
