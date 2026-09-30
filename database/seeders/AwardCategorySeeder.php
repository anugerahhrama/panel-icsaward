<?php

namespace Database\Seeders;

use App\Enums\ApplicantType;
use App\Models\AwardCategory;
use Illuminate\Database\Seeder;

class AwardCategorySeeder extends Seeder
{
    /**
     * The award categories from the concept deck (update 2026-09-30), in display order.
     *
     * @var list<array{name: string, description: string, applicant_type: ApplicantType}>
     */
    private const array CATEGORIES = [
        [
            'name' => 'Best Renewable Energy Initiative',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang telah mengadopsi dan menginvestasikan sumber energi terbarukan seperti energi surya, angin, bioenergi, dan sejenisnya dalam operasional perusahaan. Penilaian menekankan porsi energi terbarukan yang terpakai, bukan sekadar kapasitas terpasang.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Energy Efficiency Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang telah menjalankan program dan teknologi yang secara terukur menurunkan intensitas konsumsi energi.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Decarbonization Strategy',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki strategi penurunan emisi gas rumah kaca menyeluruh menuju target net zero, mencakup inventarisasi emisi, target berbasis sains, dan rencana transisi.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Circular Economy Innovation',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki inovasi yang menerapkan prinsip ekonomi sirkular untuk mengoptimalkan penggunaan sumber daya, mengurangi limbah, dan memperpanjang siklus hidup material dan produk.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Biodiversity Conservation Initiative',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki inisiatif yang berkontribusi pada perlindungan, konservasi, dan pemulihan keanekaragaman hayati serta ekosistem di sekitar wilayah operasional perusahaan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Water Stewardship Initiative',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang mengelola air secara bertanggung jawab di seluruh operasi maupun di daerah aliran sungai operasi bisnis, mencakup efisiensi penggunaan, daur ulang, kualitas air buangan, dan pemulihan sumber air.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Nature-based Solutions',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki inisiatif yang memanfaatkan, melindungi, dan memulihkan ekosistem alami sebagai solusi untuk menjawab tantangan lingkungan dan perubahan iklim, sekaligus menciptakan manfaat bagi masyarakat, lingkungan, dan keberlanjutan bisnis.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Diversity & Inclusion Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang menciptakan lingkungan kerja yang inklusif, setara, dan memberikan kesempatan yang adil bagi tenaga kerja dengan latar belakang yang beragam.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Human Capital Development Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang menerapkan program pengembangan sumber daya manusia yang meningkatkan kompetensi, kapabilitas, kesejahteraan, dan kesiapan tenaga kerja menghadapi kebutuhan masa depan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Occupational Health & Safety Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang menerapkan program yang memperkuat budaya keselamatan dan kesehatan kerja melalui sistem, inovasi, dan praktik yang melindungi pekerja serta menciptakan lingkungan kerja yang aman.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Community Education Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki inisiatif pendidikan yang meningkatkan akses, kualitas, dan kapasitas masyarakat untuk mendukung pengembangan sosial dan ekonomi komunitas secara berkelanjutan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Community Economic Development Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki program yang memperkuat kapasitas ekonomi masyarakat melalui pengembangan usaha, peningkatan keterampilan, penciptaan lapangan kerja, dan penguatan kemandirian ekonomi.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Community Environmental Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki inisiatif lingkungan yang melibatkan dan memberdayakan masyarakat dalam menjaga, memulihkan, dan meningkatkan kualitas lingkungan di wilayah sekitar.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Corporate Philanthropy Program',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang telah memiliki kontribusi sosial perusahaan melalui dukungan sumber daya yang memberikan manfaat nyata dan berkelanjutan bagi masyarakat atau kelompok yang membutuhkan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Shared Value Creation',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki strategi atau inisiatif bisnis yang secara simultan menciptakan nilai bagi perusahaan dan memberikan manfaat positif bagi masyarakat maupun lingkungan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Sustainable Supply Chain',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang mengintegrasikan prinsip keberlanjutan ke dalam pengelolaan rantai pasok melalui praktik yang bertanggung jawab, transparan, dan resilien.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Sustainable Product/Service Innovation',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang mengembangkan produk atau layanan yang menghadirkan solusi berkelanjutan sekaligus menciptakan nilai bagi pelanggan, lingkungan, dan bisnis.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Sustainability Reporting',
            'description' => 'Ditujukan bagi organisasi/perusahaan yang memiliki pelaporan keberlanjutan yang transparan, kredibel, dan informatif dalam mengkomunikasikan kinerja, dampak, serta kemajuan perusahaan dalam agenda keberlanjutan.',
            'applicant_type' => ApplicantType::Organization,
        ],
        [
            'name' => 'Best Sustainability Leader (High Level Management)',
            'description' => 'Ditujukan bagi pemimpin (high level management) yang menunjukkan visi, komitmen, dan kepemimpinan dalam mengintegrasikan keberlanjutan ke dalam strategi bisnis serta mendorong perubahan di dalam dan di luar organisasi.',
            'applicant_type' => ApplicantType::Individual,
        ],
        [
            'name' => 'Best Sustainability Leader (Middle Level Management)',
            'description' => 'Ditujukan bagi pemimpin (middle management) yang menunjukkan visi, komitmen, dan kepemimpinan dalam mengintegrasikan keberlanjutan ke dalam strategi bisnis serta mendorong perubahan di dalam dan di luar organisasi.',
            'applicant_type' => ApplicantType::Individual,
        ],
    ];

    /**
     * Categories renamed since an earlier seed (old name => new name), renamed in place so the row keeps its id and relations.
     *
     * @var array<string, string>
     */
    private const array RENAMED = [
        'Best Sustainability Leader (Middle Management)' => 'Best Sustainability Leader (Middle Level Management)',
    ];

    /**
     * Seed the award categories. Safe to run repeatedly.
     */
    public function run(): void
    {
        foreach (self::RENAMED as $oldName => $newName) {
            if (AwardCategory::query()->where('name', $newName)->doesntExist()) {
                AwardCategory::query()->where('name', $oldName)->update(['name' => $newName]);
            }
        }

        foreach (self::CATEGORIES as $index => $category) {
            AwardCategory::query()->updateOrCreate(
                ['name' => $category['name']],
                [
                    'description' => $category['description'],
                    'applicant_type' => $category['applicant_type'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
