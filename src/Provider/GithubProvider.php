<?php

namespace DavidBadura\GitWebhooks\Provider;

use DavidBadura\GitWebhooks\Event\AbstractEvent;
use DavidBadura\GitWebhooks\Event\IssueEvent;
use DavidBadura\GitWebhooks\Event\MergeRequestEvent;
use DavidBadura\GitWebhooks\Event\PushEvent;
use DavidBadura\GitWebhooks\Struct\Commit;
use DavidBadura\GitWebhooks\Struct\Repository;
use DavidBadura\GitWebhooks\Struct\User;
use DavidBadura\GitWebhooks\Util;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Tobias Olry <tobias.olry@gmail.com>
 */
class GithubProvider implements ProviderInterface
{
    const NAME = 'github';

    /**
     * @param Request $request
     * @return AbstractEvent
     */
    public function create(Request $request)
    {
        $data = $this->getData($request);

        if (!$data) {
            return null;
        }

        switch ($request->headers->get('X-Github-Event')) {
            case 'push':
                return $this->createPushEvent($data);
            case 'issues':
                return $this->createIssueEvent($data);
            case 'pull_request':
                return $this->createMergeRequestEvent($data);
            default:
                return null;
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function support(Request $request)
    {
        return $request->headers->has('X-Github-Event');
    }

    /**
     * @param array $data
     * @return IssueEvent
     */
    private function createIssueEvent(array $data)
    {

    }

    /**
     * @param array $data
     * @return MergeRequestEvent
     */
    private function createMergeRequestEvent(array $data)
    {
        throw new \Exception('not yet implemented');

        $event               = new MergeRequestEvent();
        $event->provider     = self::NAME;
        $event->id           = $data['object_attributes']['id'];
        $event->title        = $data['object_attributes']['title'];
        $event->description  = $data['object_attributes']['description'];
        $event->targetBranch = $data['object_attributes']['target_branch'];
        $event->sourceBranch = $data['object_attributes']['source_branch'];
        $event->state        = $data['object_attributes']['state'];
        $event->createdAt    = new \DateTime($data['object_attributes']['created_at']);
        $event->updatedAt    = new \DateTime($data['object_attributes']['updated_at']);

        $user       = new User();
        $user->id   = $data['object_attributes']['author_id'];
        $user->name = $data['user']['name'];

        $targetRepository            = new Repository();
        $targetRepository->id        = $data['object_attributes']['target_project_id'];
        $targetRepository->name      = $data['object_attributes']['target']['name'];
        $targetRepository->namespace = $data['object_attributes']['target']['namespace'];
        $targetRepository->url       = $data['object_attributes']['target']['ssh_url'];

        $sourceProject            = new Repository();
        $sourceProject->id        = $data['object_attributes']['source_project_id'];
        $sourceProject->name      = $data['object_attributes']['source']['name'];
        $sourceProject->namespace = $data['object_attributes']['source']['namespace'];
        $sourceProject->url       = $data['object_attributes']['source']['ssh_url'];

        $event->user             = $user;
        $event->repository       = $targetRepository;
        $event->sourceRepository = $sourceProject;
        $event->lastCommit       = $this->createCommit($data['object_attributes']['last_commit']);

        return $event;
    }

    /**
     * @param array $data
     * @return PushEvent
     */
    private function createPushEvent(array $data)
    {
        $event           = new PushEvent();
        $event->provider = self::NAME;
        $event->before   = $data['before'];
        $event->after    = $data['after'];
        $event->ref      = $data['ref'];

        if ($data['sender']['type'] <> 'User') {
            throw new \Exception('unsupported sender type ' . $data['sender']['type']);
        }

        $user       = new User();
        $user->id   = $data['sender']['id'];
        $user->name = $data['pusher']['name'];
        $user->email = $data['pusher']['email'];

        $repository              = new Repository();
        $repository->id          = $data['repository']['id'];
        $repository->name        = $data['repository']['name'];
        $repository->namespace   = explode('/', $data['repository']['full_name'], 1);
        $repository->description = $data['repository']['description'];
        $repository->homepage    = $data['repository']['homepage'];
        $repository->url         = $data['repository']['url'];

        $event->user       = $user;
        $event->repository = $repository;
        $event->commits    = $this->createCommits($data['commits']);

        $event->type = Util::getPushType($event->ref);

        if ($event->type == PushEvent::TYPE_BRANCH) {
            $event->branchName = Util::getBranchName($event->ref);
        } else {
            $event->tagName = Util::getTagName($event->ref);
        }

        return $event;
    }

    /**
     * @param array $data
     * @return Commit[]
     */
    private function createCommits(array $data)
    {
        $result = [];

        foreach ($data as $row) {
            $result[] = $this->createCommit($row);
        }

        return $result;
    }

    /**
     * @param array $data
     * @return Commit
     */
    private function createCommit(array $data)
    {
        $commit = new Commit();

        $commit->id      = $data['id'];
        $commit->message = $data['message'];
        $commit->date    = new \DateTime($data['timestamp']);

        $user        = new User();
        $user->name  = $data['author']['name'];
        $user->email = $data['author']['email'];

        $commit->author = $user;

        return $commit;
    }

    /**
     * @param Request $request
     * @return array
     */
    private function getData(Request $request)
    {
        $body = $request->getContent();

        return json_decode($body, true);
    }
}