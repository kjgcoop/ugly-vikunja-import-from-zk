<?php

namespace App\Models;

use App\Helpers\VikunjaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

use App\Models\ZkTag;

class VikunjaBucket
{
    use HasFactory;

    protected $id;
    protected $title;

    protected $project_id;

    protected $tasks = [];
    protected $labelPool;

    protected $zkEquivalent;

    public function __construct(VikunjaLabelPool $labelPool)
    {
        $this->labelPool = $labelPool;
    }

    public function addTask(VikunjaTask $task) {
        $this->tasks[] = $task;
    }

    function add(?array $addl_properties) {

        if (!isset($addl_properties['project_id'])) {
            throw new \Exception("Can't add bucket ".$this->getTitle()." because there's no project ID");
        }

        $this->project_id = $addl_properties['project_id'];

        $url = env('VIKUNJA_URL_START').'/projects/'.$addl_properties['project_id'].'/buckets';

        $properties['title'] = $this->getTitle();
        $properties['done_bucket'] = false;

        $all_properties = array_merge($properties, $addl_properties);

        Log::stack(['daily', 'stderr'])->info("Creating a new bucket (".$this->getTitle().") by hitting $url");
        $result = VikunjaHelper::put($url, $all_properties);

        if (!$result->successful()) {
            throw new \Exception('Problem adding bucket '.$this->title);
        }

        $body = json_decode($result->getBody()->getContents());
        if (isset($body->id)) {
            $this->id = $body->id;
        } else {
            dd('WTF why does it say there is no ID?', $body);
        }

        // Add its tasks
        foreach ($this->getTasks() as $task) {
            $task->add([
                'project_id' => $this->getProjectId(),
                'bucket_id' => $this->getId()
            ], $this->labelPool);
        }

        return true;
    }

    public function getTasks() {
        return $this->tasks;
    }

    public function getProjectId() {
        return $this->project_id;
    }

    public function convertFromZkTag(ZkTag $zkTag)
    {
        $this->zkEquivalent = $zkTag;
        $this->title = $zkTag->getName();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getTitle()
    {
        return $this->title;
    }
}
