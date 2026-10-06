<?php

declare(strict_types=1);

use App\Enums\Channel;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\Email\EmailSendGridDriver;
use App\Services\Channels\Sms\SmsTwilioDriver;
use App\Services\Channels\WhatsApp\WhatsAppCloudDriver;
use App\Services\Channels\WhatsApp\WhatsAppTwilioDriver;

it('resolves the configured driver for each channel', function () {
    $manager = app(ChannelManager::class);

    expect($manager->driver(Channel::WhatsApp))->toBeInstanceOf(WhatsAppTwilioDriver::class)
        ->and($manager->driver(Channel::Sms))->toBeInstanceOf(SmsTwilioDriver::class)
        ->and($manager->driver(Channel::Email))->toBeInstanceOf(EmailSendGridDriver::class);
});

it('resolves the whatsapp cloud driver when configured', function () {
    config(['channels.whatsapp.driver' => 'cloud']);

    expect(app(ChannelManager::class)->driver(Channel::WhatsApp))
        ->toBeInstanceOf(WhatsAppCloudDriver::class);
});

it('throws for an unsupported driver name', function () {
    config(['channels.sms.driver' => 'unknown']);

    app(ChannelManager::class)->driver(Channel::Sms);
})->throws(InvalidArgumentException::class);

it('memoizes the resolved driver per channel', function () {
    $manager = app(ChannelManager::class);

    expect($manager->driver(Channel::Sms))->toBe($manager->driver(Channel::Sms));
});
