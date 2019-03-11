<?php

namespace App\Console\Commands;

use App\Services\SomeCodeService;
use Illuminate\Console\Command;

class CreateTestSomeCode extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'somecode:create {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test somecode commands';

    protected $someCodeService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(SomeCodeService $someCodeService)
    {
        parent::__construct();

        $this->someCodeService = $someCodeService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Creating ' . $this->argument('quantity') . ' codes');
        $answer = $this->someCodeService->createTestCodes($this->argument('quantity'));

        if (isset($answer['error'])) {
            $this->error($answer['error']);
        } elseif (isset($answer['success'])) {
            $this->info('Created ' . $answer['count'] . ' codes');
        }

        return true;
    }
}
