<?php

namespace Oliverbj\Cord\Commands;

use Illuminate\Console\Command;

class CordCommand extends Command
{
    public $signature = 'cord';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
