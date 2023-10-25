<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

class ZkTag extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $name;
    protected $color;
    protected $id;

    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        if (!isset($zkApiDump->id)) {
            dd('No name property, allegedly', [ 'dump' => $zkApiDump ]);
        }
        $this->name = $zkApiDump->name;
        $this->id = $zkApiDump->id;
        $this->color = $zkApiDump->colorHex;
    }

    public function getId() {
        return $this->id;
    }

    public function getName() {
        return $this->name;
    }

    public function getColor() {
        return $this->color;
    }

}
