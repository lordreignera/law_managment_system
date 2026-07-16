<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use App\Models\Letterhead;
use Illuminate\Database\Seeder;

class LetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $letterheadId = Letterhead::where('is_default', true)->value('id') ?: Letterhead::query()->value('id');

        foreach ($this->templates() as $template) {
            LetterTemplate::updateOrCreate(
                ['code' => $template['code']],
                array_merge($template, [
                    'letterhead_id' => $letterheadId,
                    'is_active' => true,
                ])
            );
        }
    }

    private function templates(): array
    {
        return [
            [
                'name' => 'General Client Letter',
                'code' => 'GEN',
                'category' => 'general',
                'subject' => 'RE: {matter_title}',
                'body' => "Dear {recipient_name},\n\nWe refer to the above matter and write on behalf of {client_name}.\n\n{firm_name} will keep you updated on the next steps and any documents required.\n\nYours faithfully,",
                'description' => 'Reusable general correspondence for clients and third parties.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Legal Opinion',
                'code' => 'OPN',
                'category' => 'opinion',
                'subject' => 'RE: Legal Opinion - {matter_title}',
                'body' => "Dear {recipient_name},\n\nWe refer to your instructions in respect of {matter_title}.\n\nBased on the information and documents reviewed, our opinion is as follows:\n\n1. Background\n\n2. Issues for consideration\n\n3. Applicable law\n\n4. Analysis\n\n5. Conclusion and recommendation\n\nYours faithfully,",
                'description' => 'Structured opinion format after instructions or consultation.',
                'sort_order' => 2,
            ],
            [
                'name' => 'Demand Notice',
                'code' => 'DN',
                'category' => 'demand_notice',
                'subject' => 'RE: Demand Notice / Notice of Intention to Sue',
                'body' => "Dear {recipient_name},\n\nWe act for and on behalf of {client_name}, on whose instructions we address you as follows.\n\nDespite repeated demands and assurances on your part, the outstanding obligation remains unpaid.\n\nThis serves to demand that you settle the outstanding obligation within seven (7) days from receipt of this notice. Should you fail to comply within this notice period, we have been instructed to engage all legal mechanisms to recover the outstanding sums at your own cost and peril.\n\nYours faithfully,",
                'description' => 'Demand notice for recovery and pre-litigation follow-up.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Instruction Acceptance',
                'code' => 'INS',
                'category' => 'instruction',
                'subject' => 'RE: Acceptance of Instructions - {matter_title}',
                'body' => "Dear {recipient_name},\n\nWe acknowledge receipt of your instructions in respect of {matter_title} and confirm our acceptance to act.\n\nKindly provide any additional documents, contacts, or information necessary for us to proceed.\n\nYours faithfully,",
                'description' => 'Acceptance response after instructions are received by email or letter.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Engagement Letter',
                'code' => 'ENG',
                'category' => 'engagement',
                'subject' => 'RE: Engagement Letter - {matter_title}',
                'body' => "Dear {recipient_name},\n\nWe are pleased to confirm the terms under which {firm_name} will provide legal services in respect of {matter_title}.\n\nScope of work:\n\nFees and disbursements:\n\nClient responsibilities:\n\nYours faithfully,",
                'description' => 'Client engagement terms for newly opened work.',
                'sort_order' => 5,
            ],
        ];
    }
}
