<?php

namespace Database\Seeders;

use App\Enums\AllocationStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ComplaintStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Models\Allocation;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Seat;
use App\Models\SeatApplication;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Visitor;
use App\Notifications\HallAlert;
use App\Services\BillingService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * A realistic hall for demos: 80 seats on 4 floors, about 69 residents,
 * six months of meals and bills, payments, applications, complaints,
 * notices and visitors. All dates are relative to today, and the random
 * generator has a fixed seed, so every run gives the same hall.
 *
 * Demo sign in (password from config hall.demo_password):
 *   provost@hall.test    admin
 *   staff@hall.test      hall staff
 *   student@hall.test    resident with an unpaid bill and an open complaint
 *   applicant@hall.test  student waiting for a seat
 */
class DemoSeeder extends Seeder
{
    private const FIRST_NAMES = [
        'Tanvir', 'Rakib', 'Sakib', 'Mehedi', 'Arif', 'Fahim', 'Nayeem', 'Shakil', 'Imran', 'Rifat', 'Sabbir', 'Tamim',
        'Mahmud', 'Ashik', 'Rashed', 'Sohan', 'Rayhan', 'Fardin', 'Nafis', 'Tahmid', 'Ehsan', 'Jubayer', 'Mushfiq', 'Ratul',
        'Shuvo', 'Asif', 'Habib', 'Kawsar', 'Minhaz', 'Nahid', 'Omar', 'Parvez', 'Riyad', 'Saif', 'Tasnim', 'Yasin',
        'Zahid', 'Anik', 'Rumon', 'Siam', 'Mahir', 'Shafin', 'Abrar', 'Labib', 'Muntasir', 'Raihan', 'Samiul', 'Toufiq',
    ];

    private const LAST_NAMES = [
        'Ahmed', 'Hasan', 'Hossain', 'Islam', 'Rahman', 'Chowdhury', 'Khan', 'Uddin', 'Alam', 'Karim', 'Sarker', 'Miah',
        'Talukder', 'Bhuiyan', 'Mollah', 'Sheikh', 'Kabir', 'Haque', 'Siddique', 'Mahmud', 'Akand', 'Biswas', 'Mondal', 'Faruque',
    ];

    private const DISTRICTS = [
        'Rajshahi', 'Rangpur', 'Dinajpur', 'Bogura', 'Khulna', 'Jashore', 'Barishal', 'Patuakhali', 'Sylhet', 'Moulvibazar',
        'Cumilla', 'Noakhali', 'Feni', 'Chattogram', 'Cox\'s Bazar', 'Mymensingh', 'Jamalpur', 'Tangail', 'Kushtia', 'Pabna',
        'Faridpur', 'Gopalganj', 'Kurigram', 'Thakurgaon', 'Sirajganj', 'Netrokona', 'Bhola', 'Satkhira',
    ];

    /** Undergraduate departments of City University and a made up code used in demo student IDs */
    private const DEPT_CODES = [
        'Computer Science and Engineering' => '15',
        'Electrical and Electronic Engineering' => '23',
        'Mechanical Engineering' => '24',
        'Civil Engineering' => '33',
        'Textile Engineering' => '27',
        'Pharmacy' => '29',
        'Business Administration' => '11',
        'English' => '41',
        'Law' => '26',
        'Agriculture' => '45',
    ];

    /** How many past months get monthly bills */
    private const BILLED_MONTHS = 6;

    private CarbonImmutable $today;

    private User $admin;

    /** @var Collection<int, User> */
    private Collection $staff;

    private int $phoneSeq = 0;

    public function run(BillingService $billing): void
    {
        mt_srand(2026);
        $this->today = CarbonImmutable::today();

        // Bills, allocations and payments made here should not flood the
        // notification bell. A few hand-written ones are added at the end.
        $notifier = Notification::getFacadeRoot();
        Notification::fake();

        // Demo rows set their own created_at and other columns outside $fillable
        Model::unguard();

        DB::transaction(function () use ($billing) {
            $this->createStaff();
            $this->createRooms();
            $students = $this->createStudents();
            $this->createAllocations($students);
            $this->createMealOffs();
            $this->createApplications($students);
            $this->createBills($billing);
            $this->createOneOffBills();
            $this->createComplaints();
            $this->createNotices();
            $this->createVisitors();
        });

        Notification::swap($notifier);
        $this->createNotifications();
        Model::reguard();
    }

