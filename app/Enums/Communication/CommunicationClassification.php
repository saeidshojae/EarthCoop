<?php

namespace App\Enums\Communication;

enum CommunicationClassification: string
{
    case Required = 'required';
    case Operational = 'operational';
    case Optional = 'optional';
}
