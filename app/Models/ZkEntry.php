<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;

use ZkHelper;

use App\Models\ZkCard;
use App\Models\ZkBoardDetail;
use App\Models\ZkTagGroup;
use App\Models\ZkTag;


class ZkEntry extends Model
{
    public function __construct(array $zkApiDump)
    {
        $this->zkApiDump = $zkApiDump;

//        $this->shortId = $zkApiDump->shortId;
//        $this->name = $zkApiDump->name;

    }
}
