<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\Rag\Document;

enum DocumentType: string
{
    case Architecture = 'architecture';
    case Api = 'api';
    case Testing = 'testing';
    case Convention = 'convention';
    case General = 'general';
    case Tutorial = 'tutorial';
    case Reference = 'reference';
}
