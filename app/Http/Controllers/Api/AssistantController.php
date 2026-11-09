<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Jadwal;

class AssistantController extends Controller
{
    public function handleCommand(Request $request)
    {
        // Tangkap input dari HP
        $perintah = $request->input('message') ?? $request->input('perintah');

        // Jika perintah kosong sama sekali, berikan balasan aman
        if (!$perintah) {
            return response()->json([
                'response' => 'Maaf, saya tidak mendengar suara apa pun.'
            ]);
        }

        // 2. Instruksi sistem untuk Gemini
        $systemInstruction = "Kamu adalah asisten AI pribadi bernama Gemini yang mengontrol HP dan jadwal milik Alfarizi.
        WAJIB balas dalam format JSON murni tanpa markdown (tanpa ```json dan tanpa spasi tambahan).
        
        Kaidah balasan:
        1. Jika disuruh mencatat jadwal: {\"aksi\": \"tambah_jadwal\", \"kegiatan\": \"nama kegiatannya\", \"waktu_mulai\": \"HH:MM:00\"}
        2. Jika ditanya jadwal hari ini: {\"aksi\": \"cek_jadwal\"}
        3. Jika disuruh buka aplikasi: {\"aksi\": \"buka_app\", \"target\": \"nama_aplikasi\"}
        4. Jika sapaan/ngobrol santai: {\"aksi\": \"bicara\", \"pesan\": \"balasan kamu\"}";

        // 3. Tembak ke Gemini API
        $response = Http::withoutVerifying()
            ->withOptions([
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ])
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(60)
            ->post('[https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=)' . env('GEMINI_API_KEY'), [
                'system_instruction' => ['parts' => [['text' => $systemInstruction]]],
                'contents' => [['parts' => [['text' => $perintah]]]]
            ]);

        $hasil = $response->json();
    
        // Debugging jika Google menolak/error
        if (!isset($hasil['candidates'])) {
            return response()->json(['response' => 'Terjadi kendala pada server AI Google.']);
        }

        // Tangkap teks balasan dari AI
        $teksBalasan = $hasil['candidates'][0]['content']['parts'][0]['text'] ?? '{"aksi": "bicara", "pesan": "Maaf, saya tidak mengerti."}';
        
        // Bersihkan teks dari markdown code blocks jika AI tidak sengaja memunculkannya
        $teksBersih = str_replace(['```json', '```'], '', $teksBalasan);
        $dataAI = json_decode(trim($teksBersih), true);

        // 4. LOGIKA EKSEKUSI
        if (isset($dataAI['aksi']) && $dataAI['aksi'] === 'tambah_jadwal') {
            Jadwal::create([
                'kegiatan' => $dataAI['kegiatan'] ?? 'Kegiatan Baru',
                'waktu_mulai' => $dataAI['waktu_mulai'] ?? '08:00:00',
                'waktu_selesai' => $dataAI['waktu_mulai'] ?? '08:00:00'
            ]);
        
            return response()->json([
                'response' => 'Siap bos! Jadwal ' . ($dataAI['kegiatan'] ?? '') . ' sudah saya simpan.'
            ]);
        }
        
        if (isset($dataAI['aksi']) && $dataAI['aksi'] === 'cek_jadwal') {
            $semuaJadwal = Jadwal::all();
            
            if ($semuaJadwal->isEmpty()) {
                return response()->json(['response' => 'Jadwal kamu hari ini kosong, Alfarizi. Waktunya bersantai!']);
            }

            $teksJadwal = "Ini jadwalmu: ";
            foreach ($semuaJadwal as $j) {
                $teksJadwal .= $j->kegiatan . " jam " . $j->waktu_mulai . ", ";
            }

            return response()->json(['response' => $teksJadwal]);
        }

        // Jika respons berupa aksi 'bicara' atau ngobrol santai
        if (isset($dataAI['pesan'])) {
            return response()->json(['response' => $dataAI['pesan']]);
        }

        // CADANGAN UTAMA: Jika format di atas lolos tapi masih kosong, kirim teks mentahnya
        return response()->json([
            'response' => $teksBalasan ?? 'Halo Alfarizi, sistem merespons tapi teks kosong.'
        ]);
    }
    }