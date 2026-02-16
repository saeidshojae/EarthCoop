<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\NajmBahar\Models\ProjectCategory;

class NajmBaharProjectCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            'کشاورزی' => [
                'زراعت' => ['گندم', 'برنج', 'جو'],
                'باغداری' => ['مرکبات', 'سیب', 'خرما'],
                'دامپروری' => ['پرورش دام', 'پرورش طیور'],
            ],
            'صنعت' => [
                'غذایی' => ['بسته بندی', 'فرآوری'],
                'نساجی' => ['پارچه', 'پوشاک'],
                'فلزات' => ['قطعات صنعتی', 'کارگاه های کوچک'],
            ],
            'خدمات' => [
                'گردشگری' => ['اقامت بوم گردی', 'تور محلی'],
                'آموزشی' => ['مهارت آموزی', 'آموزش آنلاین'],
                'بازاریابی' => ['دیجیتال مارکتینگ', 'فروش حضوری'],
            ],
            'فناوری' => [
                'نرم افزار' => ['وب', 'موبایل'],
                'سخت افزار' => ['اینترنت اشیاء', 'ابزار دقیق'],
                'داده و هوش مصنوعی' => ['تحلیل داده', 'اتوماسیون'],
            ],
            'انرژی' => [
                'تجدیدپذیر' => ['خورشیدی', 'بادی'],
                'بهره وری انرژی' => ['بهینه سازی', 'عایق کاری'],
            ],
            'سلامت' => [
                'دارویی' => ['گیاهان دارویی', 'محصولات سلامت'],
                'تجهیزات پزشکی' => ['مصرفی پزشکی', 'عیب یابی'],
            ],
        ];

        $level1Order = 1;

        foreach ($categories as $level1 => $level2Items) {
            $level1Category = ProjectCategory::updateOrCreate(
                [
                    'name' => $level1,
                    'level' => 1,
                    'parent_id' => null,
                ],
                [
                    'status' => true,
                    'order' => $level1Order,
                ]
            );

            $level2Order = 1;
            foreach ($level2Items as $level2 => $level3Items) {
                $level2Category = ProjectCategory::updateOrCreate(
                    [
                        'name' => $level2,
                        'level' => 2,
                        'parent_id' => $level1Category->id,
                    ],
                    [
                        'status' => true,
                        'order' => $level2Order,
                    ]
                );

                $level3Order = 1;
                foreach ($level3Items as $level3) {
                    ProjectCategory::updateOrCreate(
                        [
                            'name' => $level3,
                            'level' => 3,
                            'parent_id' => $level2Category->id,
                        ],
                        [
                            'status' => true,
                            'order' => $level3Order,
                        ]
                    );
                    $level3Order++;
                }

                $level2Order++;
            }

            $level1Order++;
        }
    }
}
