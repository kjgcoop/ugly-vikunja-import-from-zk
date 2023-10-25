<?php

namespace App\Models;

use App\Helpers\VikunjaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

use App\Models\ZkCard;
use App\Models\ZkTagGroup;
use App\Models\ZkTag;

class VikunjaTask extends Model
{
    use HasFactory;

    protected $zkApiDump;

    protected $id;

    protected $title;
    protected $shortId;

    protected $bucket;

    // This will have checklists added, if applicable.
    protected $description;

    // Checklists get appended to the description; keep a copy of what the
    // description was before it was monkied with
    protected $originalDescription;

    protected $labels = [];
    protected $attachments = [];
    protected $comments = [];

    protected $labelPool;
    protected $column;

    protected $zkEquivalent;

    public function __construct() {
    }

    // This is the format that the Vikunja API wants it
    public function getAsHash(?array $addl_properties = []) {

        $hash = [
            'title' => $this->getTitle(),
            'done' => $this->isDone(),
            'description' => $this->getDescription(),
        ];

        // Examples: Bucket ID, project ID - things this object doesn't know
        // but that may need to get back to Vikunja
        $hash = array_merge($hash, $addl_properties);

        return $hash;
    }

    public function convertFromZkCard(ZkCard $zkCard) {
//        $this->zkEquivalent = $zkCard;

        $this->title  = $zkCard->getName();
        $this->bucket = $zkCard->getList();
        $this->originalDescription = $zkCard->getDescription();
        $this->description = $this->originalDescription;
        $this->description = $this->addChecklistsToDesc($zkCard->getChecklists());

        foreach ($zkCard->getTags() as $zkTag) {
            $vikunjaLabel = new VikunjaLabel($this->labelPool);
            $vikunjaLabel->convertFromZkTag($zkTag);

            $this->labels[$vikunjaLabel->getTitle()] = $vikunjaLabel;
        }

        foreach ($zkCard->getAttachments() as $zkAttachment) {
//            dd('Found a ZK attachment', $zkAttachment);
            Log::stack(['daily'])->info("Found an attachment on ".$this->getTitle().": ".$zkAttachment->getFileName());
            $vikunjaAttachment = new VikunjaAttachment();
            $vikunjaAttachment->convertFromZk($zkAttachment);

            // It's an image or a PDF or something.
            if ($zkAttachment->isDownloadable()) {
                $this->attachments[] = $vikunjaAttachment;

//                $zkAttachment->download();
//                $this->attachments[] = $vikunjaAttachment;

            // Zenkit allows links as attachments. Append them to the
            // description. It's worth noting that it determines that it's not
            // downloadable by checking the mimetype. I didn't see a way to
            // determine if it's actually a link. I just have a suspicion that
            // a null mimetype means it can't be downloaded.
            } else {
                $this->description = $this->addConfusingAttachmentsToDesc($vikunjaAttachment);
                // Don't add to Vikunja attachment list because Vikunja doesn't
                // see it as an attachment
            }
        }


        foreach ($zkCard->getComments() as $zkComment) {
            $vikunjaComment = new VikunjaComment();
            $vikunjaComment->convertFromZk($zkComment);
            $this->comments[] = $vikunjaComment;
        }
    }

    public function addConfusingAttachmentsToDesc(VikunjaAttachment $attachment) {
        $mkdn = $attachment->convertToMarkdown();

        if ($mkdn === '') {
            return $this->description;
        }

        return $this->description."\n\n".$mkdn;
    }

    public function addChecklistsToDesc($zkChecklists) {
        $mkdn = $this->convertToMarkdown($zkChecklists);

        if ($mkdn === '') {
            return $this->description;
        }

        return $this->description."\n\n".$this->convertToMarkdown($zkChecklists);
    }

    // This is so ugly it hurts.
    public function convertToMarkdown($zkChecklists) {
        $append = '';
        foreach ($zkChecklists as $zkChecklist) {
            $zkItems = $zkChecklist->getItems();

            if (count($zkItems) === 0) {
                $append .= 'Checklist "'.$zkChecklist->getName()."\" is empty\n";
            } else {
                $append .= "\n";
                $append .= 'Checklist "'.$zkChecklist->getName()."\"\n";
                foreach ($zkChecklist->getItems() as $zkItem) {
                    if ($zkItem->isChecked()) {
                        $append .= '- [X] '.$zkItem->getText()."\n";
                    } else {
                        $append .= '- [ ] '.$zkItem->getText()."\n";
                    }
                }
                $append .= "\n\n";
            }
        }
        return $append;

    }

