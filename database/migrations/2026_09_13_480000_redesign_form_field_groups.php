<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->string('field_group', 30)->default('custom')->after('type');
            $table->text('description')->nullable()->after('label');
            $table->index(['template_id', 'field_group']);
        });

        $required = ['product', 'sender_name', 'sender_phone', 'recipient_name', 'recipient_phone', 'district', 'location'];
        $optional = ['address', 'delivery_date', 'delivery_time', 'dedication', 'photo'];

        DB::table('form_fields')->whereIn('key', $required)->update(['field_group' => 'required_base', 'is_system' => true]);
        DB::table('form_fields')->whereIn('key', $optional)->update(['field_group' => 'optional_base', 'is_system' => true]);
        DB::table('form_fields')->whereNotIn('key', array_merge($required, $optional))->update(['field_group' => 'custom', 'is_system' => false]);

        $template = DB::table('form_templates')->where('code', 'campaign-order-v1')->first();
        if ($template && ! DB::table('form_fields')->where('template_id', $template->id)->where('key', 'photo')->exists()) {
            DB::table('form_fields')->insert([
                'template_id' => $template->id,
                'key' => 'photo',
                'label' => 'Foto de referencia',
                'description' => 'Imagen opcional relacionada con la entrega.',
                'type' => 'file',
                'field_group' => 'optional_base',
                'is_system' => true,
                'is_active' => true,
                'validation_rules' => json_encode(['accept' => ['image/jpeg', 'image/png', 'image/webp'], 'max_size_kb' => 5120]),
                'sort_order' => 105,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $deliveryTimeId = DB::table('form_fields')->where('key', 'delivery_time')->value('id');
        if ($deliveryTimeId) {
            DB::table('campaign_form_fields')->where('form_field_id', $deliveryTimeId)->update(['is_enabled' => true, 'is_required' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->dropIndex(['template_id', 'field_group']);
            $table->dropColumn(['field_group', 'description']);
        });
    }
};
