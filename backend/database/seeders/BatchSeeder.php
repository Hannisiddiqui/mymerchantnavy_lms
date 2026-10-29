<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Test;
use Illuminate\Database\Seeder;

class BatchSeeder extends Seeder
{
    /**
     * Seed initial PhysicsWallah-style batches.
     */
    public function run(): void
    {
        $firstCourse = Course::first();
        $allTests = Test::limit(3)->get();

        $batches = [
            [
                'title' => 'IMU-CET 2027 Live Target Batch',
                'slug' => 'imu-cet-2027-target-batch',
                'batch_code' => '#IMU-2027-LIVE',
                'target_exam' => 'IMU-CET',
                'image_url' => 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?auto=format&fit=crop&w=1200&q=80',
                'description' => 'Comprehensive high-intensity batch for IMU-CET 2027 aspirants. Includes complete syllabus notes, formula sheets, 10,000+ chapterwise MCQs, and full-length all-India mock test series.',
                'highlights' => [
                    'Full Syllabus Notes & Formula Sheets',
                    '10,000+ Chapterwise MCQs & Ladder Problems',
                    'Mock Test Series with Detailed Solutions',
                    'Doubt Support & Maritime Guidance',
                ],
                'mrp_price' => 5999.00,
                'price' => 2999.00,
                'discount_percent' => 50,
                'starts_at' => now()->toDateString(),
                'validity_text' => 'Validity: Till Exam 2027',
                'purchase_url' => 'https://mymerchantnavy.com/pass/',
                'footer_tag' => 'Free Test Series & Question Bank Included',
                'is_live' => true,
                'status' => 'active',
                'sort_order' => 1,
            ],
            [
                'title' => 'DNS Sponsorship Cracker Batch 2027',
                'slug' => 'dns-sponsorship-cracker-batch-2027',
                'batch_code' => '#DNS-CRACKER-2027',
                'target_exam' => 'DNS Sponsorship',
                'image_url' => 'https://images.unsplash.com/photo-1505705694340-019e1e335916?auto=format&fit=crop&w=1200&q=80',
                'description' => 'Focused preparation for top shipping company sponsorship tests (Synergy, Anglo Eastern, Fleet Management, Scorpio, Great Eastern) with company-specific aptitude and technical modules.',
                'highlights' => [
                    'Company-Specific Sponsorship Mock Tests',
                    'Psychometric & Aptitude Ladder Questions',
                    'Shipping Company Interview Questions & Tips',
                    'Ship Familiarization & Maritime Basics',
                ],
                'mrp_price' => 4999.00,
                'price' => 2499.00,
                'discount_percent' => 50,
                'starts_at' => now()->toDateString(),
                'validity_text' => 'Validity: 1 Year / Till Selection',
                'purchase_url' => 'https://mymerchantnavy.com/pass/',
                'footer_tag' => 'Includes Company Mock Papers & Interview Prep',
                'is_live' => true,
                'status' => 'active',
                'sort_order' => 2,
            ],
            [
                'title' => 'G.P. Rating Complete Pass Batch',
                'slug' => 'gp-rating-complete-pass-batch',
                'batch_code' => '#GP-RATING-PASS',
                'target_exam' => 'G.P. Rating',
                'image_url' => 'https://images.unsplash.com/photo-1518241353330-0f7941c2d9b5?auto=format&fit=crop&w=1200&q=80',
                'description' => 'Structured academic preparation for GP Rating entrance and pre-sea training. Covers basic seamanship, mathematics, science, English comprehension, and general maritime safety.',
                'highlights' => [
                    'Complete GP Rating Pre-Sea Syllabus',
                    'Maths, Science & English Chapter Notes',
                    'Practice Ladder with Step-by-Step Solutions',
                    'Real Exam Pattern Mock Tests',
                ],
                'mrp_price' => 3999.00,
                'price' => 1999.00,
                'discount_percent' => 50,
                'starts_at' => now()->toDateString(),
                'validity_text' => 'Validity: 6 Calendar Months',
                'purchase_url' => 'https://mymerchantnavy.com/pass/',
                'footer_tag' => 'Instant LMS Activation on Purchase',
                'is_live' => true,
                'status' => 'active',
                'sort_order' => 3,
            ],
        ];

        foreach ($batches as $data) {
            $batch = Batch::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            if ($firstCourse) {
                $batch->courses()->syncWithoutDetaching([$firstCourse->id]);
            }

            if ($allTests->isNotEmpty()) {
                $batch->tests()->syncWithoutDetaching($allTests->pluck('id'));
            }
        }
    }
}
