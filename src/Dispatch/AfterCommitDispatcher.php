<?php

declare(strict_types=1);

namespace Alma\Sylius\Dispatch;

use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Contracts\Service\ResetInterface;

class AfterCommitDispatcher implements ResetInterface
{
    /** @var list<\Closure(): void> */
    private array $recorded = [];

    /** @var list<\Closure(): void> */
    private array $committed = [];

    /**
     * @param \Closure(): void $call
     */
    public function record(\Closure $call): void
    {
        $this->recorded[] = $call;
    }

    public function postFlush(): void
    {
        array_push($this->committed, ...$this->recorded);
        $this->recorded = [];
    }

    public function dispatch(): void
    {
        $committed = $this->committed;
        $this->committed = [];

        foreach ($committed as $call) {
            $call();
        }
    }

    /**
     * A Messenger worker handles many messages in one process and resets this
     * service after each one: the calls go once the message is handled.
     */
    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->dispatch();
    }

    /** The message's transaction may have been rolled back. */
    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->reset();
    }

    public function reset(): void
    {
        $this->recorded = [];
        $this->committed = [];
    }
}
