<?php

namespace App\Enums;

enum AccessSyncAction: string
{
    case AddCard = 'ADD_CARD';
    case UpdateCard = 'UPDATE_CARD';
    case DeleteCard = 'DELETE_CARD';
    case DisableCard = 'DISABLE_CARD';
    case EnableCard = 'ENABLE_CARD';
    case FullSync = 'FULL_SYNC';
}