    private function createStaff(): void
    {
        $password = Hash::make(config('hall.demo_password'));

        $this->admin = User::query()->create([
            'name' => 'Dr. Mahbubur Rahman', 'email' => 'provost@hall.test', 'phone' => '01711 200100',
            'role' => Role::Admin, 'password' => $password, 'last_login_at' => $this->today->subDay()->setTime(9, 12),
        ]);
        User::query()->create([
            'name' => 'Nusrat Jahan', 'email' => 'assistant.provost@hall.test', 'phone' => '01711 200101',
            'role' => Role::Admin, 'password' => $password,
        ]);

        $this->staff = collect([
            ['Abdul Karim', 'staff@hall.test', '01811 300200'],
            ['Rafiqul Islam', 'dining@hall.test', '01811 300201'],
            ['Jahangir Alam', 'office@hall.test', '01811 300202'],
        ])->map(fn ($s) => User::query()->create([
            'name' => $s[0], 'email' => $s[1], 'phone' => $s[2], 'role' => Role::Staff, 'password' => $password,
            'last_login_at' => $this->today->setTime(8, 40),
        ]));
    }

    private function createRooms(): void
    {
        // [floor, first number, count, capacity, rent, bath, balcony]
        // Every seat costs 1000 a month; electricity (Hall settings) comes on top
        $plan = [
            [1, 101, 4, 4, 1000, false, false],
            [1, 105, 4, 3, 1000, false, true],
            [2, 201, 8, 2, 1000, false, true],
            [2, 209, 2, 4, 1000, true, false],
            [3, 301, 8, 2, 1000, true, true],
            [3, 309, 2, 1, 1000, true, true],
            [4, 401, 6, 1, 1000, true, false],
            [4, 407, 2, 2, 1000, true, true],
        ];

        foreach ($plan as [$floor, $first, $count, $capacity, $rent, $bath, $balcony]) {
            for ($i = 0; $i < $count; $i++) {
                $number = (string) ($first + $i);
                $room = Room::query()->create([
                    'number' => $number,
                    'floor' => $floor,
                    'capacity' => $capacity,
                    'monthly_rent' => $rent,
                    'status' => $number === '305' ? RoomStatus::Maintenance : RoomStatus::Available,
                    'has_attached_bath' => $bath,
                    'has_balcony' => $balcony,
                    'notes' => $number === '305' ? 'Ceiling plaster repair, expected to finish this month' : null,
                ]);
                foreach (Room::seatLabels($capacity) as $label) {
                    $room->seats()->create(['label' => $label]);
                }
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function createStudents(): Collection
    {
        $password = Hash::make(config('hall.demo_password'));
        $students = collect();
        $used = [];

        $make = function (string $name, ?string $email = null) use ($password, &$used): User {
            $department = array_rand(self::DEPT_CODES);
            $year = mt_rand(1, 4);
            $intake = (int) $this->today->format('y') - $year + ($this->today->month >= 7 ? 1 : 0);
            do {
                $studentId = sprintf('%02d%d-%s-%04d', $intake, mt_rand(1, 3), self::DEPT_CODES[$department], mt_rand(100, 2999));
            } while (isset($used[$studentId]));
            $used[$studentId] = true;

            $slug = strtolower(str_replace([' ', '.'], ['.', ''], $name));
            $user = User::query()->create([
                'name' => $name,
                'email' => $email ?? $slug.'.'.substr($studentId, -4).'@student.hall.test',
                'phone' => $this->phone(),
                'role' => Role::Student,
                'password' => $password,
                'last_login_at' => mt_rand(0, 3) ? $this->today->subDays(mt_rand(0, 20))->setTime(mt_rand(8, 23), mt_rand(0, 59)) : null,
            ]);

            $lastName = explode(' ', $name)[1] ?? 'Rahman';
            $user->profile()->create([
                'student_id' => $studentId,
                'department' => $department,
                'program' => match (self::DEPT_CODES[$department]) {
                    '11' => 'BBA', '41' => 'BA', '29' => 'BPharm', '26' => 'LLB', default => 'BSc',
                },
                'session' => (2000 + $intake - 1).'-'.substr((string) (2000 + $intake), 2),
                'year_of_study' => $year,
                'cgpa' => $year === 1 && mt_rand(0, 1) ? null : round(mt_rand(250, 395) / 100, 2),
                'gender' => 'male',
                'date_of_birth' => $this->today->subYears(18 + $year)->subDays(mt_rand(0, 360)),
                'blood_group' => StudentProfile::BLOOD_GROUPS[mt_rand(0, 7)],
                'home_district' => self::DISTRICTS[array_rand(self::DISTRICTS)],
                'permanent_address' => 'Village '.self::LAST_NAMES[array_rand(self::LAST_NAMES)].' Bari, '.self::DISTRICTS[array_rand(self::DISTRICTS)],
                'guardian_name' => 'Md. '.self::FIRST_NAMES[array_rand(self::FIRST_NAMES)].' '.$lastName,
                'guardian_relation' => mt_rand(0, 5) ? 'Father' : 'Mother',
                'guardian_phone' => $this->phone(),
            ]);

            return $user;
        };

        // The two named demo students come first
        $students->push($make('Anik Hasan', 'student@hall.test'));
        $students->push($make('Sakib Hasan', 'applicant@hall.test'));

        $names = [];
        foreach (self::FIRST_NAMES as $first) {
            foreach (self::LAST_NAMES as $last) {
                $names[] = "{$first} {$last}";
            }
        }
        shuffle($names);
        $names = array_values(array_diff($names, ['Anik Hasan', 'Sakib Hasan']));

        for ($i = 0; $i < 86; $i++) {
            $students->push($make($names[$i]));
        }

        // The demo resident gets complete, tidy details
        $students[0]->profile->update([
            'department' => 'Computer Science and Engineering', 'program' => 'BSc', 'year_of_study' => 3,
            'cgpa' => 3.62, 'home_district' => 'Rajshahi', 'guardian_name' => 'Md. Aminul Ahmed', 'guardian_phone' => '01712 456789',
        ]);
        $students[1]->profile->update([
            'department' => 'Electrical and Electronic Engineering', 'program' => 'BSc', 'year_of_study' => 2,
            'cgpa' => 3.48, 'home_district' => 'Kurigram',
        ]);

        return $students;
    }

    /**
     * Index 0 = demo resident, 1 = demo applicant, 2..69 residents,
     * 70..77 former residents, 78..87 applicants.
     *
     * @param  Collection<int, User>  $students
     */
    private function createAllocations(Collection $students): void
    {
        $billStart = $this->today->startOfMonth()->subMonthsNoOverflow(3);
        $october = $this->today->startOfMonth()->subMonthNoOverflow();

        $seats = Seat::query()->with('room')->get()
            ->filter(fn (Seat $s) => $s->room->status === RoomStatus::Available)
            ->shuffle()
            ->values();

        $demoSeat = Seat::query()->whereHas('room', fn ($q) => $q->where('number', '204'))->where('label', 'A')->first();
        $seats = $seats->reject(fn (Seat $s) => $s->id === $demoSeat->id)->values();

        // Demo resident, living here for more than a year
        $this->allocate($students[0], $demoSeat, $this->today->subMonths(14)->startOfMonth());

        // 68 more residents (indexes 2..69). The last five moved in during October.
        $residents = $students->slice(2, 68)->values();
        foreach ($residents as $i => $student) {
            $seat = $seats[$i];
            if ($i >= 63) {
                $start = $october->addDays(mt_rand(0, 9));
            } elseif ($i >= 58) {
                // Moved in during the first billed month, so their first bill is prorated
                $start = $billStart->addDays(mt_rand(5, 20));
            } else {
                $start = $this->today->subDays(mt_rand(120, 700));
            }
            $this->allocate($student, $seat, $start);
        }

        // Two residents moved rooms during the second billed month
        $september = $billStart->addMonthNoOverflow();
        foreach ([$residents[3], $residents[11]] as $k => $mover) {
            $current = $mover->activeAllocation()->first();
            $target = $seats[68 + $k];
            $moveDate = $september->addDays(10 + $k * 5);
            $current->update([
                'status' => AllocationStatus::Ended, 'end_date' => $moveDate->subDay(),
                'end_reason' => 'Transferred to seat '.$target->room->number.'-'.$target->label,
                'ended_by' => $this->admin->id, 'active_seat_id' => null, 'active_user_id' => null,
            ]);
            $this->allocate($mover, $target, $moveDate);
        }

        // Former residents: graduated or left during the billed months
        foreach ($students->slice(70, 8)->values() as $k => $former) {
            $seat = $seats[70 + ($k % 4)];
            $end = $billStart->addDays(mt_rand(3, 55));
            $start = $end->subDays(mt_rand(200, 600));
            Allocation::query()->create([
                'user_id' => $former->id, 'seat_id' => $seat->id, 'start_date' => $start, 'end_date' => $end,
                'status' => AllocationStatus::Ended, 'end_reason' => $k % 3 ? 'Graduated' : 'Left the hall',
                'allocated_by' => $this->admin->id, 'ended_by' => $this->admin->id,
                'created_at' => $start, 'updated_at' => $end,
            ]);
        }
    }

    private function allocate(User $student, Seat $seat, CarbonImmutable $start): Allocation
    {
        return Allocation::query()->create([
            'user_id' => $student->id,
            'seat_id' => $seat->id,
            'start_date' => $start,
            'status' => AllocationStatus::Active,
            'allocated_by' => $this->admin->id,
            'active_seat_id' => $seat->id,
            'active_user_id' => $student->id,
            'created_at' => $start->setTime(11, 0),
            'updated_at' => $start->setTime(11, 0),
        ]);
    }

    private function createMealOffs(): void
    {
        $from = $this->today->startOfMonth()->subMonthsNoOverflow(self::BILLED_MONTHS);
        $to = $this->today->addDays(10);
        $trip = $this->today->startOfMonth()->subMonthNoOverflow()->addDays(17);
        $rows = [];

        foreach (Allocation::query()->get() as $allocation) {
            $start = $allocation->start_date->max($from);
            $end = ($allocation->end_date ?? $to)->min($to);
            if ($start->gt($end)) {
                continue;
            }
            // About a third of the residents went home for five days in mid October
            $goesHome = mt_rand(1, 100) <= 35;

            foreach (CarbonPeriod::create($start, $end) as $period) {
                $day = CarbonImmutable::parse($period);
                $date = $day->toDateString();
                $onTrip = $goesHome && $day->between($trip, $trip->addDays(4));
                foreach (['lunch' => 12, 'dinner' => 6] as $meal => $chance) {
                    if ($onTrip || mt_rand(1, 100) <= $chance) {
                        $rows[$allocation->user_id.'|'.$date.'|'.$meal] = [
                            'user_id' => $allocation->user_id, 'date' => $date, 'meal' => $meal, 'created_at' => $day->subDay()->setTime(20, 0),
                        ];
                    }
                }
            }
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            DB::table('meal_offs')->insert($chunk);
        }
    }

    /**
     * @param  Collection<int, User>  $students
     */
    private function createApplications(Collection $students): void
    {
        $reasons = [
            'My home is in %s, more than 250 km from campus. Travelling every day is impossible and a private mess near the campus costs more than my family can afford.',
            'I am from %s. My father is a farmer and the hall seat will reduce my living cost a lot. I also want a quiet place to study for my semester finals.',
            'I currently share a mess room with five people in Ashulia and it is very hard to study there. My family lives in %s.',
            'My family lives in %s. I have a tuition job near campus in the evening and staying in the hall will save me two hours of travel every day.',
        ];
        $reason = fn (User $s) => sprintf($reasons[array_rand($reasons)], $s->profile->home_district);

        // Approved applications of the five October residents
        foreach ($students->slice(65, 5) as $student) {
            $allocation = $student->activeAllocation()->first();
            $application = SeatApplication::query()->create([
                'user_id' => $student->id, 'preferred_capacity' => mt_rand(0, 1) ? 2 : null, 'reason' => $reason($student),
                'status' => ApplicationStatus::Approved, 'reviewed_by' => $this->admin->id,
                'reviewed_at' => $allocation->start_date->subDays(2), 'decision_note' => 'Collect your key from the hall office.',
                'created_at' => $allocation->start_date->subDays(mt_rand(10, 25)),
            ]);
            $allocation->update(['application_id' => $application->id]);
        }

        // Waiting for review: the demo applicant plus six more
        $waiting = collect([$students[1]])->merge($students->slice(78, 6));
        foreach ($waiting->values() as $k => $student) {
            SeatApplication::query()->create([
                'user_id' => $student->id,
                'preferred_capacity' => [null, 2, 1, 4, 2, null, 3][$k],
                'reason' => $reason($student),
                'special_needs' => $k === 4 ? 'Asthma, a room on a lower floor would help' : null,
                'status' => ApplicationStatus::Pending,
                'created_at' => $this->today->subDays([2, 5, 8, 11, 13, 16, 1][$k])->setTime(mt_rand(9, 21), mt_rand(0, 59)),
            ]);
        }

        // Older decisions
        foreach ($students->slice(84, 3)->values() as $k => $student) {
            SeatApplication::query()->create([
                'user_id' => $student->id, 'preferred_capacity' => 1, 'reason' => $reason($student),
                'status' => ApplicationStatus::Rejected, 'reviewed_by' => $this->admin->id,
                'reviewed_at' => $this->today->subDays(40 + $k),
                'decision_note' => $k === 0
                    ? 'Your home is inside Dhaka city, seats are given first to students from outside Dhaka.'
                    : 'No single room was free in this round. Please apply again for any room size.',
                'created_at' => $this->today->subDays(55 + $k),
            ]);
        }
        SeatApplication::query()->create([
            'user_id' => $students[87]->id, 'reason' => $reason($students[87]), 'status' => ApplicationStatus::Cancelled,
            'created_at' => $this->today->subDays(30),
        ]);
    }

    private function createBills(BillingService $billing): void
    {
        $periods = array_reverse(array_slice($billing->billablePeriods(), 0, self::BILLED_MONTHS));
        $cashier = $this->staff[0];

        foreach ($periods as $index => $period) {
            $billing->generate($period, $this->admin);
            $issued = CarbonImmutable::createFromFormat('!Y-m', $period)->addMonthNoOverflow()->setTime(9, 30);
            Invoice::query()->where('period', $period)->update(['created_at' => $issued, 'updated_at' => $issued]);

            $isLatest = $index === count($periods) - 1;
            $invoices = Invoice::query()->where('period', $period)->with('user')->get();

            foreach ($invoices as $invoice) {
                $isDemo = $invoice->user->email === 'student@hall.test';
                $roll = mt_rand(1, 100);

                if ($isLatest) {
                    // Latest month: due in a few days, a third paid, some waiting for verification
                    if ($isDemo || $roll > 47) {
                        continue;
                    }
                    $roll <= 35
                        ? $this->pay($invoice, $invoice->total, $issued->addDays(mt_rand(0, min(5, max(0, (int) $issued->diffInDays($this->today))))), $cashier)
                        : $this->pending($invoice, $issued->addDays(mt_rand(1, 5)));

                    continue;
                }

                // The month before the latest has the most late payers
                $paidChance = $index === count($periods) - 2 ? 86 : 97;
                if ($isDemo || $roll <= $paidChance) {
                    $this->pay($invoice, $invoice->total, $issued->addDays(mt_rand(1, 14)), $cashier, $isDemo ? PaymentMethod::Bkash : null);
                } elseif ($roll <= $paidChance + 5) {
                    $this->pay($invoice, (int) (round($invoice->total / 2 / 100) * 100), $issued->addDays(mt_rand(3, 12)), $cashier);
                }
            }
        }
    }

    private function createOneOffBills(): void
    {
        $october = $this->today->startOfMonth()->subMonthNoOverflow();

        foreach (SeatApplication::query()->where('status', ApplicationStatus::Approved)->with('user')->get() as $application) {
            $issued = $application->reviewed_at->toImmutable();
            $invoice = Invoice::query()->create([
                'user_id' => $application->user_id, 'kind' => Invoice::KIND_OTHER, 'title' => 'Hall admission and security deposit',
                'due_date' => $issued->addDays(10), 'total' => 8000, 'status' => InvoiceStatus::Unpaid, 'created_by' => $this->admin->id,
                'created_at' => $issued, 'updated_at' => $issued,
            ]);
            $invoice->items()->createMany([
                ['description' => 'Hall admission fee', 'quantity' => 1, 'unit_price' => 5000, 'amount' => 5000],
                ['description' => 'Security deposit (refundable)', 'quantity' => 1, 'unit_price' => 3000, 'amount' => 3000],
            ]);
            $this->pay($invoice, 8000, $issued->addDays(mt_rand(1, 6)), $this->staff[0], PaymentMethod::Cash);
        }

        $student = Allocation::query()->active()->with('user')->orderBy('id')->skip(20)->first()->user;
        $issued = $october->addDays(24)->setTime(12, 0);
        $invoice = Invoice::query()->create([
            'user_id' => $student->id, 'kind' => Invoice::KIND_OTHER, 'title' => 'Damage charge: broken window glass',
            'due_date' => $this->today->addDays(12), 'total' => 800, 'status' => InvoiceStatus::Unpaid, 'created_by' => $this->admin->id,
            'created_at' => $issued, 'updated_at' => $issued,
        ]);
        $invoice->items()->create(['description' => 'Window glass replacement, room '.$student->activeAllocation->seat->room->number, 'quantity' => 1, 'unit_price' => 800, 'amount' => 800]);
    }

    private function pay(Invoice $invoice, int $amount, CarbonImmutable $at, User $cashier, ?PaymentMethod $method = null): void
    {
        $at = $at->min($this->today->subHour())->setTime(mt_rand(9, 20), mt_rand(0, 59));
        $method ??= [PaymentMethod::Bkash, PaymentMethod::Bkash, PaymentMethod::Bkash, PaymentMethod::Nagad, PaymentMethod::Nagad, PaymentMethod::Cash, PaymentMethod::Cash, PaymentMethod::Rocket][mt_rand(0, 7)];
        $isCash = $method === PaymentMethod::Cash;

        Payment::query()->create([
            'invoice_id' => $invoice->id, 'user_id' => $invoice->user_id, 'amount' => $amount, 'method' => $method,
            'transaction_id' => $isCash ? null : $this->trxId(), 'status' => PaymentStatus::Verified,
            'recorded_by' => $isCash ? $cashier->id : null, 'verified_by' => $cashier->id,
            'verified_at' => $isCash ? $at : $at->addHours(mt_rand(2, 20))->min($this->today->subMinutes(30)),
            'created_at' => $at, 'updated_at' => $at,
        ]);

        $invoice->paid += $amount;
        $invoice->status = $invoice->paid >= $invoice->total ? InvoiceStatus::Paid : InvoiceStatus::Partial;
        $invoice->saveQuietly();
    }

    private function pending(Invoice $invoice, CarbonImmutable $at): void
    {
        $at = $at->min($this->today->subHours(2))->setTime(mt_rand(8, 22), mt_rand(0, 59));
        Payment::query()->create([
            'invoice_id' => $invoice->id, 'user_id' => $invoice->user_id, 'amount' => $invoice->total,
            'method' => mt_rand(0, 2) ? PaymentMethod::Bkash : PaymentMethod::Nagad, 'transaction_id' => $this->trxId(),
            'status' => PaymentStatus::Pending, 'note' => mt_rand(0, 1) ? 'Sent from '.$this->phone() : null,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function createComplaints(): void
    {
        $residents = Allocation::query()->active()->with('user', 'seat')->get()->keyBy('user_id');
        $demo = User::query()->where('email', 'student@hall.test')->first();
        $others = $residents->except($demo->id)->values();

        $items = [
            // [category, title, description, priority, status, days ago, staff index or null]
            ['electricity', 'Ceiling fan makes a loud noise', 'The fan in our room makes a grinding sound at full speed and shakes a lot. We are afraid it may fall.', 'medium', 'in_progress', 3, 0, 'demo'],
            ['wifi', 'WiFi not reaching the corner seat', 'The signal is very weak at seat B, video classes keep disconnecting.', 'low', 'resolved', 26, 2, 'demo'],
            ['water', 'No water in the 3rd floor washroom', 'Since this morning there is no water in the east side washroom on the 3rd floor.', 'high', 'open', 0, null, null],
            ['electricity', 'Sparking from the switch board', 'The switch board near the door gives sparks when we turn on the light. Please fix it fast.', 'high', 'open', 1, null, null],
            ['furniture', 'Broken reading table drawer', 'The drawer of the reading table is broken and cannot be locked.', 'low', 'open', 4, null, null],
            ['cleanliness', 'Corridor not cleaned for two days', 'The 1st floor corridor has not been cleaned since Tuesday and smells bad.', 'medium', 'open', 2, null, null],
            ['security', 'Main gate light is off at night', 'The light at the main gate is not working, it is very dark after 10 PM.', 'medium', 'in_progress', 5, 2, null],
            ['water', 'Leaking tap in room washroom', 'The tap leaks all night and wastes water.', 'low', 'in_progress', 6, 0, null],
            ['other', 'Need an extra dustbin on the 4th floor', 'There is only one dustbin for the whole floor.', 'low', 'open', 9, null, null],
            ['wifi', 'Internet down in the reading room', 'No internet in the reading room since yesterday evening.', 'medium', 'resolved', 12, 2, null],
            ['electricity', 'Tube light flickering', 'The tube light flickers and gives headache while studying.', 'low', 'resolved', 18, 0, null],
            ['furniture', 'Bed frame broken', 'One leg of my bed frame is cracked.', 'medium', 'resolved', 21, 0, null],
            ['cleanliness', 'Dining hall tables dirty', 'Tables are not wiped after lunch.', 'medium', 'resolved', 24, 1, null],
            ['water', 'Water filter needs service', 'The drinking water filter on floor 2 tastes strange.', 'high', 'resolved', 30, 1, null],
            ['security', 'Lost room key', 'I lost my room key, need a duplicate.', 'low', 'resolved', 33, 2, null],
            ['electricity', 'Socket not working', 'The socket next to seat C has no power.', 'low', 'resolved', 38, 0, null],
            ['other', 'Mosquitoes in the rooms', 'Please arrange spraying, there are too many mosquitoes in the evening.', 'medium', 'resolved', 42, 1, null],
            ['furniture', 'Locker lock jammed', 'My locker lock is jammed, I cannot open it.', 'low', 'in_progress', 7, 0, null],
        ];

        foreach ($items as $i => [$category, $title, $description, $priority, $status, $daysAgo, $staffIndex, $who]) {
            $allocation = $who === 'demo' ? $residents[$demo->id] : $others[$i * 3 % $others->count()];
            $created = $this->today->subDays($daysAgo)->setTime(mt_rand(7, 22), mt_rand(0, 59))->min(now()->subMinutes(40)->toImmutable());
            $assignee = $staffIndex !== null ? $this->staff[$staffIndex] : null;

            $complaint = Complaint::query()->create([
                'user_id' => $allocation->user_id, 'room_id' => $allocation->seat->room_id, 'category' => $category,
                'title' => $title, 'description' => $description, 'priority' => $priority, 'status' => $status,
                'assigned_to' => $assignee?->id,
                'resolved_at' => $status === 'resolved' ? $created->addHours(mt_rand(5, 60)) : null,
                'created_at' => $created, 'updated_at' => $created,
            ]);

            if ($assignee && $status !== 'open') {
                $complaint->updates()->create([
                    'user_id' => $assignee->id, 'body' => 'We have noted it. Our '.($category === 'electricity' ? 'electrician' : 'team').' will check it.',
                    'from_status' => ComplaintStatus::Open, 'to_status' => ComplaintStatus::InProgress,
                    'created_at' => $created->addHours(2), 'updated_at' => $created->addHours(2),
                ]);
            }
            if ($status === 'resolved') {
                $complaint->updates()->create([
                    'user_id' => $assignee->id, 'body' => 'Fixed. Please let us know if the problem comes back.',
                    'from_status' => ComplaintStatus::InProgress, 'to_status' => ComplaintStatus::Resolved,
                    'created_at' => $complaint->resolved_at, 'updated_at' => $complaint->resolved_at,
                ]);
            }
            if ($who === 'demo' && $status === 'in_progress') {
                $complaint->updates()->create([
                    'user_id' => $allocation->user_id, 'body' => 'It is getting worse at night, please check soon.',
                    'created_at' => $created->addDay(), 'updated_at' => $created->addDay(),
                ]);
            }
        }
    }

    private function createNotices(): void
    {
        $now = now()->toImmutable();
        $saturday = $this->today->next(CarbonImmutable::SATURDAY);
        $notices = [
            [
                'title' => 'Water supply off on '.$saturday->format('l, j F').', 10 AM to 2 PM',
                'body' => "The water reservoir of the hall will be cleaned on {$saturday->format('l, j F')}.\n\nThere will be no water supply from 10:00 AM to 2:00 PM. Please store enough water the night before. Lunch will be served as usual.",
                'category' => 'maintenance', 'priority' => 'urgent', 'is_pinned' => true, 'is_public' => false,
                'published_at' => $now->subHours(5), 'expires_at' => $saturday->setTime(15, 0),
            ],
            [
                'title' => 'Hall bill for '.$this->today->subMonthNoOverflow()->format('F').' is ready',
                'body' => 'Monthly bills for '.$this->today->subMonthNoOverflow()->format('F Y').' are now available in the portal under Bills and payments. Please pay by '.$this->today->startOfMonth()->setDay(10)->format('j F').". You can pay by bKash, Nagad, Rocket or in cash at the hall office.\n\nStudents with dues from earlier months must clear them first.",
                'category' => 'general', 'priority' => 'important', 'is_pinned' => true, 'is_public' => false,
                'published_at' => $this->today->startOfMonth()->setTime(10, 0), 'expires_at' => null,
            ],
            [
                'title' => 'Seat applications open for the next round',
                'body' => "Students who need a hall seat can now apply online. Create an account with your student ID, then press Apply for a seat.\n\nSeats are given by CGPA, year of study and distance from home. The result will be announced within two weeks.",
                'category' => 'academic', 'priority' => 'important', 'is_pinned' => false, 'is_public' => true,
                'published_at' => $now->subDays(6), 'expires_at' => null,
            ],
            [
                'title' => 'New dining menu from this month',
                'body' => "Following the student committee meeting, the dining menu has been updated. Friday lunch now includes chicken biryani. Meal rates stay the same.\n\nRemember: turn off your meals before 10 PM the night before if you will not eat.",
                'category' => 'dining', 'priority' => 'normal', 'is_pinned' => false, 'is_public' => true,
                'published_at' => $this->today->startOfMonth()->setTime(12, 0), 'expires_at' => null,
            ],
            [
                'title' => 'Inter hall football tournament: team registration',
                'body' => 'Residents who want to play for the hall team should give their names to the hall office by next Thursday. Practice starts on the central field at 4 PM.',
                'category' => 'event', 'priority' => 'normal', 'is_pinned' => false, 'is_public' => true,
                'published_at' => $now->subDays(9), 'expires_at' => null,
            ],
            [
                'title' => 'Reading room open until 2 AM during the exam weeks',
                'body' => 'For the semester final exams, the reading room on the ground floor will stay open until 2:00 AM. Please keep it quiet and clean.',
                'category' => 'academic', 'priority' => 'normal', 'is_pinned' => false, 'is_public' => false,
                'published_at' => $now->subDays(14), 'expires_at' => null,
            ],
            [
                'title' => 'Dining schedule during the October holidays',
                'body' => 'The dining hall served only dinner during the holidays.',
                'category' => 'dining', 'priority' => 'normal', 'is_pinned' => false, 'is_public' => false,
                'published_at' => $now->subDays(28), 'expires_at' => $now->subDays(18),
            ],
        ];

        foreach ($notices as $notice) {
            Notice::query()->create([...$notice, 'created_by' => $this->admin->id, 'created_at' => $notice['published_at'], 'updated_at' => $notice['published_at']]);
        }
    }

    private function createVisitors(): void
    {
        $residents = Allocation::query()->active()->pluck('user_id')->values();
        $relations = ['Father', 'Mother', 'Brother', 'Cousin', 'Uncle', 'Friend', 'Sister'];
        $purposes = ['Brought food from home', 'Family visit', 'Gave money for the semester fee', 'Brought winter clothes', null, 'Picked up books', null];

        for ($i = 0; $i < 46; $i++) {
            $in = $this->today->subDays(mt_rand(1, 30))->setTime(mt_rand(9, 19), mt_rand(0, 59));
            $this->visitor($residents[mt_rand(0, $residents->count() - 1)], $relations, $purposes, $in, $in->addMinutes(mt_rand(20, 180)));
        }

        // Three people inside right now
        $now = now()->toImmutable();
        foreach ([25, 70, 115] as $k => $minutesAgo) {
            $this->visitor($residents[($k * 17) % $residents->count()], $relations, $purposes, $now->subMinutes($minutesAgo), null);
        }
    }

    /**
     * @param  list<string>  $relations
     * @param  list<?string>  $purposes
     */
    private function visitor(int $userId, array $relations, array $purposes, CarbonImmutable $in, ?CarbonImmutable $out): void
    {
        $relation = $relations[array_rand($relations)];
        $name = in_array($relation, ['Mother', 'Sister'], true)
            ? ['Rokeya', 'Shahnaz', 'Nasima', 'Farzana', 'Salma', 'Taslima'][mt_rand(0, 5)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)]
            : 'Md. '.self::FIRST_NAMES[array_rand(self::FIRST_NAMES)].' '.self::LAST_NAMES[array_rand(self::LAST_NAMES)];

        Visitor::query()->create([
            'user_id' => $userId, 'visitor_name' => $name, 'phone' => $this->phone(), 'relation' => $relation,
            'purpose' => $purposes[array_rand($purposes)], 'check_in_at' => $in, 'check_out_at' => $out,
            'recorded_by' => $this->staff[2]->id, 'created_at' => $in, 'updated_at' => $out ?? $in,
        ]);
    }

    /**
     * A few notifications so the bell is not empty in a demo.
     */
    private function createNotifications(): void
    {
        $demo = User::query()->where('email', 'student@hall.test')->first();
        $latest = Invoice::query()->where('user_id', $demo->id)->whereNotNull('period')->latest('period')->first();
        $paid = Payment::query()->where('user_id', $demo->id)->latest('verified_at')->first();
        $complaint = Complaint::query()->where('user_id', $demo->id)->where('status', 'in_progress')->first();
        $applicant = User::query()->where('email', 'applicant@hall.test')->first();
        $application = SeatApplication::query()->where('user_id', $applicant->id)->first();
        $urgent = Complaint::query()->where('priority', 'high')->where('status', 'open')->latest()->first();

        $this->notifyAt($demo, new HallAlert('Payment received: '.taka($paid->amount), 'Thank you. '.$paid->invoice->title.' is now fully paid.', route('receipts.show', $paid), 'success'), $paid->verified_at, true);
        $this->notifyAt($demo, new HallAlert('New bill: '.taka($latest->total), $latest->title.'. Please pay by '.$latest->due_date->format('j M Y').'.', route('student.bills.show', $latest), 'warning'), $latest->created_at, false);
        $this->notifyAt($demo, new HallAlert('Complaint In progress', $complaint->reference().' '.$complaint->title, route('student.complaints.show', $complaint), 'info'), $complaint->created_at->addHours(2), false);

        $this->notifyAt($this->admin, new HallAlert('New seat application', $applicant->name.' applied for a seat.', route('manage.applications.show', $application), 'warning'), $application->created_at, false);
        $this->notifyAt($this->admin, new HallAlert('High priority complaint', $urgent->reference().' '.$urgent->title, route('manage.complaints.show', $urgent), 'danger'), $urgent->created_at, false);
        $this->notifyAt($this->staff[0], new HallAlert('Complaint assigned to you', $complaint->reference().' '.$complaint->title, route('manage.complaints.show', $complaint), 'warning'), $complaint->created_at->addHours(2), false);
    }

    private function notifyAt(User $user, HallAlert $alert, $at, bool $read): void
    {
        $user->notify($alert);
        $user->notifications()->latest()->first()?->forceFill([
            'created_at' => $at, 'updated_at' => $at, 'read_at' => $read ? $at : null,
        ])->save();
    }

    private function phone(): string
    {
        $prefix = ['017', '018', '019', '015', '016', '013'][mt_rand(0, 5)];
        $this->phoneSeq++;

        return $prefix.mt_rand(10, 99).' '.str_pad((string) (mt_rand(100000, 999999) + $this->phoneSeq) % 1000000, 6, '0', STR_PAD_LEFT);
    }

    private function trxId(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $id = '';
        for ($i = 0; $i < 10; $i++) {
            $id .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        return $id;
    }
}
