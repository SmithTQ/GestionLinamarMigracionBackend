<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'delivery_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->text('delivery_reference')->nullable()->after('address');
            });
        }

        $template = DB::table('form_templates')->where('code', 'campaign-order-v1')->first();
        if ($template === null) {
            return;
        }

        $field = DB::table('form_fields')->where('template_id', $template->id)->where('key', 'delivery_reference')->first();
        if ($field === null) {
            $fieldId = DB::table('form_fields')->insertGetId([
                'template_id' => $template->id,
                'key' => 'delivery_reference',
                'label' => 'Referencia de entrega',
                'description' => 'Referencia visual para ubicar el punto de entrega. Ejemplo: Casa azul de tres pisos, frente al parque.',
                'type' => 'textarea',
                'field_group' => 'required_base',
                'is_system' => true,
                'is_active' => true,
                'validation_rules' => json_encode(['max_length' => 1000]),
                'sort_order' => 75,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $field = (object) ['id' => $fieldId];
        } else {
            DB::table('form_fields')->where('id', $field->id)->update([
                'label' => 'Referencia de entrega',
                'description' => 'Referencia visual para ubicar el punto de entrega. Ejemplo: Casa azul de tres pisos, frente al parque.',
                'type' => 'textarea',
                'field_group' => 'required_base',
                'is_system' => true,
                'is_active' => true,
                'validation_rules' => json_encode(['max_length' => 1000]),
                'sort_order' => 75,
                'updated_at' => now(),
            ]);
        }

        DB::table('campaign_forms')
            ->where('template_id', $template->id)
            ->where('status', 'draft')
            ->pluck('id')
            ->each(function (int $formId) use ($field): void {
                DB::table('campaign_form_fields')->updateOrInsert(
                    ['campaign_form_id' => $formId, 'form_field_id' => $field->id],
                    ['is_enabled' => true, 'is_required' => true, 'label' => null, 'config' => null, 'sort_order' => 75]
                );
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'delivery_reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropColumn('delivery_reference');
            });
        }

        $template = DB::table('form_templates')->where('code', 'campaign-order-v1')->first();
        if ($template === null) {
            return;
        }

        $field = DB::table('form_fields')->where('template_id', $template->id)->where('key', 'delivery_reference')->first();
        if ($field !== null) {
            DB::table('campaign_form_fields')->where('form_field_id', $field->id)->delete();
            DB::table('form_fields')->where('id', $field->id)->delete();
        }
    }
};
