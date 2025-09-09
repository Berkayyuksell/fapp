<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    /**
     * Ayarlar sayfası
     */
    public function index()
    {
        $settings = [
            'efatura_username' => Setting::get('efatura_username', ''),
            'efatura_password' => Setting::get('efatura_password', '')
        ];

        return view('settings.index', compact('settings'));
    }

    /**
     * Ayarları kaydet
     */
    public function update(Request $request)
    {
        $request->validate([
            'efatura_username' => 'required|string|max:255',
            'efatura_password' => 'required|string|max:255'
        ]);

        Setting::set('efatura_username', $request->efatura_username, 'string', 'E-Fatura Kullanıcı Adı');
        Setting::set('efatura_password', $request->efatura_password, 'password', 'E-Fatura Şifresi');

        return redirect()->route('settings.index')
                       ->with('success', 'Ayarlar başarıyla kaydedildi!');
    }
}
