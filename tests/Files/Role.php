<?php

namespace Dedoc\Scramble\Tests\Files;

enum Role: string
{
    case Admin = 'admin';
    case TeamLead = 'team_lead';
    case Developer = 'developer';
}
