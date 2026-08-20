<?php

declare(strict_types=1);

namespace Tests\Fixtures\Operations;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Records every event handed to it instead of putting it on the wire — the
 * substitute bound in place of Sentry's real HTTP transport via
 * `config('sentry.transport')`, the option the SDK itself documents as the
 * seam for unit testing. No test in this suite ever makes a real network
 * call to an error-tracking service.
 */
final class FakeSentryTransport implements TransportInterface
{
    /** @var list<Event> */
    public array $events = [];

    public function send(Event $event): Result
    {
        $this->events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
