<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Jadwal; // Panggil model Jadwal

class AssistantController extends Controller
{
    public function handleCommand(Request $request)
    {
        $perintah =$request->input('perintah');

        // 1. Ajari AI cara membalas dengan format yang baru
        $systemInstruction = "Kamu adalah asisten AI pribadi bernama Gemini yang mengontrol HP dan jadwal milik Alfarizi.
        WAJIB balas dalam format JSON murni tanpa markdown (tanpa ```json).
        
        Kaidah balasan:
        1. Jika disuruh mencatat jadwal: {\"aksi\": \"tambah_jadwal\", \"kegiatan\": \"nama kegiatannya\", \"waktu_mulai\": \"HH:MM:00\"}
        2. Jika ditanya jadwal hari ini: {\"aksi\": \"cek_jadwal\"}
        3. Jika disuruh buka aplikasi: {\"aksi\": \"buka_app\", \"target\": \"nama_aplikasi\"}
        4. Jika sapaan/ngobrol santai: {\"aksi\": \"bicara\", \"pesan\": \"balasan kamu\"}";

        // 2. Tembak ke Gemini API dengan pengaman jaringan dan model Flash Lite
        $response = Http::withoutVerifying()
            ->withOptions([
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=' . env('GEMINI_API_KEY'), [
                'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => [['parts' => [['text' => $perintah]]]]
            ]);

            $hasil = $response->json();
        
            // --- TAMBAHKAN BARIS INI UNTUK DEBUGGING ---
            // Jika Google tidak mengirimkan 'candidates' (berarti ada error), tampilkan error aslinya
            if (!isset($hasil['candidates'])) {
            return response()->json($hasil);
            }
            // -------------------------------------------

            // Tangkap teks JSON dari AI
                $teksBalasan = $hasil['candidates'][0]['content']['parts'][0]['text'] ?? '{"aksi": "error"}';
            // 3. LOGIKA EKSEKUSI (Tangan Kanan AI)
        
            // Jika AI memutuskan untuk TAMBAH JADWAL
                if (isset($dataAI['aksi']) && $dataAI['aksi'] === 'tambah_jadwal') {
                    Jadwal::create([
                    'kegiatan' => $dataAI['kegiatan'],
                    'waktu_mulai' => $dataAI['waktu_mulai'],
                    'waktu_selesai' => $dataAI['waktu_mulai'] // Sementara disamakan
                ]);
            
            return response()->json([
                'aksi' => 'bicara', 
                'pesan' => 'Siap bos! Jadwal ' . $dataAI['kegiatan'] . ' jam ' . $dataAI['waktu_mulai'] . ' sudah saya simpan ke database.'
            ]);
        }
        
        // Jika AI memutuskan untuk CEK JADWAL
        if (isset($dataAI['aksi']) && $dataAI['aksi'] === 'cek_jadwal') {
            $semuaJadwal = Jadwal::all();
            
            if ($semuaJadwal->isEmpty()) {
                return response()->json(['aksi' => 'bicara', 'pesan' => 'Jadwal kamu hari ini kosong, Alfarizi. Waktunya bersantai!']);
            }

            $teksJadwal = "Ini jadwalmu hari ini: ";
            foreach ($semuaJadwal as $j) {
                $teksJadwal .= $j->kegiatan . " (jam " . $j->waktu_mulai . "), ";
            }

            return response()->json(['aksi' => 'bicara', 'pesan' => $teksJadwal]);
        }

        // Jika perintah lainnya (buka app / ngobrol), langsung lempar kembali ke HP
        return response($teksBalasan)->header('Content-Type', 'application/json');
    }
}