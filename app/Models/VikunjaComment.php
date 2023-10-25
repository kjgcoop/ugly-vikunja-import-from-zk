<?php

namespace App\Models;

use App\Helpers\VikunjaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;
use Illuminate\Support\Facades\Storage;

use App\Models\ZkComment;

class VikunjaComment extends Model
{
    use HasFactory;

    protected $message;
    protected $createdAt;

    protected $zkEquivalent;

    public function __construct()
    {
    }

    public function convertFromZk(ZkComment $comment)
    {
        $this->message = $comment->getMessage();
        $this->createdAt = $comment->getCreatedAt();
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function getAsHash() {
        $hash = [];
        $hash['comment'] = $this->getMessage();
        $hash['created'] = $this->getCreatedAt();

        return $hash;
    }

    public function add(?array $addl_properties = []) {
        if (!isset($addl_properties['task_id'])) {
            throw new \Exception('Need to send a task ID to add a comment to a task.');
        }

        $url = env('VIKUNJA_URL_START').'/tasks/'.$addl_properties['task_id'].'/comments';

        Log::stack(['daily', 'stderr'])->info('Will attempt to add comment "'.$this->getMessage().'"');

        $all_properties = array_merge($this->getAsHash(), $addl_properties);

        $result = VikunjaHelper::put($url, $all_properties);

        if (!$result->successful()) {
            Log::stack(['daily', 'stderr'])->info("Failed to add comment " . $this->getMessage());
            throw new \Exception('Problem creating comment ' . $this->getMessage());
        }

        $body = json_decode($result->getBody()->getContents());
        $this->id = $body->id;

        Log::stack(['daily', 'stderr'])->info("Successfully added comment ".$this->getMessage());

        return true;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getCardName()
    {
        return $this->cardName;
    }
}



