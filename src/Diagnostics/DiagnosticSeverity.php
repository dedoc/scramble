<?php

namespace Dedoc\Scramble\Diagnostics;

enum DiagnosticSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
