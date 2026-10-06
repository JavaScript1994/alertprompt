<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CampaignRecipientStatus;
use App\Enums\Channel;
use App\Enums\SkipReason;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;

class CampaignAudienceService
{
    /**
     * Matricula explícitamente los contactos elegidos para esta campaña —
     * nunca "todos los contactos del tenant". Evalúa consentimiento vigente
     * e identificador válido en el momento de armar la audiencia. Idempotente
     * vía el unique(campaign_id, contact_id) de campaign_recipients.
     *
     * @param  list<int>  $contactIds
     */
    public function enroll(Campaign $campaign, array $contactIds): void
    {
        $template = $campaign->template;
        $requiresConsent = $template->requiresConsent();

        Contact::query()
            ->where('tenant_id', $campaign->tenant_id)
            ->whereIn('id', $contactIds)
            ->chunkById(1000, function ($contacts) use ($campaign, $requiresConsent) {
                foreach ($contacts as $contact) {
                    $identifier = $campaign->channel === Channel::Email ? $contact->email : $contact->phone;

                    $skipReason = match (true) {
                        $identifier === null => $campaign->channel === Channel::Email
                            ? SkipReason::InvalidEmail
                            : SkipReason::InvalidNumber,
                        $requiresConsent && ! $contact->hasConsentFor($campaign->channel->value) => SkipReason::NoConsent,
                        default => null,
                    };

                    CampaignRecipient::query()->firstOrCreate(
                        ['campaign_id' => $campaign->id, 'contact_id' => $contact->id],
                        [
                            'status' => $skipReason !== null ? CampaignRecipientStatus::Skipped : CampaignRecipientStatus::Pending,
                            'skip_reason' => $skipReason,
                        ],
                    );
                }
            });
    }
}
