<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Message;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MessageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Role $role;

    protected Branch $kampala;

    protected Branch $mbarara;

    protected Department $litigation;

    protected Department $finance;

    protected User $sender;

    protected User $litigationUser;

    protected User $financeUser;

    protected User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->role = Role::findOrCreate('Messenger', 'web');
        foreach (['messages.index', 'messages.store', 'messages.show', 'messages.reply', 'messages.read'] as $permissionName) {
            $this->role->givePermissionTo(Permission::findOrCreate($permissionName, 'web'));
        }

        $this->kampala = Branch::create(['name' => 'Kampala', 'code' => 'KLA', 'is_active' => true]);
        $this->mbarara = Branch::create(['name' => 'Mbarara', 'code' => 'MBR', 'is_active' => true]);
        $this->litigation = Department::create(['name' => 'Litigation', 'branch_id' => $this->kampala->id]);
        $this->finance = Department::create(['name' => 'Finance', 'branch_id' => $this->mbarara->id]);

        $this->sender = $this->activeUser('Sender Advocate', 'sender@example.test', $this->kampala, $this->litigation);
        $this->litigationUser = $this->activeUser('Litigation Officer', 'litigation@example.test', $this->kampala, $this->litigation);
        $this->financeUser = $this->activeUser('Finance Officer', 'finance@example.test', $this->mbarara, $this->finance);
        $this->inactiveUser = $this->activeUser('Inactive Staff', 'inactive@example.test', $this->kampala, $this->litigation, 'inactive');
    }

    public function test_department_message_reaches_active_department_members_and_accepts_replies(): void
    {
        Storage::fake('local');

        $this->actingAs($this->sender)
            ->post(route('messages.store'), [
                'audience_type' => 'department',
                'department_id' => $this->litigation->id,
                'title' => 'Court diary update',
                'body' => 'Please update all hearing notes before close of business.',
                'allow_replies' => '1',
                'attachments' => [
                    UploadedFile::fake()->create('hearing-notes.pdf', 64, 'application/pdf'),
                ],
            ])
            ->assertSessionHas('status', 'Message sent.');

        $conversation = Conversation::with(['participants', 'messages'])->first();

        $this->assertSame('department', $conversation->audience_type);
        $this->assertEqualsCanonicalizing(
            [$this->sender->id, $this->litigationUser->id],
            $conversation->participants->pluck('user_id')->all()
        );
        $this->assertFalse($conversation->participants->pluck('user_id')->contains($this->financeUser->id));
        $this->assertFalse($conversation->participants->pluck('user_id')->contains($this->inactiveUser->id));
        $this->assertSame('Please update all hearing notes before close of business.', $conversation->messages->first()->body);
        $this->assertSame(1, $conversation->messages->first()->attachments()->count());

        $this->actingAs($this->litigationUser)
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('kfms-chat-shell')
            ->assertSee('Court diary update')
            ->assertSee('hearing-notes.pdf')
            ->assertSee('Please update all hearing notes before close of business.');

        $conversation->refresh();
        $this->assertNotNull($conversation->participants()->where('user_id', $this->litigationUser->id)->value('last_read_at'));

        $this->actingAs($this->litigationUser)
            ->post(route('messages.reply', $conversation), [
                'body' => 'The litigation team has updated the diary.',
                'attachments' => [
                    UploadedFile::fake()->create('voice-note.webm', 32, 'audio/webm'),
                ],
            ])
            ->assertSessionHas('status', 'Reply sent.');

        $this->assertSame(2, $conversation->messages()->count());
        $this->assertSame('The litigation team has updated the diary.', Message::where('conversation_id', $conversation->id)->latest('id')->first()->body);
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->latest('id')->first()->attachments()->count());
    }

    public function test_firm_broadcast_requires_broadcast_permission(): void
    {
        $this->actingAs($this->sender)
            ->post(route('messages.store'), [
                'audience_type' => 'firm',
                'title' => 'Firm notice',
                'body' => 'This should be guarded.',
                'allow_replies' => '1',
            ])
            ->assertForbidden();

        $this->role->givePermissionTo(Permission::findOrCreate('messages.broadcast', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($this->sender)
            ->post(route('messages.store'), [
                'audience_type' => 'firm',
                'title' => 'Firm notice',
                'body' => 'All active users should receive this.',
                'allow_replies' => '1',
            ])
            ->assertSessionHas('status', 'Message sent.');

        $conversation = Conversation::where('audience_type', 'firm')->with('participants')->first();

        $this->assertTrue($conversation->is_broadcast);
        $this->assertEqualsCanonicalizing(
            [$this->sender->id, $this->litigationUser->id, $this->financeUser->id],
            $conversation->participants->pluck('user_id')->all()
        );
    }

    private function activeUser(string $name, string $email, Branch $branch, Department $department, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);

        $user->assignRole($this->role);

        StaffProfile::create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'employment_status' => $status,
        ]);

        return $user;
    }
}
