<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_locale_settings', function (Blueprint $table): void {
            $table->string('locale', 12)->default('en-US')->change();
            $table->string('number_format', 16)->default('1,234.56')->change();
            $table->string('date_format', 16)->default('YYYY-MM-DD')->change();
        });

        $localeMap = [
            'en' => 'en-US',
            'de' => 'de-DE',
            'fr' => 'fr-FR',
            'si' => 'si-LK',
        ];

        $numberMap = [
            'system' => '1,234.56',
            'en-US' => '1,234.56',
            'de-DE' => '1.234,56',
            'fr-FR' => '1 234,56',
            'si-LK' => '1,234.56',
        ];

        $dateMap = [
            'system' => 'YYYY-MM-DD',
            'ISO' => 'YYYY-MM-DD',
            'DMY' => 'DD/MM/YYYY',
            'MDY' => 'MM/DD/YYYY',
            'YMD' => 'YYYY-MM-DD',
        ];

        DB::table('company_locale_settings')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($localeMap, $numberMap, $dateMap): void {
                foreach ($rows as $row) {
                    $locale = $localeMap[$row->locale] ?? ($row->locale ?: 'en-US');
                    $numberFormat = $numberMap[$row->number_format] ?? ($row->number_format ?: '1,234.56');
                    $dateFormat = $dateMap[$row->date_format] ?? ($row->date_format ?: 'YYYY-MM-DD');

                    DB::table('company_locale_settings')
                        ->where('id', $row->id)
                        ->update([
                            'locale' => $locale,
                            'number_format' => $numberFormat,
                            'date_format' => $dateFormat,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('company_locale_settings', function (Blueprint $table): void {
            $table->string('locale', 10)->default('en')->change();
            $table->enum('number_format', ['system', 'de-DE', 'en-US', 'fr-FR', 'si-LK'])->default('system')->change();
            $table->enum('date_format', ['system', 'ISO', 'DMY', 'MDY', 'YMD'])->default('system')->change();
        });

        $localeMap = [
            'en-US' => 'en',
            'de-DE' => 'de',
            'fr-FR' => 'fr',
            'si-LK' => 'si',
        ];

        $numberMap = [
            '1,234.56' => 'en-US',
            '1.234,56' => 'de-DE',
            '1 234,56' => 'fr-FR',
        ];

        $dateMap = [
            'YYYY-MM-DD' => 'YMD',
            'DD/MM/YYYY' => 'DMY',
            'MM/DD/YYYY' => 'MDY',
        ];

        DB::table('company_locale_settings')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($localeMap, $numberMap, $dateMap): void {
                foreach ($rows as $row) {
                    $locale = $localeMap[$row->locale] ?? ($row->locale ?: 'en');
                    $numberFormat = $numberMap[$row->number_format] ?? 'system';
                    $dateFormat = $dateMap[$row->date_format] ?? 'system';

                    DB::table('company_locale_settings')
                        ->where('id', $row->id)
                        ->update([
                            'locale' => $locale,
                            'number_format' => $numberFormat,
                            'date_format' => $dateFormat,
                        ]);
                }
            });
    }
};
