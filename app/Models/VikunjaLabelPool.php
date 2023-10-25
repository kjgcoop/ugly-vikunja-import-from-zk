<?php

namespace App\Models;

use App\Helpers\VikunjaHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Log;

use App\Models\ZkTag;

class VikunjaLabelPool
{
    use HasFactory;
    protected $existingLabels = [];
    protected $projectId;

    public function __construct() {
    }

    public function getExistingLabels() {
        return $this->existingLabels;
    }

    public function populate() {
        $this->existingLabels = [];

        $url = env('VIKUNJA_URL_START').'/labels?per_page=999';

        \Log::stack(['daily', 'stderr'])->info("Getting existing labels by hitting $url");
        $result = VikunjaHelper::get($url);

        if ($result->successful()) {
            $body = json_decode($result->getBody()->getContents());

            \Log::stack(['stderr'])->info('Retrieved ' . count($body) . ' existing labels');
            \Log::stack(['daily'])->info('Retrieved ' . count($body) . ' existing labels', [ 'label_req_body' => $body ]);

            foreach ($body as $label) {
                $vikunjaLabel = new VikunjaLabel($this);

                $vikunjaLabel->convertFromVikunjaApi($label);

                $this->cacheLabel($vikunjaLabel);
            }

            // It hashed them by title, so if you have two with the same title,
            // the second will clobber the first. You'll wind up returning
            // fewer tags than reported above
            \Log::stack(['stderr'])->info('Hashing existing labels resulted in ' . count($this->existingLabels) . ' distinct labels');
            return true;
        } else {
            throw new \Exception('Problem getting existing labels from ' . $url);
        }
    }

    public function setProjectId(int $projectId): bool {
        $this->projectId = $projectId;
        return true;
    }

    public function convertFromZkTag(ZkTag $zkTag) {

        $this->title = $zkTag->getName();
        // ZK sends the color with a leading #; Vikunja will barf if it sees it.
        $this->color = ltrim($zkTag->getColor(), '#');
    }

    public function getId() {
        return $this->id;
    }

    public function alreadyExists(VikunjaLabel $label): bool {
        return isset($this->existingLabels[$label->getTitle()]) && $this->existingLabels[$label->getTitle()]->getId() !== null;
    }

    public function cacheLabel(VikunjaLabel $newLabel): bool {
        // We know this but don't have an ID for it.
        if ($this->alreadyExists($newLabel) && $this->getIdFromTitle($newLabel->getTitle()) !== null) {
            $this->existingLabels[$newLabel->getTitle()]->setID($newLabel->getId());

        // We know nothing of this.
        } elseif (!$this->alreadyExists($newLabel)) {
            $this->existingLabels[$newLabel->getTitle()] = $newLabel;

        // We already have what we need. No action required.
        } else {
            // We have a good one. Nod and smile.
        }

        return true;
    }

    // Outgo alone has the null ID problem here.
    public function syncAllLabels(): bool {
        if ($this->projectId === null) {
            Log::stack(['daily', 'stderr'])->info("No project ID given when attempting to sync all labels");
            throw new \Exception("No project ID given when attempting to sync labels; can't sync labels");
        }

        $url = env('VIKUNJA_URL_START').'/labels';

        foreach ($this->existingLabels as $label) {

            // If there's already a label by that name, don't add the new one,
            // even though Vikunja supports multiple tags with the same name.
            if ($this->alreadyExists($label)) {
//                Log::stack(['daily', 'stderr'])->info("Not creating label (".$label->getTitle()." as ".$label->getColor().") because one already exists with ID ".$label->getId(), [ 'existing' => $label ]);
                continue;
            }

            try {
                // This label has the ID
                Log::stack(['daily', 'stderr'])->info("Will create label (".$label->getTitle()." as ".$label->getColor().")", [ 'label' => $label ]);
                $newLabel = $label->add(['project_id' => $this->projectId]);
                $this->updateCache($newLabel);

            } catch (\Exception $e) {
                Log::stack(['daily', 'stderr'])->info("Exception adding new label (".$label->getTitle().')', [ 'exception' => $e ]);
                throw new \Exception('Exception adding new label ('.$label->getTitle().')');
            }
        }
        return true;
    }

    // ZK has tag groups whereas Vikunja does not. We get a bunch of tag groups
    // with the tags inside, but we need all the tags residing therein as a
    // flat array.
    // In ZK, you could have group A with tags 1 (associated with color green),
    // 2 (blue) and 3 (red). You could also have group B with tags 1 (purple)
    // and 4 (idk w/e you get the point). This will keep the color of whichever
    // tag 1 comes in second.
    public function mashDownZkTagGroups(array $zkTagGroups) : array {

        foreach ($zkTagGroups as $group) {
            foreach ($group->getTags() as $zkTag) {

                $vikunjaLabel = new VikunjaLabel($this);
                $vikunjaLabel->convertFromZkTag($zkTag);

                $this->updateCache($vikunjaLabel);
            }
        }

        return $this->existingLabels;
    }

    public function updateCache(VikunjaLabel $label) {
        if ($this->alreadyExists($label)) {
            // Make sure we're not clobbering a legit ID
            if (is_null($label->getId())) {
                // Nah, it's cool man.
                Log::stack(['daily', 'stderr'])->info('Not adding label '.$label->getTitle().' to cache in '.__CLASS__.' because the ID is null');
            } else {
                $this->existingLabels[$label->getTitle()] = $label;
            }
        } else {
            $this->existingLabels[$label->getTitle()] = $label;
        }
    }

    public function getIdFromTitle(string $title) {
        echo 'Getting ID based on title "'.$title."\"\n";

        if (isset($this->existingLabels[$title])) {
            echo 'About to return ID: '.$this->existingLabels[$title]->getId()."\n";
            return $this->existingLabels[$title]->getId();
        }

        Log::stack(['daily', 'stderr'])->info('Cannot find ID for label with title '.$title.' in '.__CLASS__.' line '.__LINE__);
        throw new \Exception('Cannot find ID for label with title '.$title);
    }
}
