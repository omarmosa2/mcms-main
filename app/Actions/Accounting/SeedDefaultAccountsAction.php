<?php

namespace App\Actions\Accounting;

use App\Actions\BaseAction;
use App\Models\Account;
use App\Models\Clinic;
use Illuminate\Support\Collection;

class SeedDefaultAccountsAction extends BaseAction
{
    private const DEFAULT_ACCOUNTS = [
        // الأصول (Assets)
        ['code' => '1000', 'name' => 'الأصول', 'type' => Account::TYPE_ASSET, 'children' => [
            ['code' => '1100', 'name' => 'النقدية', 'type' => Account::TYPE_ASSET],
            ['code' => '1200', 'name' => 'الذمم المدينة', 'type' => Account::TYPE_ASSET],
            ['code' => '1300', 'name' => 'المخزون', 'type' => Account::TYPE_ASSET],
            ['code' => '1400', 'name' => 'الأصول الثابتة', 'type' => Account::TYPE_ASSET],
        ]],
        // الالتزامات (Liabilities)
        ['code' => '2000', 'name' => 'الالتزامات', 'type' => Account::TYPE_LIABILITY, 'children' => [
            ['code' => '2100', 'name' => 'الذمم الدائنة', 'type' => Account::TYPE_LIABILITY],
            ['code' => '2200', 'name' => 'القروض', 'type' => Account::TYPE_LIABILITY],
        ]],
        // حقوق الملكية (Equity)
        ['code' => '3000', 'name' => 'حقوق الملكية', 'type' => Account::TYPE_EQUITY, 'children' => [
            ['code' => '3100', 'name' => 'رأس المال', 'type' => Account::TYPE_EQUITY],
            ['code' => '3200', 'name' => 'الاحتياطيات', 'type' => Account::TYPE_EQUITY],
            ['code' => '3300', 'name' => 'الأرباح المحتجزة', 'type' => Account::TYPE_EQUITY],
        ]],
        // الإيرادات (Revenue)
        ['code' => '4000', 'name' => 'الإيرادات', 'type' => Account::TYPE_REVENUE, 'children' => [
            ['code' => '4100', 'name' => 'إيرادات الخدمات الطبية', 'type' => Account::TYPE_REVENUE],
            ['code' => '4200', 'name' => 'إيراداتMedicamentos', 'type' => Account::TYPE_REVENUE],
            ['code' => '4300', 'name' => 'إيرادات أخرى', 'type' => Account::TYPE_REVENUE],
        ]],
        // المصروفات (Expenses)
        ['code' => '5000', 'name' => 'المصروفات', 'type' => Account::TYPE_EXPENSE, 'children' => [
            ['code' => '5100', 'name' => 'رواتب ومزايا', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5200', 'name' => 'إيجار', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5300', 'name' => 'مصاريف تشغيل', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5400', 'name' => 'مصاريف إدارية', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5500', 'name' => 'مصاريف تسويقية', 'type' => Account::TYPE_EXPENSE],
            ['code' => '5600', 'name' => 'مصروفات أخرى', 'type' => Account::TYPE_EXPENSE],
        ]],
    ];

    public function handle(Clinic $clinic): Collection
    {
        $createdAccounts = new Collection;

        foreach (self::DEFAULT_ACCOUNTS as $accountData) {
            $children = $accountData['children'] ?? [];
            unset($accountData['children']);

            $parentAccount = $this->createAccount($clinic->id, $accountData, null);
            $createdAccounts->push($parentAccount);

            foreach ($children as $childData) {
                $childAccount = $this->createAccount($clinic->id, $childData, $parentAccount->id);
                $createdAccounts->push($childAccount);
            }
        }

        return $createdAccounts;
    }

    private function createAccount(int $clinicId, array $data, ?int $parentId): Account
    {
        return Account::query()->create([
            'clinic_id' => $clinicId,
            'parent_id' => $parentId,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'opening_balance' => 0,
            'is_active' => true,
        ]);
    }
}
