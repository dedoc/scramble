<?php

namespace Dedoc\Scramble\Attributes;

enum QueryParameterStyle: string
{
    case Form = 'form';
    case SpaceDelimited = 'spaceDelimited';
    case PipeDelimited = 'pipeDelimited';
    case DeepObject = 'deepObject';
}
