<?php

declare(strict_types=1);

namespace App\Modules\Sppd\Domain;

/** Komponen biaya SPPD (BPP §II.B.1) yang disimpan sebagai baris tarif tersendiri. */
enum SppdTariffComponent: string
{
    case UangMakan = 'uang_makan';
    case UangSaku = 'uang_saku';
    case HotelCap = 'hotel_cap';
    case HotelCompensation = 'hotel_compensation';
    case LocalTransportCap = 'local_transport_cap';
    case DestinationTransportCap = 'destination_transport_cap';

    public function label(): string
    {
        return match ($this) {
            self::UangMakan => 'Uang Makan',
            self::UangSaku => 'Uang Saku',
            self::HotelCap => 'Plafon Hotel',
            self::HotelCompensation => 'Kompensasi Hotel (bila tidak disediakan Bank)',
            self::LocalTransportCap => 'Plafon Angkutan Setempat',
            self::DestinationTransportCap => 'Plafon Transportasi ke Tempat Tujuan (PP)',
        };
    }
}
