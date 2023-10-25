<?php

namespace App\Console\Commands;

use App\Models\VikunjaBoard;
use Illuminate\Console\Command;
use Http;
use Log;

use App\Helpers\ZkHelper;
use App\Helpers\VikunjaHelper;
use App\Models\ZkBoard;

class VikunjaFromZk extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:zk';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull in data from ZenKit';

    protected $zkBoards = [];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function getWorkspacesWithLists() {
        $url = env('ZK_URL_START').'/users/me/workspacesWithLists';
        Log::stack(['daily', 'stderr'])->info("Querying $url for workspaces with lists");
        return ZkHelper::get($url);
    }

    public function getListEntries($shortId) {
        $url = env('ZK_URL_START').'/lists/'.$shortId.'/entries';
        Log::stack(['daily'])->info("Querying $url for list entries");
        $body = new \stdClass();
        $body->limit = 999;
        $entries = ZkHelper::post($url, $body);
        return $entries;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // https://laravel.com/docs/10.x/logging#available-channel-drivers
        Log::stack(['daily', 'stderr'])->info('Importing from Zenkit to Vikunja');
        Log::stack(['daily', 'stderr'])->info(env('ZK_URL_START'));

        $workspace_res = $this->getWorkspacesWithLists();
        if ($workspace_res->successful()) {
            $workspaces = json_decode($workspace_res->getBody()->getContents());

            Log::stack(['daily', 'stderr'])->debug('Got workspace data back - found '.count($workspaces).' workspaces');

            if (empty($workspaces)) {
                Log::stack(['daily', 'stderr'])->fatal('Found no workspaces. Uhoh.', $workspaces, 'Status code', $workspaces->getStatusCode());
            }

            foreach ($workspaces as $workspace) {
                if (count($workspace->lists) === 0) {
                    Log::stack(['daily', 'stderr'])->info('Workspace "'.$workspace->name.'" has no lists; moving along');
                    continue;
                }

                Log::stack(['daily', 'stderr'])->info('Found board in workspace "'.$workspace->name.'"');


                foreach ($workspace->lists as $list) {

                    $zkBoard = new ZkBoard($list);
                    Log::stack(['daily', 'stderr'])->info($zkBoard->getName()." (".$zkBoard->getShortId().")\n");

                    $zkBoard->populate();

                    $vikunjaBoard = new VikunjaBoard();
                    $vikunjaBoard->convertFromZkBoard($zkBoard);
                    $vikunjaBoard->add();
                }
                Log::stack(['daily', 'stderr'])->info('Looped through a complete board; will add to Vikunja.');
            }


        } elseif ($workspace_res->failed()) {
            Log::stack(['daily', 'stderr'])->error('Getting workspaces failed', ['resource' => $workspace_res, 'body' => $workspace_res->getBody()->getContents()]);
        }

        return 0;
    }
}
