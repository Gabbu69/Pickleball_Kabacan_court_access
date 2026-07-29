<?php

namespace App\Enums;

enum UserRole: string
{
    case Player = 'player';
    case Owner = 'owner';
    case Admin = 'admin';
}
