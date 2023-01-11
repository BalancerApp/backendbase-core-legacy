<?php

declare(strict_types=1);

namespace BackendBase\Shared\Services\MessageBus;

use League\Tactician\CommandBus as TacticianCommandBus;

class MessageBus
{
    public function __construct(private TacticianCommandBus $messageBus)
    {
    }

    /**
     * Executes the given command and optionally returns a value
     *
     * @return mixed
     *
     * @var object
     */
    public function handle(object $command)
    {
        return $this->messageBus->handle($command);
    }
}
