<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Log;
use Http;

use App\Helpers\VikunjaHelper;
use App\Models\ZkBoard;
use App\Models\VikunjaLabelPool;


class VikunjaBoard extends Model
{
    use HasFactory;

    protected $id;
    protected $name;

    // ZK cards = Vikunja tasks
    protected $tasks = [];

    protected $buckets = [];

    protected $labelPool;
    protected $zkEquivalent;

    public function __construct()
    {
        // Labels already in Vikunja
        $this->labelPool = new VikunjaLabelPool();
        $this->labelPool->populate();

//        echo 'Outgo label immed after populating: '.$this->labelPool->getIdFromTitle('Outgo')."\n";
    }

    public function convertFromZkBoard(ZkBoard $zkBoard) {
        $this->zkEquivalent = $zkBoard;

        Log::stack(['daily', 'stderr'])->info('Converting ZK board '.$zkBoard->getName()." to a Vikunja board");
        $this->title = $zkBoard->getName();

        // Need to add labels/tags before buckets and tasks. The labels must
        // exist at a site-level before they can be assigned. Create them all
        // now so they can be added when the buckets and tasks are added.

        // This gets silly because ZK has tag groups whereas Vikunja doesn't,
        // so we need to cram all the tags into one group.
        $this->labelPool->mashDownZkTagGroups($zkBoard->getTagGroups());

        // This is the ZK tag group that will become list headings.
        foreach ($zkBoard->getColumnTagGroup()->getTags() as $tag) {
            // Needs the label pool so it can pass it along to the tasks it's
            // going to add
            $bucket = new VikunjaBucket($this->labelPool);

            // Don't pull in cards in the same function because we don't yet
            // have them already all divvied up by list.
            $bucket->convertFromZkTag($tag);
            $this->buckets[$bucket->getTitle()] = $bucket;
        }

        // Do cards after buckets so they can be distributed.
        foreach ($zkBoard->getCards() as $zkCard) {
            $vikunjaTask = new VikunjaTask();
            $vikunjaTask->convertFromZkCard($zkCard);

            // @todo Sometimes Zk cards are in No Stage - this will fail.
            $this->buckets[$zkCard->getList()]->addTask($vikunjaTask);
        }

        // We now have all the relevant cards in the relevant buckets
    }

    // ZK has tag groups whereas Vikunja does not. We get a bunch of tag groups
    // with the tags inside, but we need all the tags residing therein as a
    // flat array.
    // In ZK, you could have group A with tags 1 (associated with color green),
    // 2 (blue) and 3 (red). You could also have group B with tags 1 (purple)
    // and 4 (idk w/e you get the point). This will keep the color of whichever
    // tag 1 comes in second.
/*    public function mashDownZkTagGroup(array $zkTagGroups) {
        $labels = [];

        foreach ($zkTagGroups as $group) {
            foreach ($group->getTags() as $zkTag) {
                $vikunjaLabel = new VikunjaLabel();
                $vikunjaLabel->convertFromZkTag($zkTag);

                $labels[$vikunjaLabel->getTitle()] = $vikunjaLabel;
            }
        }

        return $labels;
    }*/

    public function getTitle() {
        return $this->title;
    }

    public function getLabelPool() {
        return $this->labelPool;
    }

    public function add(?array $addl_properties = []) {
/*        if ($this->labelPool->getIdFromTitle('Outgo') === null) {
            print_r('Why does it keep saying Outgo has a null ID on '.__FILE__.' line '.__LINE__.'?', $this->labelPool->getIdFromTitle('Outgo')."\n");
//            dd('Why does it keep saying Outgo has a null ID on '.__FILE__.' line '.__LINE__.'?', $this->labelPool->getIdFromTitle('Outgo'));
        } else {
            echo "Outgo evidently has a legit ID on ".__FILE__." line ".__LINE__.": ".$this->labelPool->getIdFromTitle('Outgo');
        }
*/

        if ($this->getTitle() === null) {
            throw new \Exception('You don\'t appear to have populated this object');
        }

        $url = env('VIKUNJA_URL_START').'/projects';

        $properties['title']   = $this->getTitle();
        $properties['parent_project_id'] = 0;

        $all_properties = array_merge($properties, $addl_properties);

        Log::stack(['daily', 'stderr'])->info("Creating a new board (".$this->getTitle().") by hitting $url");
        $result = VikunjaHelper::put($url, $all_properties);

        if ($result->successful()) {
            Log::stack(['daily', 'stderr'])->info('Created Vikunja board '.$this->getTitle());

            $body = json_decode($result->getBody()->getContents());
            $this->id = $body->id;
            $this->labelPool->setProjectId($this->id);

/*            if ($this->labelPool->getIdFromTitle('Outgo')=== null) {
                print_r('Says Outgo has a null ID on line '.__LINE__.'?', $this->labelPool->getIdFromTitle('Outgo')."\n");
//                dd('Why does it keep saying Outgo has a null ID on line '.__LINE__.'?', $this->labelPool->getIdFromTitle('Outgo'));
            } else {
                echo "Outgo evidently has a legit ID on line ".__LINE__.": ".$this->labelPool->getIdFromTitle('Outgo')."\n";
            }*/
//dd('About to sync all, but we know it\'s a problem there, so don\'t bother continuing');
            // Now that we have an ID, we can sync the lables
            $this->labelPool->syncAllLabels();

            // Must add the buckets before I can add cards/tasks so there's a
            // bucket ID to send with the card/task.
            foreach ($this->buckets as $bucket) {
                Log::stack(['daily', 'stderr'])->info("About to add bucket ".$bucket->getTitle()." - contains ".count($bucket->getTasks()).' tasks');
                $properties = [];
                $properties['project_id'] = $this->getId();
                $bucket->add($properties, $this->labelPool);
            }

            return true;
        } else {
//            dd('Failing Vikunja board creation response body', $result->getBody()->getContents(), 'Headers', $result->getHeaders(), 'Whole response variable', $result);
            Log::stack(['daily', 'stderr'])->error('Problem creating Vikunja board '.$this->getTitle());
            throw new \Exception('Problem creating Vikunja board '.$this->getTitle());
        }
    }

    public function getId() {
        return $this->id;
    }
}
