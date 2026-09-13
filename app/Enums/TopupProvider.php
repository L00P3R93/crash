<?php

namespace App\Enums;

enum TopupProvider: string
{
    case Mpesa = 'mpesa';
    case AirtelMoney = 'airtel_money';
    case Card = 'card';
    case Manual = 'manual';
}
