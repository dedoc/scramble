<?php

namespace Dedoc\Scramble\Tests\Files;

enum Status: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
