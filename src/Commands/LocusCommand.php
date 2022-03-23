<?php

namespace Glanum\Locus\Commands;

use Illuminate\Console\Command;

class LocusCommand extends Command
{
    public $signature = 'laravel-locus';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
