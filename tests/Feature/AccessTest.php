<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsHall;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsHall, RefreshDatabase;

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/manage/rooms')->assertRedirect('/login');
    }

    public function test_students_cannot_open_the_management_area(): void
    {
        $this->actingAs($this->student())->get('/manage/rooms')->assertForbidden();
    }

    public function test_staff_cannot_open_admin_pages(): void
    {
        $staff = $this->staffMember();

        $this->actingAs($staff)->get('/manage/rooms')->assertOk();
        $this->actingAs($staff)->get('/manage/applications')->assertForbidden();
        $this->actingAs($staff)->get('/manage/settings')->assertForbidden();
        $this->actingAs($staff)->get('/manage/invoices/generate')->assertForbidden();
    }

    public function test_a_student_can_register_and_is_sent_to_apply(): void
    {
        $this->post('/register', [
            'name' => 'Nafis Islam',
            'student_id' => '241-15-0001',
            'year_of_study' => 1,
            'department' => 'Computer Science and Engineering',
            'email' => 'nafis@example.test',
            'phone' => '01712 345678',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertRedirect(route('student.apply'));

        $user = User::query()->where('email', 'nafis@example.test')->firstOrFail();
        $this->assertTrue($user->isStudent());
        $this->assertSame('241-15-0001', $user->profile->student_id);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()->student()->create(['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_every_main_page_opens_for_its_role(): void
    {
        $admin = $this->admin();
        foreach (['/dashboard', '/manage/rooms', '/manage/residents', '/manage/applications', '/manage/students', '/manage/meals',
            '/manage/meals/monthly', '/manage/invoices', '/manage/invoices/generate', '/manage/payments', '/manage/complaints',
            '/manage/visitors', '/manage/notices', '/manage/reports', '/manage/settings', '/manage/staff', '/notices', '/notifications', '/profile'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $student = $this->student();
        foreach (['/dashboard', '/apply', '/bills', '/complaints', '/my-visitors', '/notices', '/profile'] as $url) {
            $this->actingAs($student)->get($url)->assertOk();
        }
    }
}
