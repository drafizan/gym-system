<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\AccessControllerSetting;
use App\Models\Member;
use App\Models\MemberMembership;
use App\Models\MembershipPackage;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PtPackage;
use App\Models\PtTrainer;
use App\Models\RfidCard;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $permissions = collect([
            'dashboard.view' => 'View dashboard',
            'reports.view' => 'View reports',
            'members.manage' => 'Manage members',
            'memberships.manage' => 'Manage memberships',
            'access.manage' => 'Manage access control',
            'sales.manage' => 'Manage sales',
            'products.manage' => 'Manage products',
            'pt.manage' => 'Manage personal training',
            'users.manage' => 'Manage users and roles',
            'backup.manage' => 'Manage backups',
            'audit.view' => 'View audit trail',
            'settings.manage' => 'Manage settings',
        ])->map(fn (string $label, string $name) => Permission::query()->updateOrCreate([
            'name' => $name,
        ], [
            'label' => $label,
        ]));

        $administrator = Role::query()->updateOrCreate([
            'name' => 'administrator',
        ], [
            'label' => 'Administrator',
        ]);

        $manager = Role::query()->updateOrCreate([
            'name' => 'manager',
        ], [
            'label' => 'Manager',
        ]);

        $cashier = Role::query()->updateOrCreate([
            'name' => 'cashier',
        ], [
            'label' => 'Cashier',
        ]);

        $administrator->permissions()->sync($permissions->pluck('id'));
        $manager->permissions()->sync($permissions->except(['users.manage', 'backup.manage', 'settings.manage'])->pluck('id'));
        $cashier->permissions()->sync($permissions->only(['dashboard.view', 'members.manage', 'sales.manage', 'reports.view', 'pt.manage'])->pluck('id'));

        User::query()->updateOrCreate([
            'email' => 'admin@gorillamutanz.test',
        ], [
            'role_id' => $administrator->id,
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@gorillamutanz.test',
            'password' => Hash::make('Abcd@1234'),
            'is_active' => true,
        ]);

        $cashierOne = User::query()->updateOrCreate([
            'email' => 'cashier1@gorillamutanz.test',
        ], [
            'role_id' => $cashier->id,
            'name' => 'Cashier 1',
            'username' => 'cashier1',
            'email' => 'cashier1@gorillamutanz.test',
            'password' => Hash::make('Abcd@1234'),
            'is_active' => true,
        ]);

        $cashierTwo = User::query()->updateOrCreate([
            'email' => 'cashier2@gorillamutanz.test',
        ], [
            'role_id' => $cashier->id,
            'name' => 'Cashier 2',
            'username' => 'cashier2',
            'email' => 'cashier2@gorillamutanz.test',
            'password' => Hash::make('Abcd@1234'),
            'is_active' => true,
        ]);

        $actualPackages = collect([
            ['name' => 'Walk-in Citizen', 'duration_days' => 1, 'price' => 11, 'is_walk_in' => true, 'access_allowed' => true],
            ['name' => 'Walk-in Student', 'duration_days' => 1, 'price' => 9, 'is_walk_in' => true, 'access_allowed' => true],
            ['name' => 'Walk-in Senior Citizen', 'duration_days' => 1, 'price' => 6, 'is_walk_in' => true, 'access_allowed' => true],
            ['name' => 'Walk-in OKU', 'duration_days' => 1, 'price' => 0, 'is_walk_in' => true, 'access_allowed' => true],
            ['name' => 'Monthly Citizen', 'duration_days' => 30, 'price' => 109, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'Monthly Student', 'duration_days' => 30, 'price' => 89, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'Monthly Senior Citizen', 'duration_days' => 30, 'price' => 65, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'Monthly Special', 'duration_days' => 30, 'price' => 250, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'First-Time Registration Citizen', 'duration_days' => 30, 'price' => 169, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'First-Time Registration Student', 'duration_days' => 30, 'price' => 149, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'First-Time Registration Senior Citizen', 'duration_days' => 30, 'price' => 125, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'First-Time Registration Special', 'duration_days' => 30, 'price' => 310, 'is_walk_in' => false, 'access_allowed' => true],
            ['name' => 'Registration Fee', 'duration_days' => 1, 'price' => 60, 'is_walk_in' => false, 'access_allowed' => false],
        ]);

        MembershipPackage::query()
            ->whereNotIn('name', $actualPackages->pluck('name'))
            ->update(['status' => 'inactive']);

        $actualPackages->each(fn (array $package) => MembershipPackage::query()->updateOrCreate([
            'name' => $package['name'],
        ], [
            ...$package,
            'status' => 'active',
        ]));

        $packages = MembershipPackage::query()->pluck('id', 'name');

        collect([
            [
                'member_no' => 'GMG26060001',
                'full_name' => 'Mohamad Drafizan Bin Drahman',
                'phone' => '+601128520309',
                'email' => 'drafizan@gmail.com',
                'ic_passport_no' => '0000000',
                'gender' => 'male',
                'address' => '63, Jalan Aman Bayan 1, Bandar Tropicana Aman',
                'rfid_card_number' => 'RFID-GMG-0001',
                'package' => 'Monthly Citizen',
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDays(27)->toDateString(),
                'membership_status' => 'active',
                'member_status' => 'active',
            ],
            [
                'member_no' => 'GMG26060002',
                'full_name' => 'Siti Nurhaliza Binti Ahmad',
                'phone' => '+60123334444',
                'email' => 'siti@example.com',
                'ic_passport_no' => '900101101234',
                'gender' => 'female',
                'address' => 'Cyberjaya, Selangor',
                'rfid_card_number' => 'RFID-GMG-0002',
                'package' => 'Monthly Student',
                'start_date' => now()->subDays(83)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
                'membership_status' => 'active',
                'member_status' => 'active',
            ],
            [
                'member_no' => 'GMG26060003',
                'full_name' => 'Ahmad Rizal Bin Hassan',
                'phone' => '+60125556666',
                'email' => 'rizal@example.com',
                'ic_passport_no' => '880505105555',
                'gender' => 'male',
                'address' => 'Puchong, Selangor',
                'rfid_card_number' => 'RFID-GMG-0003',
                'package' => 'Monthly Citizen',
                'start_date' => now()->subDays(40)->toDateString(),
                'end_date' => now()->subDays(11)->toDateString(),
                'membership_status' => 'expired',
                'member_status' => 'active',
            ],
            [
                'member_no' => 'GMG26060004',
                'full_name' => 'Nurul Sharmimie Binti Zainal Abidin',
                'phone' => '+601165252503',
                'email' => 'nurul@example.com',
                'ic_passport_no' => '920202105555',
                'gender' => 'female',
                'address' => 'Klang, Selangor',
                'rfid_card_number' => 'RFID-GMG-0004',
                'package' => 'Monthly Special',
                'start_date' => now()->subDays(120)->toDateString(),
                'end_date' => now()->addDays(244)->toDateString(),
                'membership_status' => 'active',
                'member_status' => 'suspended',
            ],
            [
                'member_no' => 'GMG26060005',
                'full_name' => 'Lim Wei Jian',
                'phone' => '+60137778888',
                'email' => 'lim@example.com',
                'ic_passport_no' => 'A12345678',
                'gender' => 'male',
                'address' => 'Subang Jaya, Selangor',
                'rfid_card_number' => null,
                'package' => 'Walk-in Citizen',
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'membership_status' => 'active',
                'member_status' => 'active',
            ],
        ])->each(function (array $sample) use ($packages, $administrator): void {
            $member = Member::query()->updateOrCreate([
                'phone' => $sample['phone'],
            ], [
                'full_name' => $sample['full_name'],
                'member_no' => $sample['member_no'],
                'email' => $sample['email'],
                'ic_passport_no' => $sample['ic_passport_no'],
                'gender' => $sample['gender'],
                'address' => $sample['address'],
                'rfid_card_number' => $sample['rfid_card_number'],
                'emergency_contact_name' => 'Emergency Contact',
                'emergency_contact_relationship' => 'Family',
                'emergency_contact_phone' => '+60112223333',
                'status' => $sample['member_status'],
                'created_by' => $administrator->id,
                'updated_by' => $administrator->id,
            ]);

            $membership = MemberMembership::query()->updateOrCreate([
                'member_id' => $member->id,
                'membership_package_id' => $packages[$sample['package']],
            ], [
                'start_date' => $sample['start_date'],
                'end_date' => $sample['end_date'],
                'status' => $sample['membership_status'],
                'payment_status' => 'paid',
                'amount' => MembershipPackage::query()->where('name', $sample['package'])->value('price'),
                'created_by' => $administrator->id,
                'updated_by' => $administrator->id,
            ]);

            if ($sample['rfid_card_number']) {
                RfidCard::query()->updateOrCreate([
                    'member_id' => $member->id,
                    'status' => 'active',
                ], [
                    'card_number' => $sample['rfid_card_number'],
                    'member_id' => $member->id,
                    'status' => 'active',
                    'assigned_by' => $administrator->id,
                    'assigned_at' => now(),
                    'remarks' => 'Seeded sample card.',
                ]);
            }
        });

        $actualCategoryDefinitions = collect([
            'Supplements' => 'Protein and workout support items.',
            'Beverages' => 'Ready-to-drink beverages.',
            'Apparel' => 'Gym apparel and merchandise.',
            'Rental' => 'Counter rental items.',
            'Services' => 'Service-based sales such as personal trainer session packages.',
        ]);

        ProductCategory::query()
            ->whereNotIn('name', $actualCategoryDefinitions->keys())
            ->update(['status' => 'inactive']);

        $categories = $actualCategoryDefinitions->map(fn (string $description, string $name) => ProductCategory::query()->updateOrCreate([
            'name' => $name,
        ], [
            'description' => $description,
            'status' => 'active',
        ]));

        $actualProducts = collect([
            ['category' => 'Beverages', 'sku' => 'DRK-WATER-600', 'name' => 'Mineral Water 600ml', 'selling_price' => 1.50, 'stock_quantity' => 60, 'reorder_level' => 12],
            ['category' => 'Beverages', 'sku' => 'DRK-WATER-1500', 'name' => 'Mineral Water 1.5L', 'selling_price' => 3.00, 'stock_quantity' => 40, 'reorder_level' => 8],
            ['category' => 'Beverages', 'sku' => 'DRK-GORILLA-TURBO-TIN', 'name' => 'Gorilla Turbo Tin', 'selling_price' => 4.00, 'stock_quantity' => 36, 'reorder_level' => 10],
            ['category' => 'Supplements', 'sku' => 'SUP-PROTEIN-MASS-1KG', 'name' => 'Protein Mass 1kg', 'selling_price' => 100.00, 'stock_quantity' => 10, 'reorder_level' => 2],
            ['category' => 'Supplements', 'sku' => 'SUP-PROTEIN-WHEY-1KG', 'name' => 'Protein Whey 1kg', 'selling_price' => 130.00, 'stock_quantity' => 10, 'reorder_level' => 2],
            ['category' => 'Supplements', 'sku' => 'SUP-PROTEIN-BLEND-1KG', 'name' => 'Protein Blend 1kg', 'selling_price' => 110.00, 'stock_quantity' => 10, 'reorder_level' => 2],
            ['category' => 'Supplements', 'sku' => 'SUP-BCAA', 'name' => 'BCAA', 'selling_price' => 150.00, 'stock_quantity' => 8, 'reorder_level' => 2],
            ['category' => 'Supplements', 'sku' => 'SUP-CREATINE', 'name' => 'Creatine', 'selling_price' => 100.00, 'stock_quantity' => 8, 'reorder_level' => 2],
            ['category' => 'Apparel', 'sku' => 'APP-SHIRT', 'name' => 'Shirt', 'selling_price' => 80.00, 'stock_quantity' => 20, 'reorder_level' => 5],
            ['category' => 'Rental', 'sku' => 'SRV-RENTAL-TOWEL', 'name' => 'Rental Towel', 'selling_price' => 3.00, 'stock_quantity' => 30, 'reorder_level' => 6],
            ['category' => 'Services', 'sku' => 'SRV-PT-3', 'name' => 'PT Session 3 Pack', 'selling_price' => 270.00, 'stock_quantity' => 9999, 'reorder_level' => 0],
            ['category' => 'Services', 'sku' => 'SRV-PT-6', 'name' => 'PT Session 6 Pack', 'selling_price' => 510.00, 'stock_quantity' => 9999, 'reorder_level' => 0],
            ['category' => 'Services', 'sku' => 'SRV-PT-9', 'name' => 'PT Session 9 Pack', 'selling_price' => 720.00, 'stock_quantity' => 9999, 'reorder_level' => 0],
            ['category' => 'Services', 'sku' => 'SRV-PT-20', 'name' => 'PT Session 20 Pack', 'selling_price' => 1500.00, 'stock_quantity' => 9999, 'reorder_level' => 0],
            ['category' => 'Services', 'sku' => 'SRV-PT-45', 'name' => 'PT Session 45 Pack', 'selling_price' => 3150.00, 'stock_quantity' => 9999, 'reorder_level' => 0],
        ]);

        Product::query()
            ->whereNotIn('sku', $actualProducts->pluck('sku'))
            ->delete();

        $actualProducts->each(fn (array $product) => Product::query()->updateOrCreate([
            'sku' => $product['sku'],
        ], [
            'product_category_id' => $categories[$product['category']]->id,
            'name' => $product['name'],
            'description' => $product['description'] ?? null,
            'selling_price' => $product['selling_price'],
            'stock_quantity' => $product['stock_quantity'],
            'reorder_level' => $product['reorder_level'],
            'status' => 'active',
        ]));

        collect([
            ['name' => 'PT Session 3 Pack', 'sessions_count' => 3, 'price' => 270, 'commission_per_session' => 30],
            ['name' => 'PT Session 6 Pack', 'sessions_count' => 6, 'price' => 510, 'commission_per_session' => 30],
            ['name' => 'PT Session 9 Pack', 'sessions_count' => 9, 'price' => 720, 'commission_per_session' => 30],
            ['name' => 'PT Session 20 Pack', 'sessions_count' => 20, 'price' => 1500, 'commission_per_session' => 30],
            ['name' => 'PT Session 45 Pack', 'sessions_count' => 45, 'price' => 3150, 'commission_per_session' => 30],
        ])->each(fn (array $package) => PtPackage::query()->updateOrCreate([
            'name' => $package['name'],
        ], [
            ...$package,
            'status' => 'active',
        ]));

        collect([
            ['name' => 'Trainer 1', 'phone' => '+60000000001', 'specialization' => 'Strength and conditioning'],
            ['name' => 'Trainer 2', 'phone' => '+60000000002', 'specialization' => 'Weight loss and general fitness'],
        ])->each(fn (array $trainer) => PtTrainer::query()->updateOrCreate([
            'name' => $trainer['name'],
        ], [
            ...$trainer,
            'commission_per_session' => 30,
            'joined_at' => now()->toDateString(),
            'status' => 'active',
        ]));

        $membersByNo = Member::query()->whereIn('member_no', [
            'GMG26060001',
            'GMG26060002',
            'GMG26060003',
            'GMG26060005',
        ])->get()->keyBy('member_no');
        $productsBySku = Product::query()->whereIn('sku', [
            'DRK-WATER-600',
            'DRK-WATER-1500',
            'DRK-GORILLA-TURBO-TIN',
            'SUP-PROTEIN-MASS-1KG',
            'SUP-PROTEIN-WHEY-1KG',
            'SUP-PROTEIN-BLEND-1KG',
            'SUP-BCAA',
            'SUP-CREATINE',
            'APP-SHIRT',
            'SRV-RENTAL-TOWEL',
            'SRV-PT-3',
            'SRV-PT-6',
            'SRV-PT-9',
            'SRV-PT-20',
            'SRV-PT-45',
        ])->get()->keyBy('sku');
        $packagesByName = MembershipPackage::query()->whereIn('name', [
            'Walk-in Citizen',
            'Walk-in Student',
            'Walk-in Senior Citizen',
            'Walk-in OKU',
            'Monthly Citizen',
            'Monthly Student',
            'Monthly Senior Citizen',
            'Monthly Special',
            'First-Time Registration Citizen',
            'First-Time Registration Student',
            'First-Time Registration Senior Citizen',
            'First-Time Registration Special',
            'Registration Fee',
        ])->get()->keyBy('name');

        collect([
            [
                'receipt_no' => 'INV-20260616-9001',
                'completed_at' => now()->subDays(6)->setTime(9, 20),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060001',
                'sale_type' => SaleType::MembershipSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Monthly Citizen', 'description' => 'Monthly Citizen Membership - Mohamad Drafizan Bin Drahman', 'quantity' => 1, 'unit_price' => 109],
                ],
            ],
            [
                'receipt_no' => 'INV-20260616-9002',
                'completed_at' => now()->subDays(6)->setTime(13, 40),
                'cashier' => $cashierOne,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'SUP-PROTEIN-WHEY-1KG', 'description' => 'Protein Whey 1kg x 1', 'quantity' => 1, 'unit_price' => 130],
                ],
            ],
            [
                'receipt_no' => 'INV-20260617-9001',
                'completed_at' => now()->subDays(5)->setTime(10, 10),
                'cashier' => $cashierTwo,
                'member_no' => 'GMG26060005',
                'sale_type' => SaleType::WalkInSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Walk-in Citizen', 'description' => 'Walk-in Citizen Access - Lim Wei Jian', 'quantity' => 1, 'unit_price' => 11],
                ],
            ],
            [
                'receipt_no' => 'INV-20260617-9002',
                'completed_at' => now()->subDays(5)->setTime(18, 5),
                'cashier' => $cashierTwo,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['product' => 'DRK-WATER-600', 'description' => 'Mineral Water 600ml x 5', 'quantity' => 5, 'unit_price' => 1.50],
                    ['product' => 'DRK-GORILLA-TURBO-TIN', 'description' => 'Gorilla Turbo Tin x 1', 'quantity' => 1, 'unit_price' => 4],
                ],
            ],
            [
                'receipt_no' => 'INV-20260618-9001',
                'completed_at' => now()->subDays(4)->setTime(11, 45),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060002',
                'sale_type' => SaleType::MembershipRenewal,
                'payment_method' => PaymentMethod::DebitCreditCard,
                'items' => [
                    ['package' => 'Monthly Student', 'description' => 'Monthly Student Membership Renewal - Siti Nurhaliza Binti Ahmad', 'quantity' => 1, 'unit_price' => 89],
                ],
            ],
            [
                'receipt_no' => 'INV-20260619-9001',
                'completed_at' => now()->subDays(3)->setTime(15, 15),
                'cashier' => $cashierOne,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'SUP-PROTEIN-MASS-1KG', 'description' => 'Protein Mass 1kg x 1', 'quantity' => 1, 'unit_price' => 100],
                    ['product' => 'DRK-GORILLA-TURBO-TIN', 'description' => 'Gorilla Turbo Tin x 2', 'quantity' => 2, 'unit_price' => 4],
                ],
            ],
            [
                'receipt_no' => 'INV-20260620-9001',
                'completed_at' => now()->subDays(2)->setTime(8, 50),
                'cashier' => $cashierTwo,
                'member_no' => 'GMG26060003',
                'sale_type' => SaleType::MembershipRenewal,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Monthly Citizen', 'description' => 'Monthly Citizen Membership Renewal - Ahmad Rizal Bin Hassan', 'quantity' => 1, 'unit_price' => 109],
                ],
            ],
            [
                'receipt_no' => 'INV-20260620-9002',
                'completed_at' => now()->subDays(2)->setTime(16, 25),
                'cashier' => $cashierTwo,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'DRK-WATER-1500', 'description' => 'Mineral Water 1.5L x 4', 'quantity' => 4, 'unit_price' => 3],
                ],
            ],
            [
                'receipt_no' => 'INV-20260621-9001',
                'completed_at' => now()->subDay()->setTime(19, 30),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060005',
                'sale_type' => SaleType::WalkInSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'Walk-in Citizen', 'description' => 'Walk-in Citizen Access - Lim Wei Jian', 'quantity' => 1, 'unit_price' => 11],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9001',
                'completed_at' => now()->setTime(9, 15),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060001',
                'sale_type' => SaleType::MembershipSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['package' => 'First-Time Registration Citizen', 'description' => 'First-Time Registration Citizen - Mohamad Drafizan Bin Drahman', 'quantity' => 1, 'unit_price' => 169],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9002',
                'completed_at' => now()->setTime(10, 5),
                'cashier' => $cashierTwo,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['product' => 'APP-SHIRT', 'description' => 'Shirt x 1', 'quantity' => 1, 'unit_price' => 80],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9003',
                'completed_at' => now()->setTime(11, 30),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060002',
                'sale_type' => SaleType::MembershipRenewal,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['package' => 'Monthly Student', 'description' => 'Monthly Student Membership Renewal - Siti Nurhaliza Binti Ahmad', 'quantity' => 1, 'unit_price' => 89],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9004',
                'completed_at' => now()->setTime(13, 10),
                'cashier' => $cashierTwo,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Cash,
                'items' => [
                    ['product' => 'DRK-WATER-600', 'description' => 'Mineral Water 600ml x 3', 'quantity' => 3, 'unit_price' => 1.50],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9005',
                'completed_at' => now()->setTime(15, 20),
                'cashier' => $cashierOne,
                'member_no' => 'GMG26060005',
                'sale_type' => SaleType::WalkInSale,
                'payment_method' => PaymentMethod::DebitCreditCard,
                'items' => [
                    ['package' => 'Walk-in Citizen', 'description' => 'Walk-in Citizen Access - Lim Wei Jian', 'quantity' => 1, 'unit_price' => 11],
                ],
            ],
            [
                'receipt_no' => 'INV-20260622-9006',
                'completed_at' => now()->setTime(17, 40),
                'cashier' => $cashierTwo,
                'sale_type' => SaleType::ProductSale,
                'payment_method' => PaymentMethod::Qr,
                'items' => [
                    ['product' => 'SRV-RENTAL-TOWEL', 'description' => 'Rental Towel x 2', 'quantity' => 2, 'unit_price' => 3],
                ],
            ],
        ])->each(function (array $sample) use ($membersByNo, $productsBySku, $packagesByName): void {
            /** @var Carbon $completedAt */
            $completedAt = $sample['completed_at'];
            $subtotal = collect($sample['items'])->sum(fn (array $item): float => (float) $item['unit_price'] * (int) $item['quantity']);
            $member = isset($sample['member_no']) ? $membersByNo->get($sample['member_no']) : null;

            $sale = Sale::query()->updateOrCreate([
                'receipt_no' => $sample['receipt_no'],
            ], [
                'member_id' => $member?->id,
                'cashier_id' => $sample['cashier']->id,
                'sale_type' => $sample['sale_type']->value,
                'status' => SaleStatus::Completed->value,
                'subtotal' => $subtotal,
                'discount' => 0,
                'total' => $subtotal,
                'remarks' => 'Seeded POS sample sale.',
                'completed_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

            $sale->items()->delete();
            $sale->payments()->delete();

            foreach ($sample['items'] as $item) {
                $product = isset($item['product']) ? $productsBySku->get($item['product']) : null;
                $package = isset($item['package']) ? $packagesByName->get($item['package']) : null;
                $lineTotal = (float) $item['unit_price'] * (int) $item['quantity'];

                SaleItem::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product?->id,
                    'membership_package_id' => $package?->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $lineTotal,
                    'metadata' => [
                        'seeded' => true,
                        'source' => 'dummy_pos_sales',
                    ],
                    'created_at' => $completedAt,
                    'updated_at' => $completedAt,
                ]);
            }

            SalePayment::query()->create([
                'sale_id' => $sale->id,
                'payment_method' => $sample['payment_method']->value,
                'amount' => $subtotal,
                'reference_no' => $sample['payment_method'] === PaymentMethod::Cash ? null : 'SEED-'.$sale->receipt_no,
                'received_by' => $sample['cashier']->id,
                'paid_at' => $completedAt,
                'created_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);
        });

        collect([
            ['name' => '1st Floor Door', 'host' => '192.168.100.11'],
            ['name' => '2nd Floor Door', 'host' => '192.168.100.12'],
        ])->each(fn (array $setting) => AccessControllerSetting::query()->updateOrCreate([
            'name' => $setting['name'],
        ], [
            'driver' => 'dahua_standalone',
            'host' => $setting['host'],
            'port' => 37777,
            'is_enabled' => true,
            'encrypted_credentials' => [
                'username' => 'admin',
                'password' => null,
                'card_number_format' => 'decimal',
                'bridge_url' => null,
                'bridge_token' => null,
            ],
            'last_sync_status' => null,
            'last_sync_at' => null,
            'last_error' => 'Waiting for Dahua SDK bridge sync.',
        ]));

        app(SystemSettings::class)->setMany(app(SystemSettings::class)->defaults());
    }
}
