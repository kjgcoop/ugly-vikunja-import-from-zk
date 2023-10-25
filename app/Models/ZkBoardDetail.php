<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class ZkBoardDetail extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $shortId;
    protected $name;
    protected $elementcategory;
    protected $boardData;

    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->shortId = $zkApiDump->shortId;
        $this->name = $zkApiDump->name;
        $this->elementcategory = $zkApiDump->elementcategory;
    }

    public function getName() {
        return $this->name;
    }

    public function getShortId() {
        return $this->shortId;
    }

    public function isTagGroup() {
        return $this->elementcategory == 6;
    }

    public function isAttachments() {
        return $this->name === 'Attachments';
    }

    public function isDescription() {
        return $this->Description === 'Description';
    }
}
