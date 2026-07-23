<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\RenderFailureException;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Spatie\Browsershot\Browsershot;
use Throwable;

class RenderSecureQuestionImageJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $question;

    private string $tempDir;

    private string $filename;

    /**
     * Create a new job instance.
     */
    public function __construct(array $question, string $tempDir)
    {
        $this->question = $question;
        $this->tempDir = $tempDir;
        $this->filename = 'question_'.$this->question['id'].'.png';
    }

    /**
     * Execute the job.
     *
     * @throws RenderFailureException
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            // 1. Render Blade view
            $html = view('questions.render', ['question' => $this->question])->render();

            // 2. Use Browsershot to generate image
            $imagePath = storage_path('app/private/'.$this->tempDir.'/'.$this->filename);

            // Ensure directory exists
            Storage::disk('local')->makeDirectory($this->tempDir);

            Browsershot::html($html)
                ->noSandbox()
                ->setChromePath(config('secure-pdf.chromium.path', '/usr/bin/chromium'))
                ->windowSize(1190, 1684)
                ->waitUntilNetworkIdle()
                ->save($imagePath);

            // 3. Apply Anti-OCR techniques using Intervention Image
            $manager = new ImageManager(new Driver);
            $image = $manager->decodePath($imagePath);

            // Add aggressive noise (pixelation/blur equivalent or manual noise)
            // Intervention v3 allows pixelate, blur, or writing text.
            // Add diagonal semi-transparent watermark
            $image->text('CONFIDENTIAL - SECURE EXAM', 595, 842, function ($font) {
                // We use default font since TTF path might vary
                $font->color('rgba(255, 0, 0, 0.15)'); // Semi-transparent red
                $font->size(60);
                $font->angle(45);
            });

            // Add random noise lines to confuse OCR
            for ($i = 0; $i < 10; $i++) {
                $image->drawLine(function ($line) {
                    $line->from(rand(0, 1190), rand(0, 1684));
                    $line->to(rand(0, 1190), rand(0, 1684));
                    $line->color('rgba(150, 150, 150, 0.2)'); // faint gray lines
                    $line->width(rand(1, 3));
                });
            }

            $image->save($imagePath);

        } catch (Throwable $e) {
            throw new RenderFailureException('Failed to render secure question image: '.$e->getMessage(), 0, $e);
        }
    }
}
