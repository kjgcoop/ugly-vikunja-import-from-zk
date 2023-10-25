<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;
use Illuminate\Support\Facades\Storage;

use ZkHelper;


class ZkComment extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $message;
    protected $cardName;
    protected $createdAt;


    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->message = $zkApiDump->message;
        $this->cardName = $zkApiDump->listEntryDisplayString;
        $this->createdAt = $zkApiDump->created_at;
        $this->listShortId = $zkApiDump->listEntryShortId;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getCardName() {
        return $this->cardName;
    }

    public function getCreatedAt() {
        return $this->createdAt;
    }

    public function getListShortId() {
        return $this->listShortId;
    }
}
