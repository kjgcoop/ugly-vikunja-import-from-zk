<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

use App\Models\ZkBoardDetail;
use App\Models\ZkTagGroup;
use App\Models\ZkTag;
use App\Models\ZkEntry;

class ZkCard extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $name;
    protected $shortId;

    protected $list;

    // @todo Impement
    protected $sortOrder;

    protected $zkEntries;

    protected $description;
    protected $attachments = [];
    protected $checklists = [];
    protected $comments = [];

    protected $tagGroups;

    protected $tags = [];

    protected $column;

    public function __construct($zkApiDump, $defaultListIds) {
        $this->zkApiDump = $zkApiDump;

        // Parse out the stuff we know what it is
        if (isset($zkApiDump->displayString)) {
            $this->name = $zkApiDump->displayString;
        } else {
            // Don't flood the screen
            Log::stack(['stderr'])->error('Found list item with no apparent name - sit down and cry');
            Log::stack(['daily'])->error('Found list item with no apparent name - sit down and cry', ['no_name_list_item' => $zkApiDump]);
        }

        if (isset($zkApiDump->shortId)) {
            $this->shortId = $zkApiDump->shortId;
        } else {
            // Don't flood the screen
            Log::stack(['stderr'])->error('Found a list item with no apparent short ID - sit down and cry');
            Log::stack(['daily'])->error('Found a list item with no apparent short ID - sit down and cry', ['no_short_id_list_item' => $zkApiDump]);
        }

        Log::stack(['daily'])->info('Assembled card '.$this->name.' ('.$this->shortId.')');

        foreach ($zkApiDump as $key => $value) {
            // Deal with the things we know how to deal with
            // In ZK, you can attach a checklist but not in Vikunja. Append to
            // the bottom of the description field. Kind of sucks, but w/e.
            if ($key === 'checklists' && !empty($value)) {
                foreach ($value as $checklist) {
                    $zkChecklist = new ZkChecklist($checklist);
                    $this->checklists[] = $zkChecklist;
                }

            } else if ($key === 'categories') {
//                dd('categories', $value);
                Log::stack(['daily', 'stderr'])->info('categories property', ['cats' => $value]);

            } else if ($key === 'text') {
                Log::stack(['daily'])->info('text property', ['text' => $value]);

            // Assigned tags
            } else if ($this->trimLeadingHash($key) === 'categories_sort') {
                foreach ($value as $tag) {
                    $zkTag = new ZkTag($tag);

                    // If it's from the primary tag group, treat it like a
                    // column; don't put it with the tags
                    if (in_array($zkTag->getId(), $defaultListIds)) {
                        $this->column = $zkTag->getName();
                    } else {
                        $this->tags[] = $zkTag;
                    }
                }

            // Description - both name and desc have a property in the form of
            // [hash]_text - this is a cheap way to avoid clobbering the
            // description with the title.
            } else if ($this->trimLeadingHash($key) === 'text' && $value != $this->getName() && $value !== '') {
                $this->description = $value;

            // Attachments
            } else if ($this->trimLeadingHash($key) === 'filesData' && !empty($value)) {
                foreach ($value as $attachment) {
                    $zkAttachment = new ZkAttachment($attachment);
                    Log::stack(['daily'])->info("Found attachment at ".$zkAttachment->getFileUrl());
//                    dd("Found an attachment at ".$zkAttachment->getFileUrl());

                    try {
//                        dd("Trying to dl ".$zkAttachment->getFileUrl());
                        if ($zkAttachment->isDownloadable()) {
                            $zkAttachment->download();
                        }
                    } catch (\Exception $e) {
//                        dd("Exception thrown with dl ".$zkAttachment->getFileUrl());
                        Log::stack(['daily'])->info("Found attachment without URL - check the log for details");
                    }

                    $this->attachments[] = $zkAttachment;
                    Log::stack(['daily'])->info("New attachment count on card ".$this->title.': '.count($this->attachments));
                }
            }
            // Ignore everything else.
        }
    }

    public function stashComment(ZkComment $comment) {
        $this->comments[] = $comment;
    }

    public function getComments() {
        return $this->comments;
    }

    public function getChecklists() {
        return $this->checklists;
    }

    // This is just begging for a regular expression, but when I couldn't find
    // a suitable one in about an hour, I cobbled this together. It does the
    // trick for now. Also this class is so not the best place for it. What
    // was I saying about a temporary fix?
    // Example values:
    // -- 80f43937-25fd-41f2-ae4c-e4bc195ba39f_searchText
    // -- 0ea498d1-4441-463e-b9ef-c0e8ab8b079f_created_at
    // -- displayString
    // -- created_at
    public function trimLeadingHash($index) {
        $parts = explode('_', $index, 2);

        if (strlen($parts[0]) === 36) {
            unset($parts[0]);
        }

        return implode('_', $parts);
    }

    public function getDescription() {
        return $this->description;
    }
    public function getName() {
        return $this->name;
    }

    public function getList() {
        return $this->column;
    }

    public function getTags() {
        return $this->tags;
    }

    public function getShortId() {
        return $this->shortId;
    }

    public function getAttachments() {
        return $this->attachments;
    }
}
