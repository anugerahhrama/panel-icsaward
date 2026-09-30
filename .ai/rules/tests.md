---
paths:
    - 'tests/**'
---

# Tests

## Helper rubrik & juri di `tests/Pest.php`

`categoryWithRubric()` (kategori + template 5 kriteria) dan `judgeAssignedTo($category, recused: false)` (juri ber-akun + penugasan, lewat state `JudgeFactory::assignedTo()`) dipakai lintas file. Tambahkan helper bersama di sana, jangan mendefinisikan fungsi global yang sama di dua file test. `SubmissionFactory::qualified()` = siap desk evaluation. `scoreGivenBy($judge, $category)` membuat satu skor desk evaluation (untuk menguji guard data bernilai). (Dashboard juri, 2026-09-30.)

## Dataset closure yang butuh argumen: satu level saja

Pest hanya me-resolve closure dataset tanpa parameter. Untuk skenario yang butuh data dari body test, tulis `'case' => fn (AwardCategory $category) => [...]` lalu panggil `$arrange($category)` di test. Bentuk `fn () => fn (...) => ...` membuat test menerima closure luar (error "Cannot use object of type Closure as array"). (Dashboard juri, 2026-09-30.)

## `submissions` unique (`user_id`, `award_category_id`)

Dua submission untuk user yang sama di kategori yang sama gagal `UNIQUE constraint failed`. Di test yang butuh beberapa submission dalam satu kategori, biarkan factory membuat user baru per submission (jangan `->for($user)` berulang), lalu login sebagai `$submission->user` bila perlu. (Pitching, 2026-09-30.)

## Menguji sesi user yang sudah dihapus: `withSession`, bukan `actingAs`

`actingAs($user)` memasang objek user langsung ke guard, jadi user provider tidak pernah dipanggil dan user soft-deleted tetap "login". Untuk membuktikan sesi lama putus, isi session guard lalu request: `$this->withSession([Auth::guard('web')->getName() => $user->id])->get(...)->assertRedirect(route('login'))`. Untuk menguji login ulang setelah `actingAs`, panggil `Auth::logout()` dulu (middleware `guest` me-redirect user yang masih login). Reference: `tests/Feature/Admin/AdminAccountsTest.php`. (Kelola akun admin, 2026-09-30.)