    public function add(array $properties, $labelPool) {
        // Clobber if necessary - this one may have the IDs
        $this->labelPool = $labelPool;

        if (!isset($properties['project_id'])) {
            throw new \Exception("You must specify a project");
        }

        $url = env('VIKUNJA_URL_START').'/projects/'.$properties['project_id'];

        $all_properties = array_merge($properties, $this->getAsHash());
        $result = VikunjaHelper::put($url, $all_properties);

        if (!$result->successful()) {
            Log::stack(['daily', 'stderr'])->info('Problem adding task '.$this->getTitle());
            throw new \Exception('Problem adding task '.$this->getTitle());
        }

        Log::stack(['daily', 'stderr'])->info('Added task '.$this->getTitle());
        $body = json_decode($result->getBody()->getContents());
        $this->id = $body->id;

        Log::stack(['daily', 'stderr'])->info('About to assign labels to '.$this->getTitle());
        $this->makeAssignments();


        Log::stack(['daily', 'stderr'])->info("About to upload ".count($this->attachments)." attachments");

        // @todo This was seg faulting on the loop; this is awful
        if (isset($this->attachments[0])) {
            if ($this->attachments[0]->isDownloadable()) {
                $this->attachments[0]->upload($this->id);
            } else {
                Log::stack(['daily', 'stderr'])->info("Didn't upload attachment with mimetype ".$this->attachments[0]->getMimetype());
            }
        }

        if (isset($this->attachments[1])) {
            if ($this->attachments[1]->isDownloadable()) {
                $this->attachments[1]->upload($this->id);
            } else {
                Log::stack(['daily', 'stderr'])->info("Didn't upload attachment with mimetype ".$this->attachments[1]->getMimetype());
            }
        }

        if (isset($this->attachments[2])) {
            if ($this->attachments[2]->isDownloadable()) {
                $this->attachments[2]->upload($this->id);
            } else {
                Log::stack(['daily', 'stderr'])->info("Didn't upload attachment with mimetype ".$this->attachments[2]->getMimetype());
            }
        }

        if (isset($this->attachments[3])) {
            if ($this->attachments[3]->isDownloadable()) {
                $this->attachments[3]->upload($this->id);
            } else {
                Log::stack(['daily', 'stderr'])->info("Didn't upload attachment with mimetype ".$this->attachments[3]->getMimetype());
            }
        }

        if (isset($this->attachments[4])) {
            if ($this->attachments[4]->isDownloadable()) {
                $this->attachments[4]->upload($this->id);
            } else {
                Log::stack(['daily', 'stderr'])->info("Didn't upload attachment with mimetype ".$this->attachments[4]->getMimetype());
            }
        }


        /*
                // Commented out because the loop was seg faulting
                foreach ($this->attachments as $attachment) {
                    dd('Successfully looped into attachment '.$attachment->getLocalPath());
                    Log::stack(['daily', 'stderr'])->info("About to upload attachment ".$attachment->getLocalPath());

                    if (file_exists($attachment->getLocalPath())) {
                        $attachment->upload($this->id);
                    } else {
                        Log::stack(['daily', 'stderr'])->info("Skipped attachment allegedly at ".$attachment->getLocalPath()." because it doesn't exist.");
                    }
                }*/

        foreach ($this->comments as $comment) {
            Log::stack(['daily', 'stderr'])->info("About to add a comment: ".$comment->getMessage());
            $comment->add(['task_id' => $this->id ]);
        }

        return true;
    }

    public function getId() : int {
        return $this->id;
    }

    public function makeAssignments() {

        foreach ($this->labels as $label_name => $label) {
            $assignment_details = [
                'task_id' => $this->getId(),
                'label_id' => $this->labelPool->getIdFromTitle($label_name)
            ];

            if ($assignment_details['label_id'] === null) {
                Log::stack(['daily', 'stderr'])->info('Null label sent when trying to assign tag "'.$label_name.'" to task "'.$this->getTitle().'"');
                dd('Existing labels - I found an assignment with null ID', $this->labelPool->getExistingLabels());
                throw new \Exception('Null label ID on "'.$label_name.'" - what are the existing labels?');
            }

            if ($label->assign($assignment_details)) {
                Log::stack(['daily', 'stderr'])->info('Assigned label "'.$label->getTitle().'" to task "'.$this->getTitle().'"');
            } else {
                Log::stack(['daily', 'stderr'])->info('Failed to assign label "'.$label->getTitle().'" to task "'.$this->getTitle().'"');
                throw new \Exception('Failed to assign label '.$label->getTitle().' to task '.$this->getTitle());
            }
        }
    }

    // ZK actually has a done flag on each column, but I'm not sure offhand how
    // to parse it; this works for now.
    public function isDone() {
        return $this->column === 'Done';
    }

    public function getTitle() {
        return $this->title;
    }

    public function getDescription() {
        return $this->description;
    }
    public function getBucket() {
        return $this->bucket;
    }

    public function getLabels() {
        return $this->labels;
    }
}
