<?php

namespace Database\Seeders;

use App\Enums\EmployeeStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\MenuItem;
use App\Models\Promo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocalOperationalDataSeeder extends Seeder
{
    private Collection $branches;

    private Collection $registeredCustomers;

    private Collection $guestCustomers;

    private Collection $employees;

    /** @var array<int, array{item: MenuItem, options: Collection, branch_id: int, weight: int}> */
    private array $menuPool = [];

    private int $orderSeq = 0;

    /** @var array<int, float> */
    private array $deliveryFees = [];

    /** @var Collection<int, Promo> */
    private Collection $promos;

    private int $yearsToSeed = 3;

    // ── Ghanaian name pools ──────────────────────────────────────────────────

    private const MALE = [
        'Kofi', 'Kwame', 'Kwesi', 'Yaw', 'Kojo', 'Kwaku', 'Kwabena',
        'Fiifi', 'Nana', 'Ebo', 'Ekow', 'Paa', 'Nii', 'Kobby', 'Papa',
    ];

    private const FEMALE = [
        'Ama', 'Akua', 'Yaa', 'Efua', 'Adwoa', 'Afia', 'Abena',
        'Akosua', 'Esi', 'Naa', 'Adjoa', 'Araba', 'Maame', 'Serwaa', 'Gifty',
    ];

    private const SURNAMES = [
        'Mensah', 'Owusu', 'Boateng', 'Asante', 'Osei', 'Appiah', 'Agyeman',
        'Amoah', 'Darko', 'Antwi', 'Kumah', 'Adjei', 'Ansah', 'Badu', 'Donkor',
        'Frimpong', 'Gyamfi', 'Kusi', 'Nkrumah', 'Opoku', 'Poku', 'Sarpong',
        'Tetteh', 'Yeboah', 'Acheampong',
    ];

    private const AREAS = [
        'Ashaiman', 'Tema New Town', 'Sakumono', 'Lashibi', 'Dawhenya',
        'Community 25', 'Community 18', 'Baatsona', 'Nungua', 'Spintex',
        'Kpone', 'Tsui Bleoo', 'Tema Community 1', 'Tema Community 2',
    ];

    // =========================================================================
    //  Entry point
    // =========================================================================

    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->error('This seeder is for LOCAL development only.');

            return;
        }

        $this->command->info("Seeding ~{$this->yearsToSeed} years of operational data (local only)...");
        $start = microtime(true);

        $this->boot();
        $this->seedCustomers();
        $this->seedEmployees();
        $this->loadMenu();
        $this->seedOrders();
        $this->seedShifts();
        $this->seedRatings();

        $elapsed = round(microtime(true) - $start, 1);
        $this->command->info("Done in {$elapsed}s.");
    }

    // =========================================================================
    //  Boot — ensure base data exists
    // =========================================================================

    private function boot(): void
    {
        $this->branches = Branch::all();

        if ($this->branches->isEmpty()) {
            $this->command->warn('No branches found — running DatabaseSeeder first...');
            $this->call(DatabaseSeeder::class);
            $this->branches = Branch::all();
        }

        foreach ($this->branches as $b) {
            $ds = $b->activeDeliverySetting();
            $this->deliveryFees[$b->id] = $ds ? (float) $ds->base_delivery_fee : 15.00;
        }

        $this->promos = Promo::all();
        $this->orderSeq = (int) DB::table('orders')->count();
    }

    // =========================================================================
    //  Customers
    // =========================================================================

    private function seedCustomers(): void
    {
        if (Customer::count() >= 50) {
            $this->loadCustomers();
            $this->command->info('  Customers: already seeded ('.Customer::count().')');

            return;
        }

        $registeredCount = $this->yearsToSeed * 40;
        $guestCount = $this->yearsToSeed * 15;
        $names = array_merge(self::MALE, self::FEMALE);

        for ($i = 1; $i <= $registeredCount; $i++) {
            $first = $names[array_rand($names)];
            $last = self::SURNAMES[array_rand(self::SURNAMES)];
            $email = Str::lower($first).'.'.Str::lower($last).$i.'@example.com';
            $phone = '+2332'.rand(0, 9).str_pad((string) rand(0, 9999999), 7, '0', STR_PAD_LEFT);

            $user = User::create([
                'name' => "$first $last",
                'email' => $email,
                'username' => Str::lower($first).$i,
                'phone' => $phone,
                'password' => bcrypt('password'),
                'email_verified_at' => now()->subDays(rand(30, 365)),
            ]);

            $cust = Customer::create(['user_id' => $user->id, 'is_guest' => false]);

            $area = self::AREAS[array_rand(self::AREAS)];
            Address::create([
                'customer_id' => $cust->id,
                'label' => 'Home',
                'full_address' => rand(1, 200)." $area Street, $area, Greater Accra",
                'latitude' => 5.60 + rand(-50, 50) / 1000,
                'longitude' => -0.18 + rand(-50, 50) / 1000,
                'is_default' => true,
            ]);

            if ($i % 4 === 0) {
                $workArea = self::AREAS[array_rand(self::AREAS)];
                Address::create([
                    'customer_id' => $cust->id,
                    'label' => 'Work',
                    'full_address' => rand(1, 50)." Business Road, $workArea, Greater Accra",
                    'latitude' => 5.60 + rand(-50, 50) / 1000,
                    'longitude' => -0.18 + rand(-50, 50) / 1000,
                    'is_default' => false,
                ]);
            }
        }

        for ($i = 1; $i <= $guestCount; $i++) {
            $cust = Customer::create([
                'user_id' => null,
                'is_guest' => true,
                'guest_session_id' => Str::uuid()->toString(),
            ]);

            $area = self::AREAS[array_rand(self::AREAS)];
            Address::create([
                'customer_id' => $cust->id,
                'label' => 'Delivery Address',
                'full_address' => rand(1, 200)." $area, Greater Accra",
                'latitude' => 5.60 + rand(-50, 50) / 1000,
                'longitude' => -0.18 + rand(-50, 50) / 1000,
                'is_default' => true,
            ]);
        }

        $this->loadCustomers();
        $this->command->info("  Customers: {$this->registeredCustomers->count()} registered, {$this->guestCustomers->count()} guests");
    }

    private function loadCustomers(): void
    {
        $all = Customer::with(['user', 'addresses'])->get();
        $this->registeredCustomers = $all->where('is_guest', false)->values();
        $this->guestCustomers = $all->where('is_guest', true)->values();
    }

    // =========================================================================
    //  Employees
    // =========================================================================

    private function seedEmployees(): void
    {
        if (Employee::count() >= 10) {
            $this->employees = Employee::with('user')->get();
            $this->command->info('  Employees: already seeded ('.$this->employees->count().')');

            return;
        }

        $roles = [
            [Role::Manager, 2],
            [Role::SalesStaff, 4],
            [Role::Kitchen, 3],
            [Role::Rider, 3],
            [Role::CallCenter, 1],
        ];

        $empNo = (int) Employee::max('id') + 1;
        $names = array_merge(self::MALE, self::FEMALE);

        foreach ($roles as [$role, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $first = $names[array_rand($names)];
                $last = self::SURNAMES[array_rand(self::SURNAMES)];

                $user = User::create([
                    'name' => "$first $last",
                    'email' => Str::lower("{$first}.{$last}.s{$empNo}").'@cedibites.com',
                    'username' => Str::lower($first)."_s{$empNo}",
                    'phone' => '+2335'.str_pad((string) $empNo, 8, '0', STR_PAD_LEFT),
                    'password' => bcrypt('password'),
                ]);

                $user->syncRoles([$role->value]);

                $emp = Employee::create([
                    'user_id' => $user->id,
                    'employee_no' => 'EMP'.str_pad((string) $empNo, 4, '0', STR_PAD_LEFT),
                    'status' => EmployeeStatus::Active,
                    'hire_date' => now()->subDays(rand(60, 500)),
                    'performance_rating' => round(rand(30, 50) / 10, 1),
                ]);

                $emp->branches()->sync($this->branches->pluck('id'));
                $empNo++;
            }
        }

        $this->employees = Employee::with('user')->get();
        $this->command->info("  Employees: {$this->employees->count()}");
    }

    // =========================================================================
    //  Menu pool
    // =========================================================================

    private function loadMenu(): void
    {
        $weights = [
            'jollof' => 25, 'fried-rice' => 22,
            'fried-rice-jollof-3-drums' => 15, 'drumsticks' => 12,
            'assorted-fried-rice-jollof-noodles-3-drums' => 10,
            'noodles' => 8, 'rotisserie-grilled' => 7,
            'cedi-wraps' => 6, 'banku' => 5,
        ];

        foreach ($this->branches as $branch) {
            $items = MenuItem::where('branch_id', $branch->id)
                ->with(['options' => fn ($q) => $q->where('is_available', true)])
                ->get();

            foreach ($items as $item) {
                if ($item->options->isEmpty()) {
                    continue;
                }

                $this->menuPool[] = [
                    'item' => $item,
                    'options' => $item->options,
                    'branch_id' => $branch->id,
                    'weight' => $weights[$item->slug] ?? 5,
                ];
            }
        }

        $this->command->info('  Menu pool: '.count($this->menuPool).' items loaded');
    }

    // =========================================================================
    //  Orders — the main event (~3 000–4 500 orders over 365 days)
    // =========================================================================

    private function seedOrders(): void
    {
        $startDate = Carbon::now()->subYears($this->yearsToSeed)->startOfDay();
        $endDate = Carbon::now()->startOfDay();
        $totalDays = max((int) $startDate->diffInDays($endDate), 1);

        $salesStaff = $this->employees
            ->filter(fn ($e) => $e->user?->hasAnyRole(['sales_staff', 'manager']))
            ->values();

        $this->command->info("  Generating orders for ~{$totalDays} days ({$this->yearsToSeed} years)...");

        $batchOrders = [];
        $batchMeta = [];
        $totalCount = 0;
        $batchSize = 200;

        $date = $startDate->copy();

        while ($date->lte($endDate)) {
            $dayIndex = (int) $startDate->diffInDays($date);
            $growth = 0.25 + 0.75 * ($dayIndex / $totalDays);
            $dow = $date->dayOfWeek;

            $base = match (true) {
                $dow === 0 => rand(10, 20),  // Sunday
                $dow === 6 => rand(12, 25),  // Saturday
                $dow === 5 => rand(8, 18),   // Friday
                default => rand(5, 14),       // Mon–Thu
            };

            $dailyCount = max(3, (int) round($base * $growth));

            for ($o = 0; $o < $dailyCount; $o++) {
                $this->orderSeq++;
                $orderNumber = $this->nextOrderNumber();

                $hour = $this->randomHour();
                $orderTime = $date->copy()->setTime($hour, rand(0, 59), rand(0, 59));
                $branch = $this->branches->random();

                $isGuest = rand(1, 100) <= 25;
                $customer = $isGuest
                    ? $this->guestCustomers->random()
                    : $this->registeredCustomers->random();

                $source = $this->randomSource();
                $type = $this->randomType($source);
                $payMethod = $this->randomPaymentMethod($source);

                // Pick menu items
                $branchItems = array_filter($this->menuPool, fn ($m) => $m['branch_id'] === $branch->id);
                $pickedCount = $this->randomItemCount();
                $pickedItems = $this->weightedPick($branchItems, $pickedCount);

                $subtotal = 0.0;
                $itemRows = [];

                foreach ($pickedItems as $pick) {
                    $mi = $pick['item'];
                    $opt = $pick['options']->random();
                    $qty = rand(1, 3);
                    $price = (float) $opt->price;
                    $sub = round($qty * $price, 2);
                    $subtotal += $sub;

                    $itemRows[] = [
                        'menu_item_id' => $mi->id,
                        'menu_item_option_id' => $opt->id,
                        'menu_item_snapshot' => json_encode([
                            'id' => $mi->id,
                            'name' => $mi->name,
                            'description' => $mi->description,
                        ]),
                        'menu_item_option_snapshot' => json_encode([
                            'id' => $opt->id,
                            'option_key' => $opt->option_key,
                            'option_label' => $opt->option_label,
                            'display_name' => $opt->display_name,
                            'price' => $price,
                        ]),
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $sub,
                        'special_instructions' => null,
                        'created_at' => $orderTime->toDateTimeString(),
                        'updated_at' => $orderTime->toDateTimeString(),
                    ];
                }

                $deliveryFee = $type === 'delivery' ? $this->deliveryFees[$branch->id] : 0;
                $serviceCharge = $source === 'pos' ? 0 : round($subtotal * 0.025, 2);

                // Apply promo discount to ~12% of orders
                $discount = 0.0;
                $promoId = null;
                $promoName = null;
                if (rand(1, 100) <= 12 && $this->promos->isNotEmpty()) {
                    $promo = $this->promos->random();
                    if ($subtotal >= (float) ($promo->min_order_value ?? 0)) {
                        $promoId = $promo->id;
                        $promoName = $promo->name;
                        $discount = $promo->type === 'percentage'
                            ? round($subtotal * ((float) $promo->value / 100), 2)
                            : (float) $promo->value;
                        if ($promo->max_discount && $discount > (float) $promo->max_discount) {
                            $discount = (float) $promo->max_discount;
                        }
                    }
                }

                $total = round(max(0, $subtotal + $deliveryFee + $serviceCharge - $discount), 2);

                $status = $this->randomStatus($date, $type);
                $statusChain = $this->statusChain($status, $orderTime, $type);

                $addr = $customer->addresses->first();
                $cName = $customer->is_guest ? 'Walk-in Customer' : ($customer->user?->name ?? 'Customer');
                $cPhone = $customer->is_guest ? '+233000000000' : ($customer->user?->phone ?? '+233000000000');
                $assignedEmp = ($source === 'pos' && $salesStaff->isNotEmpty()) ? $salesStaff->random()->id : null;

                $lastStatusTime = ! empty($statusChain) ? end($statusChain)['changed_at'] : $orderTime->toDateTimeString();

                $batchOrders[] = [
                    'order_number' => $orderNumber,
                    'customer_id' => $customer->id,
                    'branch_id' => $branch->id,
                    'assigned_employee_id' => $assignedEmp,
                    'order_type' => $type,
                    'order_source' => $source,
                    'delivery_address' => $type === 'delivery' ? ($addr?->full_address ?? 'Ashaiman, Greater Accra') : null,
                    'delivery_latitude' => $type === 'delivery' ? ($addr?->latitude ?? 5.60) : null,
                    'delivery_longitude' => $type === 'delivery' ? ($addr?->longitude ?? -0.18) : null,
                    'contact_name' => $cName,
                    'contact_phone' => $cPhone,
                    'delivery_note' => null,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'service_charge' => $serviceCharge,
                    'discount' => $discount,
                    'promo_id' => $promoId,
                    'promo_name' => $promoName,
                    'total_amount' => $total,
                    'status' => $status,
                    'estimated_prep_time' => rand(15, 45),
                    'estimated_delivery_time' => $type === 'delivery' ? $orderTime->copy()->addMinutes(rand(30, 60))->toDateTimeString() : null,
                    'actual_delivery_time' => in_array($status, ['delivered', 'completed']) && $type === 'delivery'
                        ? $orderTime->copy()->addMinutes(rand(25, 55))->toDateTimeString()
                        : null,
                    'cancelled_at' => $status === 'cancelled' ? $orderTime->copy()->addMinutes(rand(5, 30))->toDateTimeString() : null,
                    'cancelled_reason' => $status === 'cancelled' ? $this->randomCancelReason() : null,
                    'momo_number' => $payMethod === 'mobile_money' ? $cPhone : null,
                    'created_at' => $orderTime->toDateTimeString(),
                    'updated_at' => $lastStatusTime,
                ];

                $batchMeta[$orderNumber] = [
                    'items' => $itemRows,
                    'statuses' => $statusChain,
                    'payment' => [
                        'customer_id' => $customer->id,
                        'payment_method' => $payMethod,
                        'payment_status' => $status === 'cancelled' ? 'refunded' : 'completed',
                        'amount' => $total,
                        'transaction_id' => 'TXN-'.Str::random(12),
                        'payment_gateway_response' => null,
                        'paid_at' => $status !== 'cancelled' ? $orderTime->toDateTimeString() : null,
                        'refunded_at' => $status === 'cancelled' ? $orderTime->copy()->addMinutes(rand(30, 120))->toDateTimeString() : null,
                        'refund_reason' => $status === 'cancelled' ? 'Order cancelled' : null,
                        'created_at' => $orderTime->toDateTimeString(),
                        'updated_at' => $orderTime->toDateTimeString(),
                    ],
                ];

                $totalCount++;

                // Flush every $batchSize orders to keep memory low
                if (count($batchOrders) >= $batchSize) {
                    $this->flushOrders($batchOrders, $batchMeta);
                    $batchOrders = [];
                    $batchMeta = [];
                }
            }

            $date->addDay();
        }

        // Flush remaining orders
        if (! empty($batchOrders)) {
            $this->flushOrders($batchOrders, $batchMeta);
        }

        $this->command->info("  Orders: {$totalCount}");
    }

    private function flushOrders(array $allOrders, array $orderMeta): void
    {
        foreach (array_chunk($allOrders, 200) as $chunk) {
            DB::table('orders')->insert(array_values($chunk));

            $numbers = array_column($chunk, 'order_number');
            $idMap = DB::table('orders')
                ->whereIn('order_number', $numbers)
                ->pluck('id', 'order_number');

            $itemBatch = [];
            $statusBatch = [];
            $paymentBatch = [];

            foreach ($chunk as $row) {
                $orderId = $idMap[$row['order_number']];
                $meta = $orderMeta[$row['order_number']];

                foreach ($meta['items'] as $item) {
                    $item['order_id'] = $orderId;
                    $itemBatch[] = $item;
                }

                foreach ($meta['statuses'] as $st) {
                    $st['order_id'] = $orderId;
                    $statusBatch[] = $st;
                }

                $pay = $meta['payment'];
                $pay['order_id'] = $orderId;
                $paymentBatch[] = $pay;
            }

            foreach (array_chunk($itemBatch, 500) as $c) {
                DB::table('order_items')->insert($c);
            }

            foreach (array_chunk($statusBatch, 500) as $c) {
                DB::table('order_status_history')->insert($c);
            }

            foreach (array_chunk($paymentBatch, 500) as $c) {
                DB::table('payments')->insert($c);
            }
        }
    }

    // =========================================================================
    //  Shifts (~900 over the year)
    // =========================================================================

    private function seedShifts(): void
    {
        $staffIds = $this->employees
            ->filter(fn ($e) => $e->user?->hasAnyRole(['sales_staff', 'manager', 'kitchen']))
            ->pluck('id')
            ->values()
            ->toArray();

        if (empty($staffIds)) {
            return;
        }

        $startDate = Carbon::now()->subYears($this->yearsToSeed)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        $shifts = [];
        $date = $startDate->copy();

        while ($date->lte($endDate)) {
            foreach ($this->branches as $branch) {
                $shiftCount = rand(2, 3);
                $usedEmployees = [];

                for ($s = 0; $s < $shiftCount; $s++) {
                    $empId = $staffIds[array_rand($staffIds)];
                    $attempts = 0;
                    while (in_array($empId, $usedEmployees) && $attempts < count($staffIds)) {
                        $empId = $staffIds[array_rand($staffIds)];
                        $attempts++;
                    }
                    $usedEmployees[] = $empId;

                    $loginHour = match ($s) {
                        0 => rand(7, 9),
                        1 => rand(12, 14),
                        default => rand(16, 18),
                    };

                    $login = $date->copy()->setTime($loginHour, rand(0, 30));
                    $logout = $login->copy()->addHours(rand(6, 8));
                    $isOpen = $date->isToday() && $s === $shiftCount - 1;

                    $shifts[] = [
                        'employee_id' => $empId,
                        'branch_id' => $branch->id,
                        'login_at' => $login->toDateTimeString(),
                        'logout_at' => $isOpen ? null : $logout->toDateTimeString(),
                        'total_sales' => round(rand(800, 3000) + rand(0, 99) / 100, 2),
                        'order_count' => rand(5, 25),
                        'created_at' => $login->toDateTimeString(),
                        'updated_at' => ($isOpen ? $login : $logout)->toDateTimeString(),
                    ];
                }
            }

            $date->addDay();
        }

        foreach (array_chunk($shifts, 500) as $chunk) {
            DB::table('shifts')->insert($chunk);
        }

        $this->command->info('  Shifts: '.count($shifts));
    }

    // =========================================================================
    //  Ratings
    // =========================================================================

    private function seedRatings(): void
    {
        $completedIds = DB::table('orders')
            ->whereIn('status', ['delivered', 'completed'])
            ->inRandomOrder()
            ->limit(600)
            ->pluck('id');

        $orders = DB::table('orders')
            ->whereIn('id', $completedIds)
            ->get()
            ->keyBy('id');

        $orderItems = DB::table('order_items')
            ->whereIn('order_id', $completedIds)
            ->get()
            ->groupBy('order_id');

        $seen = [];
        $ratings = [];

        foreach ($completedIds as $orderId) {
            $order = $orders[$orderId] ?? null;
            $items = $orderItems[$orderId] ?? collect();

            if (! $order) {
                continue;
            }

            foreach ($items as $oi) {
                $key = $order->customer_id.'-'.$oi->menu_item_id;
                if (isset($seen[$key])) {
                    continue;
                }
                if (rand(1, 100) > 30) {
                    continue;
                }

                $seen[$key] = true;

                $ratings[] = [
                    'customer_id' => $order->customer_id,
                    'menu_item_id' => $oi->menu_item_id,
                    'order_item_id' => $oi->id,
                    'rating' => $this->randomRating(),
                    'created_at' => $order->created_at,
                    'updated_at' => $order->created_at,
                ];
            }
        }

        foreach (array_chunk($ratings, 500) as $chunk) {
            DB::table('menu_item_ratings')->insert($chunk);
        }

        $this->command->info('  Ratings: '.count($ratings));
    }

    // =========================================================================
    //  Randomisation helpers
    // =========================================================================

    private function nextOrderNumber(): string
    {
        $n = $this->orderSeq;
        $numPart = (($n - 1) % 999) + 1;
        $letterIndex = (int) ceil($n / 999) - 1;

        $letters = '';
        $tmp = $letterIndex;

        do {
            $letters = chr(65 + ($tmp % 26)).$letters;
            $tmp = intdiv($tmp, 26) - 1;
        } while ($tmp >= 0);

        return $letters.str_pad((string) $numPart, 3, '0', STR_PAD_LEFT);
    }

    /** Weighted toward lunch (11-14) and dinner (17-21) peaks. */
    private function randomHour(): int
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 5 => rand(8, 10),    // morning
            $r <= 40 => rand(11, 14),  // lunch peak
            $r <= 55 => rand(15, 16),  // afternoon lull
            $r <= 90 => rand(17, 21),  // dinner peak
            default => rand(21, 22),    // late
        };
    }

    private function randomSource(): string
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 45 => 'pos',
            $r <= 70 => 'online',
            $r <= 85 => 'phone',
            $r <= 93 => 'whatsapp',
            default => 'instagram',
        };
    }

    private function randomType(string $source): string
    {
        if ($source === 'pos') {
            return rand(1, 100) <= 70 ? 'pickup' : 'delivery';
        }

        return rand(1, 100) <= 60 ? 'delivery' : 'pickup';
    }

    private function randomPaymentMethod(string $source): string
    {
        if ($source === 'pos') {
            $r = rand(1, 100);

            return match (true) {
                $r <= 40 => 'cash',
                $r <= 80 => 'mobile_money',
                default => 'card',
            };
        }

        $r = rand(1, 100);

        return match (true) {
            $r <= 70 => 'mobile_money',
            $r <= 90 => 'card',
            default => 'cash',
        };
    }

    private function randomStatus(Carbon $date, string $type): string
    {
        $daysAgo = (int) $date->diffInDays(now());

        if ($daysAgo <= 1) {
            $r = rand(1, 100);

            return match (true) {
                $r <= 12 => 'received',
                $r <= 22 => 'accepted',
                $r <= 32 => 'preparing',
                $r <= 42 => $type === 'delivery' ? 'ready' : 'ready_for_pickup',
                $r <= 47 && $type === 'delivery' => 'out_for_delivery',
                $r <= 90 => $type === 'delivery' ? 'delivered' : 'completed',
                default => 'cancelled',
            };
        }

        return rand(1, 100) <= 88
            ? ($type === 'delivery' ? 'delivered' : 'completed')
            : 'cancelled';
    }

    /** Build the status history chain leading to the final status. */
    private function statusChain(string $finalStatus, Carbon $orderTime, string $type): array
    {
        $deliveryFlow = ['received', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered'];
        $pickupFlow = ['received', 'accepted', 'preparing', 'ready_for_pickup', 'completed'];
        $flow = $type === 'delivery' ? $deliveryFlow : $pickupFlow;

        $chain = [];
        $t = $orderTime->copy();

        $addStatus = function (string $st) use (&$chain, &$t): void {
            $chain[] = [
                'status' => $st,
                'notes' => null,
                'changed_by_type' => 'system',
                'changed_by_id' => null,
                'changed_at' => $t->toDateTimeString(),
            ];
        };

        if ($finalStatus === 'cancelled') {
            $cancelAt = rand(0, min(2, count($flow) - 1));
            for ($i = 0; $i <= $cancelAt; $i++) {
                $addStatus($flow[$i]);
                $t->addMinutes(rand(1, 8));
            }
            $addStatus('cancelled');

            return $chain;
        }

        $targetIndex = array_search($finalStatus, $flow);
        if ($targetIndex === false) {
            $addStatus($finalStatus);

            return $chain;
        }

        for ($i = 0; $i <= $targetIndex; $i++) {
            $addStatus($flow[$i]);
            if ($i < $targetIndex) {
                $t->addMinutes(match ($flow[$i]) {
                    'received' => rand(1, 5),
                    'accepted' => rand(2, 8),
                    'preparing' => rand(10, 25),
                    'ready', 'ready_for_pickup' => rand(2, 15),
                    'out_for_delivery' => rand(10, 25),
                    default => rand(1, 5),
                });
            }
        }

        return $chain;
    }

    private function randomCancelReason(): string
    {
        $reasons = [
            'Customer changed their mind',
            'Item out of stock',
            'Long wait time',
            'Incorrect order placed',
            'Customer unreachable for delivery',
            'Duplicate order',
            'Payment issue',
        ];

        return $reasons[array_rand($reasons)];
    }

    private function randomItemCount(): int
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 30 => 1,
            $r <= 65 => 2,
            $r <= 85 => 3,
            default => 4,
        };
    }

    /** @return array<int, array{item: MenuItem, options: Collection, branch_id: int, weight: int}> */
    private function weightedPick(array $pool, int $count): array
    {
        if (empty($pool)) {
            return [];
        }

        $pool = array_values($pool);
        $totalWeight = array_sum(array_column($pool, 'weight'));
        $picked = [];

        for ($i = 0; $i < $count; $i++) {
            $r = rand(1, $totalWeight);
            $cumulative = 0;

            foreach ($pool as $entry) {
                $cumulative += $entry['weight'];
                if ($r <= $cumulative) {
                    $picked[] = $entry;
                    break;
                }
            }
        }

        return $picked;
    }

    /** Rating weighted toward 4–5 stars. */
    private function randomRating(): int
    {
        $r = rand(1, 100);

        return match (true) {
            $r <= 5 => 1,
            $r <= 10 => 2,
            $r <= 25 => 3,
            $r <= 55 => 4,
            default => 5,
        };
    }
}
