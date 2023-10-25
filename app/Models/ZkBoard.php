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


class ZkBoard extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $shortId;
    protected $name;

    // ZK cards = Vikunja tasks

    protected $zkCards = [];
    protected $zkDetails  = [];
    protected $zkTagGroups = [];

    // This should be a TagGroup
    protected $zkTagGroupToBecomeColumns;

    public function __construct($zkApiDump) {
        $this->zkApiDump = $zkApiDump;

        $this->shortId = $zkApiDump->shortId;
        $this->name = $zkApiDump->name;
    }

    public function getName() {
        return $this->name;
    }

    public function getShortId() {
        return $this->shortId;
    }

    private function pullContents($shortId) {
        $url = env('ZK_URL_START').'/lists/'.$shortId.'/elements';

        Log::stack(['daily', 'stderr'])->info("Querying $url (".$this->getName().") for board details");
        return ZkHelper::get($url);
    }

    public function pullComments() {
        $url = env('ZK_URL_START').'/lists/'.$this->shortId.'/activities?filter=2';
        Log::stack(['daily', 'stderr'])->info("Querying $url (".$this->getName().") for board comments");
        return ZkHelper::get($url);
    }

    private function pullCardContents($shortId) {
        $url = env('ZK_URL_START').'/lists/'.$shortId.'/entries/filter/list';
        $body = new \stdClass();
        $body->limit = 1000;
        Log::stack(['daily', 'stderr'])->info("Querying $url for cards belonging to '".$this->getName()."'");
        return ZkHelper::post($url, $body);
    }

    // Details about the board
    private function parseBoardDetails() {
        // Get all the details about the board - contains tags and whatnot
        $boardDataPoints_res = $this->pullContents($this->getShortId());
        $boardDataPoints = json_decode($boardDataPoints_res->getBody()->getContents());
        foreach ($boardDataPoints as $key => $boardData) {

            // Put it in an object so we can have nice names for detecting what type of detail it is. We don't want to
            // save every detail to the board because the relevant ones will be replaced with less generic objects.
            // $boardDetail is disposable.
            $boardDetail = new ZkBoardDetail($boardData);

            // It's a tag group
            if ($boardDetail->isTagGroup()) {
                // We want to keep this
                $zkTagGroup = new ZkTagGroup($boardData);
                // This is the tag group that will become the column names
                if ($zkTagGroup->getName() === env('ZK_DEFAULT_LIST_NAME')) {
                    // If it's the tag group that will become column names, set it aside so it doesn't appear as both
                    // list heads and tags.
//                    if (isset($this->zkTagGroups[env('ZK_DEFAULT_LIST_NAME')])) {
//                        $this->zkTagGroupToBecomeColumns = $this->zkTagGroups[env('ZK_DEFAULT_LIST_NAME')];
                        $this->zkTagGroupToBecomeColumns = $zkTagGroup;
//                        $this->defaultListId = $zkTagGroup->getId();
//                    }

                // This is a regular tag group
                } else {
                    // These will all be tags - ZK supports groups whereas Vikunja does not - store now as groups, then
                    // let VikunjaTask mash it into one group when it converts this to it own format.
                    $this->zkTagGroups[$zkTagGroup->getName()] = $zkTagGroup;
                }
            }
        }
    }

    public function populate() {
        $this->parseBoardDetails();
        $this->parseCards();
        $this->putCommentsOnCards();
    }

    public function putCommentsOnCards() {
        if (count($this->zkCards) === 0) {
            Log::stack(['stderr'])->info('There are no cards - is this board empty or have you not yet populated it?');
            throw new \Exception('There are no cards - is this board empty or have you not yet populated it?');
        }

        Log::stack(['daily'])->info('About to grab comments on '.$this->getName());

        $comments_res = $this->pullComments();
        $comments = json_decode($comments_res->getBody()->getContents())->activities;


        Log::stack(['stderr'])->info('Distributing '.count($comments).' comments amongst '.count($this->zkCards).' cards');
        foreach ($comments as $comment) {
            $zkComment = new ZkComment($comment);
            Log::stack(['daily'])->info("About to attach comment to task ".$zkComment->getCardName());
            $this->zkCards[$zkComment->getListShortId()]->stashComment($zkComment);
        }
    }

    // Board contents
    private function parseCards() {
        // Get the stuff in the list
        $contents = json_decode($this->pullCardContents($this->getShortId())->getBody()->getContents());
        Log::stack(['daily', 'stderr'])->debug("Found ".count($contents->listEntries)." cards");

        foreach ($contents->listEntries as $card) {
            if (!isset($card->displayString) || $card->displayString === null) {
                Log::stack(['daily'])->debug("Found alleged card with no title @todo wtf is this", [$card]);
                throw new Exception('Found a card without a title in '.$this->title);
            } else {
                // The data sent to card knows which tags are applied to it, but not which groups those tags are from.
                // Therefore, it can't deduce which tag is supposed to become its list and which will continue to be a
                // tag. Send it a list of all the possible list head IDs.
                $zkCard = new ZkCard($card, $this->zkTagGroupToBecomeColumns->getAllTagIds());
                if ($zkCard->getName() === '') {
                    // Don't flood the screen
                    Log::stack(['stderr'])->info('Card with no name');
                    Log::stack(['daily'])->info('Card with no name', [$zkCard]);
                }

                $this->zkCards[$zkCard->getShortId()] = $zkCard;
            }
        }
    }

    public function getCards() {
        return $this->zkCards;
    }

    public function getTagGroups() {
        return $this->zkTagGroups;
    }

    public function getColumnTagGroup() {
        return $this->zkTagGroupToBecomeColumns;
    }
}
