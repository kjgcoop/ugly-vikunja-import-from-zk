<?php

namespace App\Models;

use App\Helpers\VikunjaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

use App\Models\ZkTag;

class VikunjaLabel
{
    use HasFactory;

    protected $id;
    protected $title;
    protected $color;
    protected $labelPool;
    protected $zkEquivalent;

    public function __construct($labelPool) {
        $this->labelPool = $labelPool;
    }

    public function isComplete() {
        return $this->id !== null;
    }

    public function convertFromVikunjaApi(\stdClass $apiLabel) {
        $this->id    = $apiLabel->id;
        $this->title = $apiLabel->title;
        $this->color = $apiLabel->hex_color;
    }

    // So we know whether or not we'd be duplicating labels.
    public function setLabelPool($labelPool) {
        $this->labelPool = $labelPool;
    }

    public function getTitle() {
        return $this->title;
    }

    public function getColor() {
        return $this->color;
    }

    public function convertFromZkTag(ZkTag $zkTag) {
//        $this->zkEquivalent = $zkTag;

        $this->title = $zkTag->getName();
        // ZK sends the color with a leading #; Vikunja will barf if it sees it.
        $this->color = ltrim($zkTag->getColor(), '#');
    }

    public function getId() {
        return $this->id;
    }

    public function alreadyExists() {
        return $this->labelPool->alreadyExists($this->getTitle());
    }

    public function getAsHash() {
        return [
            'title' => $this->getTitle(),
            'hex_color' => $this->getColor(),
        ];
    }

    public function add(?array $addl_properties = []): VikunjaLabel {
        $url = env('VIKUNJA_URL_START').'/labels';

        // If there's already a label by that name, don't add the new
        // one, even though Vikunja supports multiple tags with the
        // same name.
        Log::stack(['daily', 'stderr'])->info("May attempt to create a new label (".$this->getTitle().")");

        $all_properties = array_merge($this->getAsHash(), $addl_properties);

        $result = VikunjaHelper::put($url, $all_properties);

        if ($result->successful()) {
            $body = json_decode($result->getBody()->getContents());

            $this->id = $body->id;

            if ($result->getBody()->getContents()) {
                Log::stack(['daily', 'stderr'])->info('Laravel tells us adding "'.$this->getTitle().'" it worked, but the body is blank');
                throw new \Exception('Laravel tells us adding "'.$this->getTitle().'" it worked, but the body is blank');
            }
//            dd('Result of add function', $result->getBody()->getContents(), 'Successful()', $result->successful(), 'Failed()', $result->failed());

            Log::stack(['daily', 'stderr'])->info("Successfully created label ".$this->getTitle());
            $this->addSelfToPool();

            return $this;
        } else {
            Log::stack(['daily', 'stderr'])->info("Failed to create label ".$this->getTitle());
            throw new \Exception('Problem adding label '.$this->getTitle());
        }
    }

    public function assign($properties): bool
    {
        // This doesn't know its own ID, and I don't want to have to pull in
        // the list of existing labels every time this executes, so pass it its
        // own ID. Kind of gross, but it works.
        if (!isset($properties['task_id']) || $properties['task_id'] === null || !isset($properties['label_id']) || $properties['task_id'] === null) {
            throw new \Exception('Must provide a task ID and label ID');
        }
        $url = env('VIKUNJA_URL_START').'/tasks/'.$properties['task_id'].'/labels';

        // We've used task_id; Vikunja will barf if you send it along.
        unset($properties['task_id']);

        $result = VikunjaHelper::put($url, $properties);

        if ($result->failed()) {
//            dd('Properties sent when attempting to assign label '.$this->getTitle(), $properties, 'Client error', $result->clientError, 'Server error', $result->serverError());
            throw new \Exception('Problem assigning label '.$this->getTitle().'. Client error: '.$result->clientError().'; server error: '.$result->serverError());
        }

        return true;
    }

    public function setId(int $id) {
        $this->id = $id;
    }

    public function addSelfToPool(): bool {
        echo "Adding ".$this->getTitle()." (".$this->getId().") to pool.\n";
        return $this->labelPool->cacheLabel($this);
    }
}
