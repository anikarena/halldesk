<?php

use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Manage;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\Student;
use Illuminate\Support\Facades\Route;

/*
| Public
*/
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/announcements/{notice}', [HomeController::class, 'notice'])->name('public.notice');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::post('/profile/telegram', [ProfileController::class, 'connectTelegram'])->name('profile.telegram');
    Route::delete('/profile/telegram', [ProfileController::class, 'disconnectTelegram'])->name('profile.telegram.disconnect');

    Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
    Route::get('/notices/{notice}', [NoticeController::class, 'show'])->name('notices.show');

    Route::get('/rules', RulesController::class)->name('rules');

    // Ask AI, for every signed in user; the tools decide what each role may do
    Route::get('/assistant', [AssistantController::class, 'index'])->name('assistant');
    Route::post('/assistant/message', [AssistantController::class, 'message'])->middleware('throttle:assistant')->name('assistant.message');
    Route::post('/assistant/decide', [AssistantController::class, 'decide'])->middleware('throttle:assistant')->name('assistant.decide');
    Route::post('/assistant/reset', [AssistantController::class, 'reset'])->name('assistant.reset');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');

    // Push alerts for this device (resources/js/app.js, public/sw.js)
    Route::post('/push/subscription', [PushController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscription', [PushController::class, 'destroy'])->name('push.unsubscribe');

    // Printable pages, shared by students (their own) and managers
    Route::get('/print/invoice/{invoice}', [PrintController::class, 'invoice'])->name('print.invoice');
    Route::get('/print/receipt/{payment}', [PrintController::class, 'receipt'])->name('receipts.show');
    Route::get('/print/allotment/{allocation}', [PrintController::class, 'allotment'])->name('print.allotment');

    /*
    | Student area
    */
    Route::middleware('role:student')->name('student.')->group(function () {
        Route::get('/apply', [Student\ApplicationController::class, 'create'])->name('apply');
        Route::post('/apply', [Student\ApplicationController::class, 'store'])->name('apply.store');
        Route::post('/apply/{application}/cancel', [Student\ApplicationController::class, 'cancel'])->name('apply.cancel');

        Route::get('/my-room', [Student\RoomController::class, 'show'])->name('room');

        Route::get('/meals', [Student\MealController::class, 'index'])->name('meals');
        Route::post('/meals/toggle', [Student\MealController::class, 'toggle'])->name('meals.toggle');
        Route::post('/meals/range', [Student\MealController::class, 'range'])->name('meals.range');

        Route::get('/bills', [Student\BillController::class, 'index'])->name('bills.index');
        Route::get('/bills/{invoice}', [Student\BillController::class, 'show'])->name('bills.show');
        Route::post('/bills/{invoice}/pay', [Student\BillController::class, 'pay'])->name('bills.pay');

        Route::get('/complaints', [Student\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/complaints/new', [Student\ComplaintController::class, 'create'])->name('complaints.create');
        Route::post('/complaints', [Student\ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/{complaint}', [Student\ComplaintController::class, 'show'])->name('complaints.show');
        Route::post('/complaints/{complaint}/comment', [Student\ComplaintController::class, 'comment'])->name('complaints.comment');
        Route::post('/complaints/{complaint}/reopen', [Student\ComplaintController::class, 'reopen'])->name('complaints.reopen');

        Route::get('/my-visitors', [Student\VisitorController::class, 'index'])->name('visitors');
    });

    /*
    | Management area: admin and staff
    */
    Route::middleware('role:admin,staff')->prefix('manage')->name('manage.')->group(function () {
        /*
        | Admin only (registered first so "rooms/create" wins over "rooms/{room}")
        */
        Route::middleware('role:admin')->group(function () {
            Route::get('rooms/create', [Manage\RoomController::class, 'create'])->name('rooms.create');
            Route::post('rooms', [Manage\RoomController::class, 'store'])->name('rooms.store');
            Route::get('rooms/{room}/edit', [Manage\RoomController::class, 'edit'])->name('rooms.edit');
            Route::put('rooms/{room}', [Manage\RoomController::class, 'update'])->name('rooms.update');
            Route::delete('rooms/{room}', [Manage\RoomController::class, 'destroy'])->name('rooms.destroy');

            Route::get('applications', [Manage\ApplicationController::class, 'index'])->name('applications.index');
            Route::get('applications/{application}', [Manage\ApplicationController::class, 'show'])->name('applications.show');
            Route::post('applications/{application}/approve', [Manage\ApplicationController::class, 'approve'])->name('applications.approve');
            Route::post('applications/{application}/reject', [Manage\ApplicationController::class, 'reject'])->name('applications.reject');

            Route::get('residents/allocate', [Manage\ResidentController::class, 'create'])->name('residents.create');
            Route::post('residents', [Manage\ResidentController::class, 'store'])->name('residents.store');
            Route::get('residents/{allocation}/transfer', [Manage\ResidentController::class, 'transferForm'])->name('residents.transfer');
            Route::post('residents/{allocation}/transfer', [Manage\ResidentController::class, 'transfer'])->name('residents.transfer.store');
            Route::post('residents/{allocation}/vacate', [Manage\ResidentController::class, 'vacate'])->name('residents.vacate');

            Route::get('students/create', [Manage\StudentController::class, 'create'])->name('students.create');
            Route::post('students', [Manage\StudentController::class, 'store'])->name('students.store');
            Route::get('students/{student}/edit', [Manage\StudentController::class, 'edit'])->name('students.edit');
            Route::put('students/{student}', [Manage\StudentController::class, 'update'])->name('students.update');
            Route::post('students/{student}/toggle', [Manage\StudentController::class, 'toggle'])->name('students.toggle');
            Route::post('students/{student}/password', [Manage\StudentController::class, 'resetPassword'])->name('students.password');

            Route::get('staff', [Manage\StaffController::class, 'index'])->name('staff.index');
            Route::get('staff/create', [Manage\StaffController::class, 'create'])->name('staff.create');
            Route::post('staff', [Manage\StaffController::class, 'store'])->name('staff.store');
            Route::post('staff/{member}/toggle', [Manage\StaffController::class, 'toggle'])->name('staff.toggle');

            Route::get('invoices/create', [Manage\InvoiceController::class, 'create'])->name('invoices.create');
            Route::post('invoices', [Manage\InvoiceController::class, 'store'])->name('invoices.store');
            Route::get('invoices/generate', [Manage\InvoiceController::class, 'generateForm'])->name('invoices.generate');
            Route::post('invoices/generate', [Manage\InvoiceController::class, 'generate'])->name('invoices.generate.store');
            Route::post('invoices/{invoice}/cancel', [Manage\InvoiceController::class, 'cancel'])->name('invoices.cancel');

            Route::get('notices', [Manage\NoticeController::class, 'index'])->name('notices.index');
            Route::get('notices/create', [Manage\NoticeController::class, 'create'])->name('notices.create');
            Route::post('notices', [Manage\NoticeController::class, 'store'])->name('notices.store');
            Route::get('notices/{notice}/edit', [Manage\NoticeController::class, 'edit'])->name('notices.edit');
            Route::put('notices/{notice}', [Manage\NoticeController::class, 'update'])->name('notices.update');
            Route::delete('notices/{notice}', [Manage\NoticeController::class, 'destroy'])->name('notices.destroy');

            Route::get('reports', [Manage\ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/dues.csv', [Manage\ReportController::class, 'duesCsv'])->name('reports.dues');

            Route::get('settings', [Manage\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Manage\SettingController::class, 'update'])->name('settings.update');
        });

        /*
        | Admin and staff
        */
        Route::get('rooms', [Manage\RoomController::class, 'index'])->name('rooms.index');
        Route::get('rooms/{room}', [Manage\RoomController::class, 'show'])->name('rooms.show');

        Route::get('residents', [Manage\ResidentController::class, 'index'])->name('residents.index');

        Route::get('students', [Manage\StudentController::class, 'index'])->name('students.index');
        Route::get('students/{student}', [Manage\StudentController::class, 'show'])->name('students.show');

        Route::get('meals', [Manage\MealController::class, 'index'])->name('meals.index');
        Route::get('meals/monthly', [Manage\MealController::class, 'monthly'])->name('meals.monthly');

        Route::get('invoices', [Manage\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('invoices/{invoice}', [Manage\InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('invoices/{invoice}/payments', [Manage\InvoiceController::class, 'recordPayment'])->name('invoices.payments.store');

        Route::get('payments', [Manage\PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/verify', [Manage\PaymentController::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/reject', [Manage\PaymentController::class, 'reject'])->name('payments.reject');

        Route::get('complaints', [Manage\ComplaintController::class, 'index'])->name('complaints.index');
        Route::get('complaints/{complaint}', [Manage\ComplaintController::class, 'show'])->name('complaints.show');
        Route::put('complaints/{complaint}', [Manage\ComplaintController::class, 'update'])->name('complaints.update');
        Route::post('complaints/{complaint}/comment', [Manage\ComplaintController::class, 'comment'])->name('complaints.comment');

        Route::get('visitors', [Manage\VisitorController::class, 'index'])->name('visitors.index');
        Route::post('visitors', [Manage\VisitorController::class, 'store'])->name('visitors.store');
        Route::post('visitors/{visitor}/checkout', [Manage\VisitorController::class, 'checkout'])->name('visitors.checkout');
    });
});
