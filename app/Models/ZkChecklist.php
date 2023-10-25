<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Http;
use Log;
use Illuminate\Support\Facades\Storage;

use ZkHelper;


class ZkChecklist extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $name;
    protected $items = [];



    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->name = $zkApiDump->name;
        $this->items = $this->checklistItemsToObjs($zkApiDump->items);
    }

    public function checklistItemsToObjs(?array $items = []) {
        $zkItems = [];
        foreach ($items as $item) {
            $zkItem = new ZkChecklistItem($item);
            $zkItems[] = $zkItem;
        }

        return $zkItems;
    }

    public function getName() {
        return $this->name;
    }

    public function getItems() {
        return $this->items;
    }
}
