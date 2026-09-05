<?php

declare(strict_types=1);

namespace App\Listeners\Omnichat;

use App\Actions\Omnichat\DetectPhoneNumberFromMessage;
use App\Events\OmnichatMessageCreated;

class CaptureLeadPhoneNumber
{
    public function __construct(private readonly DetectPhoneNumberFromMessage $detector) {}

    public function handle(OmnichatMessageCreated $event): void
    {
        $this->detector->execute($event->message);
    }
}
