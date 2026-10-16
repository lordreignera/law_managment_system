<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CalendarEvent;
use App\Models\PublicHoliday;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CalendarFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions, string $roleName, ?int $branchId = null): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_staff_can_schedule_a_calendar_event(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $user = $this->activeUser(['calendar.index', 'calendar.create', 'calendar.store', 'calendar.show'], 'Advocate', $branch->id);

        $response = $this->actingAs($user)->post(route('calendar.store'), [
            'title' => 'Partner Meeting',
            'type' => 'meeting',
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $event = CalendarEvent::first();

        $this->assertNotNull($event);
        $this->assertSame('Partner Meeting', $event->title);
        $this->assertSame($branch->id, $event->branch_id);
        $this->assertSame([$branch->id], $event->branches()->pluck('branches.id')->all());
        $this->assertSame($user->id, $event->created_by);
        $response->assertRedirect(route('calendar.show', $event));
    }

    public function test_staff_can_schedule_an_event_for_multiple_branches(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $user = $this->activeUser(['calendar.index', 'calendar.create', 'calendar.store', 'calendar.show'], 'Advocate', $branchA->id);
        $branchBUser = $this->activeUser([], 'Advocate', $branchB->id);

        $response = $this->actingAs($user)->post(route('calendar.store'), [
            'title' => 'Joint Planning',
            'type' => 'meeting',
            'branch_ids' => [$branchA->id, $branchB->id],
            'assigned_to' => [$user->id, $branchBUser->id],
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $event = CalendarEvent::first();

        $this->assertNotNull($event);
        $this->assertSame($branchA->id, $event->branch_id);
        $this->assertSame([$branchA->id, $branchB->id], $event->branches()->orderBy('branches.id')->pluck('branches.id')->all());
        $this->assertSame([$user->id, $branchBUser->id], $event->assignees()->orderBy('users.id')->pluck('users.id')->all());
        $response->assertRedirect(route('calendar.show', $event));
    }

    public function test_assignees_must_belong_to_selected_branches(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $branchC = Branch::create(['name' => 'Branch C', 'code' => 'BRC']);
        $user = $this->activeUser(['calendar.create', 'calendar.store'], 'Advocate', $branchA->id);
        $otherBranchUser = $this->activeUser([], 'Advocate', $branchC->id);

        $this->actingAs($user)->get(route('calendar.create'))
            ->assertOk()
            ->assertSee('id="calendar-branch-picker"', false)
            ->assertSee('id="calendar-assignee-picker"', false)
            ->assertSee('data-check-dependent-on="calendar-branch-picker"', false)
            ->assertSee('data-check-all', false)
            ->assertSee('Search branches')
            ->assertSee('Search assigned to (optional)')
            ->assertSee('data-user-branch-id="'.$branchA->id.'"', false)
            ->assertSee('data-user-branch-id="'.$branchC->id.'"', false);

        $this->actingAs($user)->post(route('calendar.store'), [
            'title' => 'Restricted Assignment',
            'type' => 'meeting',
            'branch_ids' => [$branchA->id, $branchB->id],
            'assigned_to' => [$otherBranchUser->id],
            'starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('assigned_to');

        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_calendar_is_branch_scoped(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $branchC = Branch::create(['name' => 'Branch C', 'code' => 'BRC']);

        CalendarEvent::create([
            'title' => 'A Meeting',
            'type' => 'meeting',
            'branch_id' => $branchA->id,
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        CalendarEvent::create([
            'title' => 'B Meeting',
            'type' => 'meeting',
            'branch_id' => $branchB->id,
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        $sharedEvent = CalendarEvent::create([
            'title' => 'Shared Meeting',
            'type' => 'meeting',
            'branch_id' => $branchB->id,
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);
        $sharedEvent->branches()->sync([$branchA->id, $branchB->id]);

        $user = $this->activeUser(['calendar.index'], 'Advocate', $branchA->id);
        $otherUser = $this->activeUser(['calendar.index'], 'Other Advocate', $branchC->id);

        $response = $this->actingAs($user)->get(route('calendar.index'));

        $response->assertOk();
        $response->assertSee('A Meeting');
        $response->assertSee('Shared Meeting');
        $response->assertDontSee('B Meeting');

        $otherResponse = $this->actingAs($otherUser)->get(route('calendar.index'));

        $otherResponse->assertOk();
        $otherResponse->assertDontSee('A Meeting');
        $otherResponse->assertDontSee('Shared Meeting');
    }

    public function test_public_holidays_and_weekends_show_on_calendar(): void
    {
        $user = $this->activeUser(['calendar.index'], 'Advocate', null);

        $month = now()->startOfMonth();
        PublicHoliday::create([
            'name' => 'Test Holiday',
            'date' => $month->copy()->addDays(9),
            'is_recurring' => false,
        ]);

        $response = $this->actingAs($user)->get(route('calendar.index', [
            'm' => $month->month,
            'y' => $month->year,
        ]));

        $response->assertOk();
        $response->assertSee('Test Holiday');
        $response->assertSee('is-weekend', false);
    }
}
