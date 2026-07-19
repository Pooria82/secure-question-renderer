<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\RenderFailureException;
use App\Jobs\RenderSecureQuestionImageJob;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;

class RenderSecureQuestionImageJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_throws_render_failure_exception_when_browsershot_fails()
    {
        // Mock Browsershot by binding it or intercepting the call.
        // Wait, Browsershot is instantiated directly inside the Job via static Browsershot::html() which returns a new instance.
        // Since we cannot easily mock static calls on a 3rd party class without an alias or dependency injection, 
        // we can use Mockery's "overload" feature or an alias mock to intercept the instantiation.
        
        // Since alias/overload mocks can be tricky in PHPUnit, another way to force Browsershot to fail
        // is to give it an invalid node binary path, which will cause it to throw an exception when it tries to run.
        
        $question = [
            'id' => 'q_error',
            'text' => 'Crash test',
            'options' => ['A', 'B']
        ];

        $job = new class($question, 'temp_dir') extends RenderSecureQuestionImageJob {
            public function handle(): void
            {
                // We override handle just to catch how we might inject failure if we used DI.
                // But let's actually just run the parent handle and mock the Browsershot class statically.
                parent::handle();
            }
        };

        // We can use Mockery to alias the class
        Mockery::mock('alias:' . Browsershot::class)
            ->shouldReceive('html')
            ->andThrow(new RuntimeException('Chrome crashed!'));

        $this->expectException(RenderFailureException::class);
        $this->expectExceptionMessage('Failed to render secure question image: Chrome crashed!');

        $job->handle();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
