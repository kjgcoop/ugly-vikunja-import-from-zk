<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;
use Illuminate\Support\Facades\Storage;

use ZkHelper;


class ZkChecklistItem extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $text;
    protected $checked;


    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->text = $zkApiDump->text;
        $this->checked = $zkApiDump->checked;
    }

    public function getText() {
        return $this->text;
    }

    public function isChecked() {
        return $this->checked;
    }
}
