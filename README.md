# JobPrep — Asisten Wawancara Kerja (Laravel 11)

Fitur: analisis CV, jawab pertanyaan recruiter, riset perusahaan, plus **daftar/masuk dengan email dan Google**.

## Pasang
```bash
composer create-project laravel/laravel jobprep
cd jobprep
composer require smalot/pdfparser laravel/socialite
```
Salin isi folder ini ke proyek (timpa `routes/web.php`), lalu:

1. `config/services.snippet.php` → tambahkan isinya ke `config/services.php`
2. `app/Models/User.snippet.php` → ubah `$fillable` di `app/Models/User.php`
3. Isi `.env`:
```
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite            # atau mysql, sesuai selera

ANTHROPIC_API_KEY=sk-ant-xxxxx
ANTHROPIC_MODEL=claude-sonnet-5-5

GOOGLE_CLIENT_ID=xxxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxxxx
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```
4. `php artisan migrate`
5. `php artisan serve`

## Membuat kredensial Google (sekali saja)
1. Buka https://console.cloud.google.com → buat project.
2. **APIs & Services → OAuth consent screen**: pilih External, isi nama aplikasi dan email, lalu tambahkan akun Anda di *Test users* selama masih mode testing.
3. **Credentials → Create Credentials → OAuth client ID → Web application**.
4. Di **Authorized redirect URIs** isi persis: `http://localhost:8000/auth/google/callback` (untuk produksi, ganti dengan domain Anda yang memakai https).
5. Salin Client ID dan Client Secret ke `.env`.
