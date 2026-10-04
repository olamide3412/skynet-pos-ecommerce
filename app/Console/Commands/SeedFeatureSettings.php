<?php

namespace App\Console\Commands;

use App\Models\StoreSetting;
use Illuminate\Console\Command;

class SeedFeatureSettings extends Command
{
    protected $signature = 'settings:seed-features';
    protected $description = 'Seed default feature toggle settings';

    public function handle(): void
    {
        $defaults = [
            'order_tracking_enabled'        => '1',
            'customer_registration_enabled' => '1',
            'shop_enabled'                  => '1',
            'guest_checkout_enabled'        => '1',
            'reviews_enabled'               => '0',
            'wishlist_enabled'              => '0',
            'tracking_number_prefix'        => 'SKY-',
        ];

        foreach ($defaults as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->info('Feature toggle settings seeded successfully!');
    }
}
