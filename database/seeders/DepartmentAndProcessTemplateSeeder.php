<?php

namespace Database\Seeders;

use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Models\Department;
use App\Models\ProcessTemplate;
use App\Models\ProcessTemplateItem;
use Illuminate\Database\Seeder;

class DepartmentAndProcessTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            [
                'name' => 'Finance / Accounts',
                'code' => 'FINANCE',
                'description' => 'Finance, Accounts, Banking, Invoicing, Taxes and Audits',
                'template' => [
                    'name' => 'Finance — Daily Checklist',
                    'code' => 'FIN_DAILY',
                    'description' => 'Daily operational checklist for finance and accounts department',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Today bank transactions update செய்யப்பட்டதா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Receivables follow-up செய்யப்பட்டதா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Payables due list பார்த்தீர்களா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Pending invoices உள்ளதா?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Customer payments update செய்யப்பட்டதா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Supplier payments update செய்யப்பட்டதா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Cash position update செய்யப்பட்டதா?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Expense entries complete-ஆ?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'GST-related entries/reconciliation தயாரா?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'GST filing-க்கு தேவையான data/documents ready-ஆ?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'TDS-related work due-ஆ?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Salary/payment processing status என்ன?', 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Month-end closing completed-ஆ?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Auditor கேட்ட documents கொடுக்கப்பட்டதா?', 'type' => ProcessResponseType::YES_NO_NA],
                    ],
                ],
            ],
            [
                'name' => 'Sales',
                'code' => 'SALES',
                'description' => 'Lead management, Client calls, Quotations and Revenue targets',
                'template' => [
                    'name' => 'Sales — Daily Checklist',
                    'code' => 'SALES_DAILY',
                    'description' => 'Daily sales follow-up and pipeline checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => "Today's leads follow-up completed?", 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Pending enquiries reviewed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Quotations sent?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Old quotations followed up?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => "Today's customer calls completed?", 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Customer meetings completed?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Order confirmation pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Payment follow-up completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Lost leads updated?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Daily sales report submitted?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Monthly target vs activity updated?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'Marketing',
                'code' => 'MARKETING',
                'description' => 'Campaigns, Social Media, Branding and Lead Generation',
                'template' => [
                    'name' => 'Marketing — Daily Checklist',
                    'code' => 'MKT_DAILY',
                    'description' => 'Daily marketing campaigns, content and lead generation checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => "Today's campaign activity completed?", 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Social media post/content published?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Leads generated?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Leads assigned to sales team?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Campaign follow-up completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Website / Google Business updates completed?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Enquiry responses completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Marketing report submitted?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Next campaign activity planned?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'Admin',
                'code' => 'ADMIN',
                'description' => 'Office management, Facilities, Licences, Renewals and Vendors',
                'template' => [
                    'name' => 'Admin — Daily Checklist',
                    'code' => 'ADMIN_DAILY',
                    'description' => 'Daily administrative and facility operations checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Attendance checked?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Leave requests processed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Office opening checklist completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Office facilities checked?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Important documents updated?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Licence / renewal dates checked?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Vendor follow-up completed?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Staff requirements reviewed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Meeting arrangements completed?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Office closing checklist completed?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'HR',
                'code' => 'HR',
                'description' => 'Recruitment, Onboarding, Training, Performance and Employee Relations',
                'template' => [
                    'name' => 'HR — Daily Checklist',
                    'code' => 'HR_DAILY',
                    'description' => 'Daily HR and operational workforce checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Attendance / late-coming reviewed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Leave approval pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'New employee onboarding status?', 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Employee documents complete?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Training scheduled?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Pending HR requests?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Performance review due?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Employee communication sent?', 'type' => ProcessResponseType::YES_NO_NA],
                    ],
                ],
            ],
            [
                'name' => 'Production',
                'code' => 'PRODUCTION',
                'description' => 'Manufacturing, Machine scheduling, Quality control and Output tracking',
                'template' => [
                    'name' => 'Production — Daily Checklist',
                    'code' => 'PROD_DAILY',
                    'description' => 'Daily shop floor and output checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => "Today's production target?", 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Actual output recorded?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Machine/operator allocation completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Production checklist completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Material available?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Quality check completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Rejection / wastage recorded?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Production pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Shift handover completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Daily production report submitted?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'Purchase / Procurement',
                'code' => 'PURCHASE',
                'description' => 'Supplier sourcing, Purchase orders, Quotations and Deliveries',
                'template' => [
                    'name' => 'Purchase — Daily Checklist',
                    'code' => 'PUR_DAILY',
                    'description' => 'Daily procurement and supplier tracking checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Pending purchase requests?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Quotations received?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Supplier follow-up?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Purchase orders pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Material delivery due today?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Delayed deliveries?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Purchase approval pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Supplier documents updated?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'Stores / Inventory',
                'code' => 'STORES',
                'description' => 'Material receipts, Issues, Shortages and Physical verifications',
                'template' => [
                    'name' => 'Stores — Process Checklist',
                    'code' => 'STORE_DAILY',
                    'description' => 'Daily stores inward/outward and critical stock checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Material received update?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Material issue records completed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Critical items checked?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Physical verification due?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Shortage reported?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Damaged material reported?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Stock-related pending requests?', 'type' => ProcessResponseType::YES_NO_NA],
                    ],
                ],
            ],
            [
                'name' => 'Service / Customer Support',
                'code' => 'SERVICE',
                'description' => 'Support tickets, Technician visits, Complaints and SLA tracking',
                'template' => [
                    'name' => 'Service — Daily Checklist',
                    'code' => 'SRV_DAILY',
                    'description' => 'Daily service requests, technician visits and SLA checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => 'Open service requests?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => "Today's scheduled visits?", 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Technician assigned?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Pending customer calls?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Service completed?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Customer confirmation received?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Open complaints?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'SLA/deadline approaching?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Service report submitted?', 'type' => ProcessResponseType::YES_NO],
                    ],
                ],
            ],
            [
                'name' => 'Transport / Logistics',
                'code' => 'TRANSPORT',
                'description' => 'Dispatch, Vehicle assignment, Driver routes and Delivery tracking',
                'template' => [
                    'name' => 'Transport — Daily Checklist',
                    'code' => 'TRANS_DAILY',
                    'description' => 'Daily dispatch, logistics and vehicle operations checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => "Today's dispatches?", 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Vehicles assigned?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Driver assigned?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Delivery schedule confirmed?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Pending deliveries?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Vehicle documents/renewals due?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Delivery confirmation received?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Transport expenses/documents submitted?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Delayed delivery follow-up?', 'type' => ProcessResponseType::YES_NO_NA],
                    ],
                ],
            ],
            [
                'name' => 'Management / Business Owner',
                'code' => 'MANAGEMENT',
                'description' => 'Critical approvals, Strategic decisions, Overdue items and Key priorities',
                'template' => [
                    'name' => 'Management — Daily Checklist',
                    'code' => 'MGMT_DAILY',
                    'description' => 'Daily executive summary, approvals and priority checklist',
                    'frequency' => ProcessFrequency::DAILY,
                    'questions' => [
                        ['question' => "Today's critical pending items?", 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Approvals pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Important customer follow-ups?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Important payments/collections?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => "Today's meetings?", 'type' => ProcessResponseType::TEXT],
                        ['question' => 'Department reports received?', 'type' => ProcessResponseType::YES_NO],
                        ['question' => 'Overdue tasks?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => 'Decisions pending?', 'type' => ProcessResponseType::YES_NO_NA],
                        ['question' => "Tomorrow's priorities?", 'type' => ProcessResponseType::TEXT],
                    ],
                ],
            ],
        ];

        foreach ($definitions as $def) {
            $department = Department::updateOrCreate(
                ['code' => $def['code']],
                [
                    'name' => $def['name'],
                    'description' => $def['description'],
                    'is_active' => true,
                ]
            );

            if (isset($def['template'])) {
                $templateDef = $def['template'];
                $template = ProcessTemplate::updateOrCreate(
                    ['code' => $templateDef['code']],
                    [
                        'department_id' => $department->id,
                        'name' => $templateDef['name'],
                        'description' => $templateDef['description'],
                        'frequency_default' => $templateDef['frequency'],
                        'is_active' => true,
                        'is_system_template' => true,
                    ]
                );

                foreach ($templateDef['questions'] as $index => $q) {
                    ProcessTemplateItem::updateOrCreate(
                        [
                            'process_template_id' => $template->id,
                            'sort_order' => $index + 1,
                        ],
                        [
                            'question' => $q['question'],
                            'response_type' => $q['type'],
                            'is_required' => true,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
