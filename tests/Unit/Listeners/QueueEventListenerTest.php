<?php

use Grazulex\LaravelChronotrace\Listeners\QueueEventListener;
use Grazulex\LaravelChronotrace\Services\TraceRecorder;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;

it('captures job processing events', function (): void {
    config(['chronotrace.capture.jobs' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('jobs', Mockery::on(fn ($data): bool => $data['type'] === 'job_processing'
            && isset($data['job_name'])
            && isset($data['queue'])));

    $listener = new QueueEventListener($mockRecorder);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendEmailJob');
    $mockJob->shouldReceive('getQueue')->andReturn('default');
    $mockJob->shouldReceive('attempts')->andReturn(1);

    $event = new JobProcessing('connection', $mockJob);
    $listener->handleJobProcessing($event);
});

it('captures job processed events', function (): void {
    config(['chronotrace.capture.jobs' => true]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldReceive('addCapturedData')
        ->once()
        ->with('jobs', Mockery::on(fn ($data): bool => $data['type'] === 'job_processed'
            && isset($data['job_name'])
            && isset($data['queue'])));

    $listener = new QueueEventListener($mockRecorder);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendEmailJob');
    $mockJob->shouldReceive('getQueue')->andReturn('default');

    $event = new JobProcessed('connection', $mockJob);
    $listener->handleJobProcessed($event);
});

it('does not capture queue events when disabled', function (): void {
    config(['chronotrace.capture.jobs' => false]);

    $mockRecorder = Mockery::mock(TraceRecorder::class);
    $mockRecorder->shouldNotReceive('addCapturedData');

    $listener = new QueueEventListener($mockRecorder);

    $mockJob = Mockery::mock(Job::class);
    $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\SendEmailJob');
    $mockJob->shouldReceive('getQueue')->andReturn('default');

    $event = new JobProcessing('connection', $mockJob);
    $listener->handleJobProcessing($event);
});
