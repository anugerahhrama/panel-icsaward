<?php

namespace Database\Seeders;

use App\Models\AssessmentTemplate;
use App\Models\AwardCategory;
use Illuminate\Database\Seeder;

class AssessmentTemplateSeeder extends Seeder
{
    /**
     * The assessment rubrics from the concept deck, with the categories that use them.
     *
     * @var list<array{name: string, categories: list<string>, criteria: list<array{aspect: string, criteria: string, description: string, weight: int}>}>
     */
    private const array TEMPLATES = [
        [
            'name' => 'Environmental Assessment',
            'categories' => [
                'Best Renewable Energy Initiative',
                'Best Energy Efficiency Program',
                'Best Decarbonization Strategy',
                'Best Circular Economy Innovation',
                'Best Biodiversity Conservation Initiative',
                'Best Water Stewardship Initiative',
                'Best Nature-based Solutions',
            ],
            'criteria' => [
                [
                    'aspect' => 'Strategy & Governance',
                    'criteria' => 'Target, Kebijakan, Anggaran, Akuntabilitas',
                    'description' => 'Inisiatif lahir dari strategi perusahaan, ada target berbatas waktu, kebijakan yang mengikat, alokasi anggaran, dan penanggung jawab di level manajemen.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Implementation & Stakeholder Engagement',
                    'criteria' => 'Sistem Manajemen, Cakupan, Kolaborasi, Transparansi',
                    'description' => 'Kualitas rancangan dan eksekusi program, cakupan operasi yang dijangkau, kemitraan yang relevan, serta keterbukaan data lingkungan.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Measurable Impact',
                    'criteria' => 'Baseline, Tren 3 Tahun, Keandalan Data',
                    'description' => 'Perbaikan nyata pada indikator lingkungan inti dibanding baseline, dengan metodologi yang dijelaskan dan data yang dapat diverifikasi pihak ketiga.',
                    'weight' => 35,
                ],
                [
                    'aspect' => 'Scale & Transformation',
                    'criteria' => 'Replikasi, Integrasi, Jangkauan Rantai Nilai',
                    'description' => 'Bukti inisiatif telah naik kelas dari pilot project: melekat pada operasi inti, direplikasi lintas lokasi, dan meluas ke pemasok, kawasan industri, atau praktik sektor.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Innovation & Uniqueness',
                    'criteria' => 'Kebaruan, Keunikan, Adaptasi',
                    'description' => 'Kebaruan dan keunikan pendekatan dibanding praktik lazim di industri sejenis, termasuk ketepatan mengadaptasi teknologi ke konteks operasi sendiri.',
                    'weight' => 15,
                ],
            ],
        ],
        [
            'name' => 'Workforce & Workplace',
            'categories' => [
                'Best Diversity & Inclusion Program',
                'Best Human Capital Development Program',
                'Best Occupational Health & Safety Program',
            ],
            'criteria' => [
                [
                    'aspect' => 'Strategy & Governance',
                    'criteria' => 'Kebijakan, Target, Akuntabilitas Pimpinan',
                    'description' => 'Kebijakan ketenagakerjaan dan keselamatan yang tegas, target yang terukur, serta akuntabilitas pimpinan dan sumber daya yang memadai untuk menjalankannya.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Implementation & Stakeholder Engagement',
                    'criteria' => 'Sistem Manajemen, Cakupan Kontraktor, Partisipasi Pekerja',
                    'description' => 'Mutu sistem dan program yang dijalankan, termasuk cakupan hingga kontraktor dan pekerja non-tetap, serta mekanisme partisipasi dan pengaduan yang aman bagi pekerja.',
                    'weight' => 25,
                ],
                [
                    'aspect' => 'Measurable Impact',
                    'criteria' => 'Data SDM Terpilah, Indikator Lagging & Leading',
                    'description' => 'Perubahan nyata pada kinerja ketenagakerjaan dan keselamatan.',
                    'weight' => 30,
                ],
                [
                    'aspect' => 'Scale & Transformation',
                    'criteria' => 'Perluasan Standar, Transisi Berkeadilan',
                    'description' => 'Standar diperluas ke kontraktor, pemasok, dan mitra, serta kesiapan menyiapkan tenaga kerja menghadapi transisi ekonomi hijau.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Innovation & Uniqueness',
                    'criteria' => 'Kebaruan, Keunikan, Adaptasi',
                    'description' => 'Kebaruan dan keunikan pendekatan dalam mengelola keselamatan, inklusi, dan pengembangan tenaga kerja dibanding praktik umum di industrinya.',
                    'weight' => 10,
                ],
            ],
        ],
        [
            'name' => 'Community & Social Investment',
            'categories' => [
                'Best Community Education Program',
                'Best Community Economic Development Program',
                'Best Community Environmental Program',
                'Best Corporate Philanthropy Program',
            ],
            'criteria' => [
                [
                    'aspect' => 'Strategy & Governance',
                    'criteria' => 'Social Mapping, Theory of Change, Anggaran Multi-Tahun',
                    'description' => 'Program berangkat dari pemetaan kebutuhan dan berpijak pada teori perubahan yang eksplisit, dengan anggaran multi-tahun dan tata kelola penyaluran yang jelas.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Implementation & Stakeholder Engagement',
                    'criteria' => 'Partisipasi, Inklusi, Kemitraan, Akuntabilitas Dana',
                    'description' => 'Warga terlibat sejak perencanaan, kelembagaan lokal diperkuat, dan program berjalan melalui kemitraan yang akuntabel serta penyaluran dana yang dapat diaudit.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Measurable Impact',
                    'criteria' => 'Outcome vs Baseline, Penerima Manfaat Unik, SROI',
                    'description' => 'Perubahan yang dialami penerima manfaat dibanding baseline — bukan jumlah kegiatan, peserta, atau nilai dana yang disalurkan.',
                    'weight' => 30,
                ],
                [
                    'aspect' => 'Scale & Transformation',
                    'criteria' => 'Replikasi, Adopsi Kebijakan, Green Jobs, Leverage',
                    'description' => 'Program meluas dan diakui di luar perusahaan, serta terhubung dengan agenda transformasi hijau dan kebijakan daerah.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Innovation & Uniqueness',
                    'criteria' => 'Kebaruan, Keunikan, dan Adaptasi',
                    'description' => 'Kebaruan dan keunikan pendekatan pemberdayaan dibanding pola program serupa di wilayah lain, serta ketepatan adaptasinya pada konteks lokal.',
                    'weight' => 15,
                ],
            ],
        ],
        [
            'name' => 'Business Model & Value Chain',
            'categories' => [
                'Best Shared Value Creation',
                'Best Sustainable Supply Chain',
                'Best Sustainable Product/Service Innovation',
            ],
            'criteria' => [
                [
                    'aspect' => 'Strategy & Governance',
                    'criteria' => 'Keterkaitan Strategi Bisnis, Verifikasi Klaim',
                    'description' => 'Keberlanjutan melekat pada strategi bisnis, dengan KPI bisnis dan KPI dampak yang dimiliki unit bisnis.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Implementation & Stakeholder Engagement',
                    'criteria' => 'Desain Model, Uji Tuntas, Kemitraan',
                    'description' => 'Kekuatan rancangan model bisnis, uji tuntas rantai pasok, atau desain produk, beserta kemitraan dan mekanisme akuntabilitas yang menjalankannya.',
                    'weight' => 15,
                ],
                [
                    'aspect' => 'Measurable Impact',
                    'criteria' => 'Nilai Bisnis dan Nilai Sosial/Lingkungan, Keterkaitan Kausal',
                    'description' => 'Nilai terukur bagi perusahaan sekaligus bagi masyarakat atau lingkungan, dengan hubungan sebab-akibat yang jelas di antara keduanya.',
                    'weight' => 25,
                ],
                [
                    'aspect' => 'Scale & Transformation',
                    'criteria' => 'Pertumbuhan, Replikasi, Pergeseran Pasar',
                    'description' => 'Bukti model telah tumbuh, direplikasi, dan mulai menggeser praktik pasar atau industri.',
                    'weight' => 30,
                ],
                [
                    'aspect' => 'Innovation & Uniqueness',
                    'criteria' => 'Kebaruan, Keunikan, dan Adaptasi',
                    'description' => 'Orisinalitas model bisnis, produk, atau layanan dibanding yang tersedia di pasar, serta kemampuan mengadaptasinya ke segmen, wilayah, atau komoditas baru.',
                    'weight' => 15,
                ],
            ],
        ],
        [
            'name' => 'Reporting & Communication',
            'categories' => [
                'Best Sustainability Reporting',
            ],
            'criteria' => [
                [
                    'aspect' => 'Kelengkapan',
                    'criteria' => 'Materialitas, Keterlibatan Pemangku Kepentingan, Strategi, & Konteks Organisasi',
                    'description' => 'Laporan keberlanjutan mencakup seluruh topik material yang relevan, perspektif pemangku kepentingan, dan konteks organisasi untuk memberikan gambaran yang jelas, berimbang, dan terhubung mengenai kinerja keberlanjutan perusahaan.',
                    'weight' => 30,
                ],
                [
                    'aspect' => 'Kredibilitas',
                    'criteria' => 'Assurance, Manajemen, Proses, Tata Kelola, Kinerja, & Keterlibatan Pemangku Kepentingan',
                    'description' => 'Menilai keandalan dan integritas laporan keberlanjutan melalui praktik assurance, proses manajemen, transparansi tata kelola, akurasi kinerja, serta keterlibatan pemangku kepentingan untuk memastikan pengungkapan yang dapat dipercaya dan diverifikasi.',
                    'weight' => 35,
                ],
                [
                    'aspect' => 'Komunikasi',
                    'criteria' => 'Penyajian, Struktur, & Keterlibatan Pemangku Kepentingan',
                    'description' => 'Menilai seberapa efektif laporan keberlanjutan menyampaikan informasi melalui penyajian yang jelas, struktur yang logis, dan komunikasi yang mudah diakses oleh pemangku kepentingan, sehingga konten menarik, transparan, dan mudah dinavigasi.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Multimedia Application',
                    'criteria' => 'Website, Laporan Elektronik, Bentuk Media Lain, & Keterlibatan Pemangku Kepentingan',
                    'description' => 'Menilai bagaimana organisasi memanfaatkan platform digital dan interaktif untuk mengomunikasikan kinerja keberlanjutan, meningkatkan aksesibilitas, serta memperkuat keterlibatan pemangku kepentingan melalui format media yang beragam dan inovatif.',
                    'weight' => 15,
                ],
            ],
        ],
        [
            'name' => 'Individual Leadership',
            'categories' => [
                'Best Sustainability Leader (High Level Management)',
                'Best Sustainability Leader (Middle Level Management)',
            ],
            'criteria' => [
                [
                    'aspect' => 'Visi & Kepemimpinan Strategis',
                    'criteria' => 'Penempatan Strategi, Keputusan Besar',
                    'description' => 'Kemampuan menempatkan keberlanjutan di jantung strategi korporat dan mengambil keputusan besar yang membuktikannya.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Hasil Terukur',
                    'criteria' => 'Kinerja ESG Sebelum–Sesudah, Atribusi',
                    'description' => 'Perbaikan kinerja ESG organisasi selama masa jabatan kandidat, beserta kejelasan keterkaitannya dengan peran kandidat.',
                    'weight' => 30,
                ],
                [
                    'aspect' => 'Transformasi Organisasi & Budaya',
                    'criteria' => 'KPI, Remunerasi, Tata Kelola, Kapabilitas',
                    'description' => 'Sejauh mana keberlanjutan dilembagakan ke dalam sistem organisasi sehingga tidak bergantung pada sosok kandidat.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Pengaruh Ekosistem & Advokasi',
                    'criteria' => 'Peran Industri, Forum Kebijakan, Mentoring',
                    'description' => 'Pengaruh kandidat melampaui batas organisasinya sendiri, pada industri, kebijakan, dan pemimpin lain.',
                    'weight' => 20,
                ],
                [
                    'aspect' => 'Integritas & Keteladanan',
                    'criteria' => 'Konsistensi, Hasil Screening',
                    'description' => 'Kesesuaian antara pernyataan publik dan tindakan nyata, serta bersihnya rekam jejak dari kontroversi etika yang material.',
                    'weight' => 10,
                ],
            ],
        ],
    ];

    /**
     * Seed the assessment templates and assign them to their categories. Safe to run repeatedly.
     */
    public function run(): void
    {
        foreach (self::TEMPLATES as $data) {
            $template = AssessmentTemplate::query()->updateOrCreate(['name' => $data['name']]);

            foreach ($data['criteria'] as $index => $criterion) {
                $template->criteria()->updateOrCreate(['sort_order' => $index + 1], $criterion);
            }

            $template->criteria()->where('sort_order', '>', count($data['criteria']))->delete();

            AwardCategory::query()
                ->whereIn('name', $data['categories'])
                ->update(['assessment_template_id' => $template->id]);
        }
    }
}
