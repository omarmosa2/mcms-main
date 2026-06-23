<?php

namespace Tests\Feature\Frontend;

use Tests\TestCase;

class SidebarNavigationTest extends TestCase
{
    public function test_sidebar_groups_clinic_links_and_uses_single_financial_entry(): void
    {
        $sidebar = file_get_contents(resource_path('js/components/AppSidebar.vue'));

        $this->assertIsString($sidebar);

        $mainNavItems = $this->extractBlock($sidebar, 'const mainNavItems', '] as MainNavItem[]');
        $sectionMetadata = $this->extractBlock($sidebar, 'const sectionMetadata', '];');

        $this->assertMatchesRegularExpression("/title: 'المالية',[\\s\\S]*?group: 'management'/", $mainNavItems);
        $this->assertStringNotContainsString("group: 'finance'", $mainNavItems);
        $this->assertStringNotContainsString("title: 'الفواتير'", $mainNavItems);
        $this->assertStringNotContainsString("title: 'المصروفات'", $mainNavItems);
        $this->assertStringNotContainsString("title: 'الصندوق'", $mainNavItems);

        $this->assertMatchesRegularExpression("/title: 'السجلات الطبية',[\\s\\S]*?group: 'clinical'/", $mainNavItems);
        $this->assertMatchesRegularExpression("/title: 'الأمان',[\\s\\S]*?group: 'system'/", $mainNavItems);
        $this->assertStringNotContainsString("group: 'finance'", $sectionMetadata);

        $this->assertTitlesAppearInOrder($sectionMetadata, [
            "key: 'main'",
            "key: 'clinical'",
            "key: 'management'",
            "key: 'system'",
        ]);

        $this->assertStringNotContainsString("key: 'finance'", $sectionMetadata);
        $this->assertStringNotContainsString("key: 'account'", $sectionMetadata);
    }

    /**
     * @param  list<string>  $needles
     */
    private function assertTitlesAppearInOrder(string $subject, array $needles): void
    {
        $lastPosition = -1;

        foreach ($needles as $needle) {
            $position = strpos($subject, $needle);

            $this->assertNotFalse($position, "Expected [{$needle}] to appear.");
            $this->assertGreaterThan($lastPosition, $position, "Expected [{$needle}] to appear after the previous item.");

            $lastPosition = $position;
        }
    }

    private function extractBlock(string $subject, string $startNeedle, string $endNeedle): string
    {
        $start = strpos($subject, $startNeedle);

        $this->assertNotFalse($start, "Expected block start [{$startNeedle}] to exist.");

        $end = strpos($subject, $endNeedle, $start);

        $this->assertNotFalse($end, "Expected block end [{$endNeedle}] to exist.");

        return substr($subject, $start, $end - $start);
    }
}
