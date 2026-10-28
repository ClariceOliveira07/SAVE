<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class OpenCvImageProcessingService
{
    /**
     * @return array{
     *     image: array{width: int, height: int, channels: int, format: string, size_bytes: int},
     *     processing: array{engine: string, version: string, recognized_foods: bool}
     * }
     */
    public function process(UploadedFile $image): array
    {
        $storedPath = $image->store('food-scans', 'local');

        if (! is_string($storedPath)) {
            throw new RuntimeException('Não foi possível armazenar a imagem para processamento.');
        }

        try {
            $pythonScript = base_path('python/process_image.py');
            $pythonBinary = config('services.opencv.python_binary');

            if (! is_file($pythonScript) || ! is_string($pythonBinary) || trim($pythonBinary) === '') {
                throw new RuntimeException('A configuração local do OpenCV está incompleta.');
            }

            $process = new Process([
                $pythonBinary,
                $pythonScript,
                Storage::disk('local')->path($storedPath),
            ], base_path());
            $process->setTimeout((int) config('services.opencv.timeout', 30));
            $process->run();

            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

            if (! $process->isSuccessful()
                || ! is_array($result)
                || ! is_array($result['image'] ?? null)
                || ! is_array($result['processing'] ?? null)) {
                throw new RuntimeException('O processador OpenCV retornou um resultado inválido.');
            }

            return $result;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Não foi possível processar a imagem. Confira o serviço OpenCV e tente novamente.',
                previous: $exception,
            );
        } finally {
            Storage::disk('local')->delete($storedPath);
        }
    }
}
