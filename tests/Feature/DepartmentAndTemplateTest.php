<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\ProcessTemplate;
use Database\Seeders\DepartmentAndProcessTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAndTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DepartmentAndProcessTemplateSeeder::class);
    }

    public function test_all_eleven_departments_exist(): void
    {
        $expectedCodes = [
            'FINANCE', 'SALES', 'MARKETING', 'ADMIN', 'HR',
            'PRODUCTION', 'PURCHASE', 'STORES', 'SERVICE', 'TRANSPORT', 'MANAGEMENT',
        ];

        $this->assertEquals(11, Department::count());

        foreach ($expectedCodes as $code) {
            $this->assertDatabaseHas('departments', ['code' => $code]);
        }
    }

    public function test_all_eleven_departments_have_prebuilt_templates_and_questions(): void
    {
        $expectedTemplates = [
            'FIN_DAILY' => 'Finance — Daily Checklist',
            'SALES_DAILY' => 'Sales — Daily Checklist',
            'MKT_DAILY' => 'Marketing — Daily Checklist',
            'ADMIN_DAILY' => 'Admin — Daily Checklist',
            'HR_DAILY' => 'HR — Daily Checklist',
            'PROD_DAILY' => 'Production — Daily Checklist',
            'PUR_DAILY' => 'Purchase — Daily Checklist',
            'STORE_DAILY' => 'Stores — Process Checklist',
            'SRV_DAILY' => 'Service — Daily Checklist',
            'TRANS_DAILY' => 'Transport — Daily Checklist',
            'MGMT_DAILY' => 'Management — Daily Checklist',
        ];

        foreach ($expectedTemplates as $code => $name) {
            $template = ProcessTemplate::where('code', $code)->first();
            $this->assertNotNull($template, "Template {$code} should exist.");
            $this->assertEquals($name, $template->name);
            $this->assertTrue($template->items()->count() > 0, "Template {$code} should have items.");
            $this->assertTrue($template->is_system_template);
        }
    }

    public function test_finance_template_contains_all_reference_questions(): void
    {
        $template = ProcessTemplate::where('code', 'FIN_DAILY')->first();
        $this->assertNotNull($template);

        $questions = $template->items->pluck('question')->toArray();

        $this->assertContains('Today bank transactions update செய்யப்பட்டதா?', $questions);
        $this->assertContains('Receivables follow-up செய்யப்பட்டதா?', $questions);
        $this->assertContains('Payables due list பார்த்தீர்களா?', $questions);
        $this->assertContains('GST-related entries/reconciliation தயாரா?', $questions);
        $this->assertContains('GST filing-க்கு தேவையான data/documents ready-ஆ?', $questions);
        $this->assertContains('Auditor கேட்ட documents கொடுக்கப்பட்டதா?', $questions);
    }

    public function test_seeding_is_idempotent(): void
    {
        // Run the seeder a second time
        $this->seed(DepartmentAndProcessTemplateSeeder::class);

        $this->assertEquals(11, Department::count());
        $this->assertEquals(11, ProcessTemplate::count());
    }
}
