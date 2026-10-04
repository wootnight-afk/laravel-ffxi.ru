<?php

namespace App\Enums;

enum EventParticipantStatus: string
{
    case Joined = 'joined';
    case Left = 'left';
}
