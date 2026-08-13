<?php

namespace Tests\Feature;

use App\Mail\PublicContactInquiryMail;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicContactEmailTest extends TestCase
{
    public function test_public_contact_sends_to_platform_support_email_without_creating_a_ticket(): void
    {
        config()->set('tradeflow.platform.name', 'Profit Point');
        config()->set('tradeflow.platform.support_email', 'support@example.test');
        app(PlatformSettingsService::class)->forget();
        Mail::fake();

        $response = $this->post(route('contact.store'), [
            'name' => 'Haseeb Jamal',
            'phone' => '+923001234567',
            'email' => 'visitor@example.test',
            'message' => 'I would like to learn more about Profit Point.',
        ]);

        $response->assertRedirect()->assertSessionHas('success', 'Message sent successfully.');
        Mail::assertSent(PublicContactInquiryMail::class, function (PublicContactInquiryMail $mail): bool {
            $this->assertTrue($mail->hasTo('support@example.test'));
            $this->assertSame('New Profit Point Contact Inquiry - Haseeb Jamal', $mail->envelope()->subject);
            $this->assertSame('visitor@example.test', $mail->envelope()->replyTo[0]->address);

            return true;
        });
    }
}
