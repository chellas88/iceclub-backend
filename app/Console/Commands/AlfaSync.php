<?php

namespace App\Console\Commands;

use App\Http\Controllers\WidgetController;
use Illuminate\Console\Command;

class AlfaSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alfa:sync {entity?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $amo = new WidgetController();
        $amo->getLessons();
        return 0;
    }
}
