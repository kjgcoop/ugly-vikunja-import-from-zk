<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class ZkTagGroup extends ZkBoardDetail
{
    use HasFactory;

    protected $id;
    protected $shortId;
    protected $name;
    protected $tags = [];

    public function __construct($zkApiDump) {
        parent::__construct($zkApiDump);
        $this->shortId = $zkApiDump->shortId;
        $this->id = $zkApiDump->id;
        $this->parseOutTags();
    }

    public function parseOutTags() {
        foreach ($this->zkApiDump->elementData->predefinedCategories as $tag) {
            $this->tags[$tag->name] = new ZkTag($tag);
        }
    }

    public function getId() {
        return $this->id;
    }

    public function getShortId() {
        return $this->shortId;
    }

    public function getName() {
        return $this->name;
    }

    public function getTags() {
        return $this->tags;
    }


    public function getAllTagIds() {
        $ids = [];

        foreach ($this->tags as $tag) {
            $ids[] = $tag->getId();
        }

        return $ids;
    }
}
