<?php

namespace App\Enums;

enum Role: string
{
    case Citoyen = 'citoyen';
    case Moderateur = 'moderateur';
    case Administrateur = 'administrateur';
}