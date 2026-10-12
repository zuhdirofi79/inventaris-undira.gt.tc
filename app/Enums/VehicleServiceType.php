<?php

namespace App\Enums;

enum VehicleServiceType: string
{
    case DropOff = 'drop_off';
    case RoundTrip = 'round_trip';
    case Pickup = 'pickup';
    case GoCar = 'gocar';

    public function label(): string
    {
        return match ($this) {
            self::DropOff => 'Drop-off',
            self::RoundTrip => 'Pulang-pergi',
            self::Pickup => 'Penjemputan',
            self::GoCar => 'GoCar',
        };
    }

    /**
     * Layanan yang dipilih peminjam. GoCar ditetapkan admin saat approval.
     *
     * @return list<string>
     */
    public static function requestValues(): array
    {
        return [
            self::DropOff->value,
            self::RoundTrip->value,
            self::Pickup->value,
        ];
    }
}
