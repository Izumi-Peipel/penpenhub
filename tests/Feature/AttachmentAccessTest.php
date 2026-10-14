<?php

namespace Tests\Feature;

use App\Actions\AddComment;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AttachmentAccessTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private User $om;
    private User $dev;
    private Attachment $publicAttachment;
    private Attachment $internalAttachment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorld();

        $this->om = $this->makeMember($this->a, Roles::OUTLET_MANAGER, $this->outletA1);
        $this->dev = $this->makeMember($this->a, Roles::DEVELOPER);

        $ticket = $this->openTicket($this->om, $this->outletA1, $this->posA1, [
            'attachments' => [UploadedFile::fake()->image('foto.jpg')],
        ]);
        $this->publicAttachment = $ticket->attachments()->first();

        $comment = app(AddComment::class)->handle($this->dev, $ticket, 'log internal', true, [
            UploadedFile::fake()->create('internal.log', 5, 'text/plain'),
        ]);
        $this->internalAttachment = $comment->attachments()->first();
    }

    public function test_authorized_user_can_open_a_signed_url(): void
    {
        $this->actingAs($this->om)->get($this->publicAttachment->signedUrl())->assertOk();
    }

    public function test_unsigned_url_is_forbidden(): void
    {
        $this->actingAs($this->om)->get(route('attachments.show', $this->publicAttachment))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->publicAttachment->signedUrl())->assertRedirect(route('login'));
    }

    public function test_signed_url_expires(): void
    {
        $url = $this->publicAttachment->signedUrl();
        $this->travel(15)->minutes();

        $this->actingAs($this->om)->get($url)->assertForbidden();
    }

    public function test_leaked_link_does_not_work_for_another_company(): void
    {
        $ownerB = $this->makeMember($this->b, Roles::OWNER);

        $status = $this->actingAs($ownerB)->get($this->publicAttachment->signedUrl())->status();

        $this->assertContains($status, [403, 404]);
    }

    public function test_internal_note_attachment_is_hidden_from_clients_but_visible_to_engineers(): void
    {
        $url = $this->internalAttachment->signedUrl();

        $this->actingAs($this->om)->get($url)->assertForbidden();
        $this->actingAs($this->dev)->get($url)->assertOk();
    }

    public function test_ticket_page_lists_only_attachments_the_viewer_may_see(): void
    {
        $ticketId = $this->publicAttachment->attachable_id;

        $this->actingAs($this->om)->get(route('tickets.show', $ticketId))
            ->assertOk()->assertSee('foto.jpg')->assertDontSee('internal.log');

        $this->actingAs($this->dev)->get(route('tickets.show', $ticketId))
            ->assertOk()->assertSee('foto.jpg')->assertSee('internal.log');
    }
}
