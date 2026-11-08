<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    // Izinkan pengisian kolom ini
    protected $fillable = ['kegiatan', 'waktu_mulai', 'waktu_selesai'];
}