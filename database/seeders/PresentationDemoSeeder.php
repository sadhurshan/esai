<?php

namespace Database\Seeders;

use App\Enums\CompanySupplierStatus;
use App\Enums\InvoiceStatus;
use App\Enums\RiskGrade;
use App\Models\Bin;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Part;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\RFQ;
use App\Models\RfqInvitation;
use App\Models\RfqItem;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\CompanyContext;
use App\Support\PurchaseOrders\PurchaseOrderSupplierResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PresentationDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'presentation.buyer@example.com';
    private const DEMO_SUPPLIER_CODE = 'aurora-fab';

    private const SUPPLIER_BLUEPRINTS = [
        [
            'code' => 'aurora-fab',
            'name' => 'Aurora Fabrication Labs',
            'city' => 'Seattle',
            'state' => 'WA',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 12,
            'moq' => 8,
            'risk_grade' => 'low',
            'rating_avg' => 4.82,
            'price_band' => 'tier_1',
            'methods' => ['CNC Milling', 'CNC Turning', 'Sheet Metal Fabrication'],
            'materials' => ['Aluminum 6061', 'Aluminum 7075', 'Stainless Steel 304'],
            'finishes' => ['Anodizing', 'Bead Blast', 'Powder Coat'],
            'tolerances' => ['+/- 0.005"', '+/- 0.010"'],
            'industries' => ['Aerospace', 'Robotics', 'Commercial Drones'],
            'phone' => '+1-425-555-0114',
            'address' => '1180 Dexter Ave N, Seattle, WA 98109',
            'website' => 'https://aurorafab.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-AUR-001',
            'lat' => 47.6205,
            'lng' => -122.3493,
            'notes' => 'Holds AS9100 certification and in-house anodizing.',
        ],
        [
            'code' => 'summit-metals',
            'name' => 'Summit Metals & Machining',
            'city' => 'Denver',
            'state' => 'CO',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 16,
            'moq' => 15,
            'risk_grade' => 'medium',
            'rating_avg' => 4.54,
            'price_band' => 'tier_2',
            'methods' => ['CNC Turning', 'Swiss Turning', 'Sheet Metal Fabrication'],
            'materials' => ['Aluminum 6061', 'Brass', 'Titanium Grade 5'],
            'finishes' => ['Anodizing', 'Passivation'],
            'tolerances' => ['+/- 0.005"', '+/- 0.002"'],
            'industries' => ['Aerospace', 'Energy'],
            'phone' => '+1-303-555-0156',
            'address' => '9400 E Hampden Ave, Denver, CO 80231',
            'website' => 'https://summit-metals.example',
            'payment_terms' => 'Net 45',
            'tax_id' => 'SUP-SUM-014',
            'lat' => 39.6542,
            'lng' => -104.8798,
            'notes' => 'Known for quick-turn swiss turning.',
        ],
        [
            'code' => 'northstar-cnc',
            'name' => 'Northstar CNC Works',
            'city' => 'Minneapolis',
            'state' => 'MN',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 18,
            'moq' => 25,
            'risk_grade' => 'medium',
            'rating_avg' => 4.38,
            'price_band' => 'tier_2',
            'methods' => ['CNC Milling', 'CNC Turning', 'Waterjet Cutting'],
            'materials' => ['Steel 1018', 'Aluminum 7050', 'Plastics'],
            'finishes' => ['Powder Coat', 'Black Oxide'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Industrial Equipment', 'Robotics'],
            'phone' => '+1-612-555-0193',
            'address' => '1400 Industrial Blvd, Minneapolis, MN 55413',
            'website' => 'https://northstarcnc.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-NSC-022',
            'lat' => 45.0030,
            'lng' => -93.2290,
            'notes' => 'Carries large-format 5-axis capability.',
        ],
        [
            'code' => 'stratus-precision',
            'name' => 'Stratus Precision Group',
            'city' => 'Chicago',
            'state' => 'IL',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 20,
            'moq' => 50,
            'risk_grade' => 'low',
            'rating_avg' => 4.67,
            'price_band' => 'tier_1',
            'methods' => ['Sheet Metal Fabrication', 'Laser Cutting', 'CNC Forming'],
            'materials' => ['Stainless Steel 304', 'Galvanized Steel'],
            'finishes' => ['Powder Coat', 'Zinc Plating'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Energy Storage', 'Mobility'],
            'phone' => '+1-312-555-0177',
            'address' => '221 W Kinzie St, Chicago, IL 60654',
            'website' => 'https://stratusprecision.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-STR-031',
            'lat' => 41.8896,
            'lng' => -87.6356,
            'notes' => 'High mix sheet-metal supplier with robotic forming.',
        ],
        [
            'code' => 'cobalt-labs',
            'name' => 'Cobalt Labs Manufacturing',
            'city' => 'Detroit',
            'state' => 'MI',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 14,
            'moq' => 20,
            'risk_grade' => 'low',
            'rating_avg' => 4.73,
            'price_band' => 'tier_1',
            'methods' => ['CNC Turning', 'Grinding', 'Broaching'],
            'materials' => ['Alloy Steel', 'Tool Steel'],
            'finishes' => ['Black Oxide', 'Phosphate'],
            'tolerances' => ['+/- 0.002"'],
            'industries' => ['Automotive', 'Industrial'],
            'phone' => '+1-313-555-0124',
            'address' => '801 W Fort St, Detroit, MI 48226',
            'website' => 'https://cobaltlabs.example',
            'payment_terms' => 'Net 45',
            'tax_id' => 'SUP-COB-044',
            'lat' => 42.3295,
            'lng' => -83.0489,
            'notes' => 'Strong grinding bench for shaft work.',
        ],
        [
            'code' => 'orbit-works',
            'name' => 'Orbit Works Fabrication',
            'city' => 'Phoenix',
            'state' => 'AZ',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 10,
            'moq' => 12,
            'risk_grade' => 'medium',
            'rating_avg' => 4.41,
            'price_band' => 'tier_2',
            'methods' => ['3D Printing', 'CNC Milling'],
            'materials' => ['Nylon', 'PEEK', 'Aluminum 6061'],
            'finishes' => ['Bead Blast', 'Vapor Polish'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Aviation', 'Space'],
            'phone' => '+1-602-555-0145',
            'address' => '3200 E Broadway Rd, Phoenix, AZ 85040',
            'website' => 'https://orbitworks.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-ORB-053',
            'lat' => 33.4100,
            'lng' => -112.0100,
            'notes' => 'Hybrid additive plus CNC finishing.',
        ],
        [
            'code' => 'vector-dynamics',
            'name' => 'Vector Dynamics',
            'city' => 'Boston',
            'state' => 'MA',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 22,
            'moq' => 40,
            'risk_grade' => 'medium',
            'rating_avg' => 4.36,
            'price_band' => 'tier_3',
            'methods' => ['CNC Milling', 'Surface Grinding'],
            'materials' => ['Titanium', 'Inconel'],
            'finishes' => ['Passivation'],
            'tolerances' => ['+/- 0.002"'],
            'industries' => ['Defense', 'Aerospace'],
            'phone' => '+1-617-555-0199',
            'address' => '175 Federal St, Boston, MA 02110',
            'website' => 'https://vectordynamics.example',
            'payment_terms' => 'Net 45',
            'tax_id' => 'SUP-VEC-061',
            'lat' => 42.3525,
            'lng' => -71.0553,
            'notes' => 'Focus on titanium drivetrain components.',
        ],
        [
            'code' => 'forge-alliance',
            'name' => 'Forge Alliance Partners',
            'city' => 'Houston',
            'state' => 'TX',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 15,
            'moq' => 30,
            'risk_grade' => 'low',
            'rating_avg' => 4.58,
            'price_band' => 'tier_2',
            'methods' => ['Sheet Metal Fabrication', 'CNC Milling'],
            'materials' => ['Stainless Steel', 'Aluminum'],
            'finishes' => ['Powder Coat', 'Passivation'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Oil & Gas', 'Industrial'],
            'phone' => '+1-713-555-0182',
            'address' => '609 Main St, Houston, TX 77002',
            'website' => 'https://forgealliance.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-FOR-072',
            'lat' => 29.7604,
            'lng' => -95.3698,
            'notes' => 'Great for heavy-gauge enclosures.',
        ],
        [
            'code' => 'lumen-prototyping',
            'name' => 'Lumen Prototyping Studio',
            'city' => 'San Jose',
            'state' => 'CA',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 9,
            'moq' => 5,
            'risk_grade' => 'low',
            'rating_avg' => 4.91,
            'price_band' => 'tier_1',
            'methods' => ['3D Printing', 'CNC Milling', 'Urethane Casting'],
            'materials' => ['Carbon Fiber Nylon', 'ABS', 'Aluminum'],
            'finishes' => ['Vapor Polish', 'Paint'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Consumer Electronics', 'Robotics'],
            'phone' => '+1-408-555-0133',
            'address' => '30 W San Fernando St, San Jose, CA 95113',
            'website' => 'https://lumenproto.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-LUM-081',
            'lat' => 37.3355,
            'lng' => -121.8935,
            'notes' => 'Best fit for fast-turn prototypes.',
        ],
        [
            'code' => 'cascade-industries',
            'name' => 'Cascade Industries',
            'city' => 'Portland',
            'state' => 'OR',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 11,
            'moq' => 10,
            'risk_grade' => 'medium',
            'rating_avg' => 4.44,
            'price_band' => 'tier_2',
            'methods' => ['Sheet Metal Fabrication', 'CNC Forming'],
            'materials' => ['Aluminum', 'Mild Steel'],
            'finishes' => ['Powder Coat', 'Anodize'],
            'tolerances' => ['+/- 0.015"'],
            'industries' => ['Energy Storage', 'Industrial'],
            'phone' => '+1-503-555-0174',
            'address' => '111 SW 5th Ave, Portland, OR 97204',
            'website' => 'https://cascade-industries.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-CAS-095',
            'lat' => 45.5202,
            'lng' => -122.6732,
            'notes' => 'Excels at enclosures with Class A cosmetics.',
        ],
        [
            'code' => 'apex-castings',
            'name' => 'Apex Castings & Machining',
            'city' => 'Pittsburgh',
            'state' => 'PA',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 24,
            'moq' => 60,
            'risk_grade' => 'medium',
            'rating_avg' => 4.32,
            'price_band' => 'tier_3',
            'methods' => ['Investment Casting', 'Machining'],
            'materials' => ['Stainless Steel', 'Tool Steel'],
            'finishes' => ['Passivation', 'Polish'],
            'tolerances' => ['+/- 0.010"'],
            'industries' => ['Industrial Equipment'],
            'phone' => '+1-412-555-0150',
            'address' => '525 William Penn Pl, Pittsburgh, PA 15219',
            'website' => 'https://apexcastings.example',
            'payment_terms' => 'Net 45',
            'tax_id' => 'SUP-APX-101',
            'lat' => 40.4411,
            'lng' => -79.9959,
            'notes' => 'Great for cast plus machine workflows.',
        ],
        [
            'code' => 'nova-composites',
            'name' => 'Nova Composites',
            'city' => 'Raleigh',
            'state' => 'NC',
            'country' => 'US',
            'status' => 'approved',
            'lead_time_days' => 13,
            'moq' => 12,
            'risk_grade' => 'low',
            'rating_avg' => 4.65,
            'price_band' => 'tier_1',
            'methods' => ['Composite Layup', 'Waterjet Cutting'],
            'materials' => ['Carbon Fiber', 'Fiberglass'],
            'finishes' => ['Clear Coat'],
            'tolerances' => ['+/- 0.020"'],
            'industries' => ['Aerospace', 'Mobility'],
            'phone' => '+1-919-555-0188',
            'address' => '421 Fayetteville St, Raleigh, NC 27601',
            'website' => 'https://novacomposites.example',
            'payment_terms' => 'Net 30',
            'tax_id' => 'SUP-NOV-110',
            'lat' => 35.7780,
            'lng' => -78.6382,
            'notes' => 'Lightweight fairings and housings specialist.',
        ],
    ];

    private const RFQ_BLUEPRINTS = [
        [
            'number' => 'RFQ-2025-001',
            'title' => 'Machined Drone Motor Mounts',
            'status' => RFQ::STATUS_OPEN,
            'method' => 'cnc',
            'material' => 'Aluminum 7075',
            'finish' => 'Anodized',
            'tolerance' => '+/- 0.005"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 6,
            'due_in_days' => 9,
            'notes' => 'Priority build for Q1 drone pilot lots.',
            'open_bidding' => false,
            'attachments' => 3,
            'is_partially_awarded' => false,
            'priority' => 'rush',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'DRV-2410',
                    'description' => 'Upper motor mount plate',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 7075',
                    'finish' => 'Clear anodize',
                    'tolerance' => '+/- 0.005"',
                    'qty' => 120,
                    'uom' => 'EA',
                    'target_price_minor' => 18500,
                    'notes' => 'Include inspection report.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'DRV-2411',
                    'description' => 'Motor mount spacer block',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Bead blast',
                    'tolerance' => '+/- 0.010"',
                    'qty' => 120,
                    'uom' => 'EA',
                    'target_price_minor' => 9500,
                    'notes' => 'Deburr all edges.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'summit-metals', 'northstar-cnc'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'submitted',
                    'price_factor' => 1.02,
                    'lead_time_days' => 14,
                    'submitted_days_ago' => 4,
                    'note' => 'Can bundle anodizing if PO released this week.',
                    'shortlisted' => true,
                ],
                [
                    'supplier' => 'summit-metals',
                    'status' => 'submitted',
                    'price_factor' => 0.98,
                    'lead_time_days' => 16,
                    'submitted_days_ago' => 5,
                    'note' => 'Includes fixture refresh cost.',
                ],
                [
                    'supplier' => 'northstar-cnc',
                    'status' => 'submitted',
                    'price_factor' => 1.04,
                    'lead_time_days' => 18,
                    'submitted_days_ago' => 5,
                    'note' => 'Requires partial release for tooling.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-002',
            'title' => 'Battery Door Assemblies',
            'status' => RFQ::STATUS_OPEN,
            'method' => 'sheet_metal',
            'material' => 'Stainless Steel 304',
            'finish' => 'Powder Coat',
            'tolerance' => '+/- 0.015"',
            'delivery_location' => 'San Diego, CA',
            'incoterm' => 'DAP',
            'currency' => 'USD',
            'publish_days_ago' => 10,
            'due_in_days' => 6,
            'notes' => 'Needs texture matched to legacy fleet.',
            'open_bidding' => true,
            'attachments' => 2,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'ENL-1180',
                    'description' => 'Battery door outer shell',
                    'method' => 'Sheet Metal',
                    'material' => 'Stainless Steel',
                    'finish' => 'Powder coat texture A',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 80,
                    'uom' => 'EA',
                    'target_price_minor' => 21500,
                    'notes' => 'Form radius per print.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'ENL-1181',
                    'description' => 'Latch bracket',
                    'method' => 'Laser Cut',
                    'material' => 'Stainless Steel',
                    'finish' => 'Passivation',
                    'tolerance' => '+/- 0.010"',
                    'qty' => 80,
                    'uom' => 'EA',
                    'target_price_minor' => 7200,
                    'notes' => 'Keep burrs under 0.003 in.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'stratus-precision', 'cascade-industries', 'orbit-works'],
            'quotes' => [
                [
                    'supplier' => 'stratus-precision',
                    'status' => 'submitted',
                    'price_factor' => 1.01,
                    'lead_time_days' => 20,
                    'submitted_days_ago' => 3,
                    'note' => 'Powder coat batch weekly.',
                    'shortlisted' => true,
                ],
                [
                    'supplier' => 'cascade-industries',
                    'status' => 'submitted',
                    'price_factor' => 0.97,
                    'lead_time_days' => 18,
                    'submitted_days_ago' => 4,
                    'note' => 'Paint line upgrade adds gloss option.',
                ],
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'submitted',
                    'price_factor' => 1.03,
                    'lead_time_days' => 19,
                    'submitted_days_ago' => 2,
                    'note' => 'Can mirror grain direction to match legacy skins.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-003',
            'title' => 'Composite Fairing Sets',
            'status' => RFQ::STATUS_AWARDED,
            'method' => 'other',
            'material' => 'Carbon Fiber',
            'finish' => 'Clear Coat',
            'tolerance' => '+/- 0.020"',
            'delivery_location' => 'Ventura, CA',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 22,
            'due_in_days' => -4,
            'closed_days_ago' => 3,
            'notes' => 'Targeting weight reduction vs last revision.',
            'open_bidding' => false,
            'attachments' => 4,
            'is_partially_awarded' => true,
            'priority' => 'strategic',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'CMP-3300',
                    'description' => 'Forward fairing shell',
                    'method' => 'Composite Layup',
                    'material' => 'Carbon Fiber',
                    'finish' => 'Clear coat gloss',
                    'tolerance' => '+/- 0.020"',
                    'qty' => 40,
                    'uom' => 'EA',
                    'target_price_minor' => 88000,
                    'notes' => 'Need autoclave cycle data.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'CMP-3302',
                    'description' => 'Rear fairing shell',
                    'method' => 'Composite Layup',
                    'material' => 'Carbon Fiber',
                    'finish' => 'Clear coat gloss',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 40,
                    'uom' => 'EA',
                    'target_price_minor' => 83500,
                    'notes' => 'Critical interface holes final drill.',
                ],
            ],
            'invite_suppliers' => ['nova-composites', 'lumen-prototyping', 'vector-dynamics'],
            'quotes' => [
                [
                    'supplier' => 'lumen-prototyping',
                    'status' => 'awarded',
                    'price_factor' => 1.05,
                    'lead_time_days' => 21,
                    'submitted_days_ago' => 9,
                    'note' => 'Split shipment possible.',
                    'shortlisted' => true,
                ],
                [
                    'supplier' => 'vector-dynamics',
                    'status' => 'submitted',
                    'price_factor' => 0.98,
                    'lead_time_days' => 28,
                    'submitted_days_ago' => 11,
                    'note' => 'Requires tooling retention.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-004',
            'title' => 'Precision Drive Shafts',
            'status' => RFQ::STATUS_CLOSED,
            'method' => 'cnc',
            'material' => 'Alloy Steel',
            'finish' => 'Black Oxide',
            'tolerance' => '+/- 0.002"',
            'delivery_location' => 'Cincinnati, OH',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 28,
            'due_in_days' => -2,
            'closed_days_ago' => 5,
            'notes' => 'Need PPAP level 3 documentation.',
            'open_bidding' => false,
            'attachments' => 5,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'SHA-5520',
                    'description' => 'Drive shaft - short',
                    'method' => 'CNC Turning',
                    'material' => 'Alloy Steel',
                    'finish' => 'Black oxide',
                    'tolerance' => '+/- 0.002"',
                    'qty' => 60,
                    'uom' => 'EA',
                    'target_price_minor' => 45500,
                    'notes' => 'Straightness under 0.001 in.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'SHA-5525',
                    'description' => 'Drive shaft - long',
                    'method' => 'CNC Turning',
                    'material' => 'Alloy Steel',
                    'finish' => 'Black oxide',
                    'tolerance' => '+/- 0.002"',
                    'qty' => 60,
                    'uom' => 'EA',
                    'target_price_minor' => 61200,
                    'notes' => 'Deep hole drilling per print.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'cobalt-labs', 'forge-alliance', 'apex-castings'],
            'quotes' => [
                [
                    'supplier' => 'cobalt-labs',
                    'status' => 'submitted',
                    'price_factor' => 1.00,
                    'lead_time_days' => 19,
                    'submitted_days_ago' => 8,
                    'note' => 'Includes PPAP package.',
                    'shortlisted' => true,
                ],
                [
                    'supplier' => 'forge-alliance',
                    'status' => 'submitted',
                    'price_factor' => 1.07,
                    'lead_time_days' => 17,
                    'submitted_days_ago' => 9,
                    'note' => 'Capacity limit 80 shafts per lot.',
                ],
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'rejected',
                    'price_factor' => 1.06,
                    'lead_time_days' => 20,
                    'submitted_days_ago' => 7,
                    'note' => 'Unable to grind diameter tolerance in-house.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-005',
            'title' => 'Sensor Bracket Refresh',
            'status' => RFQ::STATUS_DRAFT,
            'method' => 'cnc',
            'material' => 'Aluminum 6061',
            'finish' => 'Bead Blast',
            'tolerance' => '+/- 0.010"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => null,
            'due_in_days' => 18,
            'notes' => 'Pending antenna cutout confirmation.',
            'open_bidding' => false,
            'attachments' => 1,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'BRK-7830',
                    'description' => 'Sensor bracket main body',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Bead blast',
                    'tolerance' => '+/- 0.010"',
                    'qty' => 90,
                    'uom' => 'EA',
                    'target_price_minor' => 12800,
                    'notes' => 'Add threaded inserts per rev C.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'BRK-7835',
                    'description' => 'Sensor shield plate',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 5052',
                    'finish' => 'Powder coat',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 90,
                    'uom' => 'EA',
                    'target_price_minor' => 8400,
                    'notes' => 'Needs EMI gasket groove.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'vector-dynamics'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'draft',
                    'price_factor' => 1.01,
                    'lead_time_days' => 17,
                    'submitted_days_ago' => 1,
                    'note' => 'Waiting on antenna cutout confirmation before final submission.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-006',
            'title' => 'Carbon Fiber Landing Gear',
            'status' => RFQ::STATUS_OPEN,
            'method' => 'other',
            'material' => 'Carbon Fiber',
            'finish' => 'Clear Coat',
            'tolerance' => '+/- 0.015"',
            'delivery_location' => 'Reno, NV',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 3,
            'due_in_days' => 14,
            'notes' => 'Needs resin toughening data.',
            'open_bidding' => true,
            'attachments' => 2,
            'is_partially_awarded' => false,
            'priority' => 'rush',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'LG-4510',
                    'description' => 'Landing strut - left',
                    'method' => 'Composite Layup',
                    'material' => 'Carbon Fiber',
                    'finish' => 'Clear coat satin',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 30,
                    'uom' => 'EA',
                    'target_price_minor' => 67500,
                    'notes' => 'Autoclave cycle per print.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'LG-4511',
                    'description' => 'Landing strut - right',
                    'method' => 'Composite Layup',
                    'material' => 'Carbon Fiber',
                    'finish' => 'Clear coat satin',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 30,
                    'uom' => 'EA',
                    'target_price_minor' => 67500,
                    'notes' => 'Match weight pairings.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'nova-composites', 'lumen-prototyping', 'vector-dynamics'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'withdrawn',
                    'price_factor' => 1.12,
                    'lead_time_days' => 30,
                    'submitted_days_ago' => 2,
                    'note' => 'Withdrew after realizing composite layup falls outside capability.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-007',
            'title' => 'Control Housing Updates',
            'status' => RFQ::STATUS_DRAFT,
            'method' => 'sheet_metal',
            'material' => 'Aluminum 5052',
            'finish' => 'Powder Coat',
            'tolerance' => '+/- 0.020"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => null,
            'due_in_days' => 30,
            'notes' => 'Waiting on vent spec validation.',
            'open_bidding' => false,
            'attachments' => 1,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'HSG-9100',
                    'description' => 'Control housing shell',
                    'method' => 'Sheet Metal',
                    'material' => 'Aluminum 5052',
                    'finish' => 'Powder coat matte',
                    'tolerance' => '+/- 0.020"',
                    'qty' => 70,
                    'uom' => 'EA',
                    'target_price_minor' => 24800,
                    'notes' => 'Add louver detail rev B.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'HSG-9105',
                    'description' => 'Housing backplate',
                    'method' => 'Sheet Metal',
                    'material' => 'Aluminum 5052',
                    'finish' => 'Powder coat matte',
                    'tolerance' => '+/- 0.020"',
                    'qty' => 70,
                    'uom' => 'EA',
                    'target_price_minor' => 14200,
                    'notes' => 'Need PEM inserts pre-installed.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'forge-alliance', 'cascade-industries'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'draft',
                    'price_factor' => 1.04,
                    'lead_time_days' => 22,
                    'submitted_days_ago' => 0,
                    'note' => 'Modeling alternate powder colors before submitting final numbers.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-008',
            'title' => 'Legacy Wiring Brackets',
            'status' => RFQ::STATUS_CANCELLED,
            'method' => 'cnc',
            'material' => 'Aluminum 6061',
            'finish' => 'Clear Anodize',
            'tolerance' => '+/- 0.015"',
            'delivery_location' => 'Dallas, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 15,
            'due_in_days' => 5,
            'closed_days_ago' => 2,
            'notes' => 'Canceled after design pivot.',
            'open_bidding' => false,
            'attachments' => 1,
            'is_partially_awarded' => false,
            'priority' => 'low',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'BRK-1201',
                    'description' => 'Legacy wiring bracket',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Clear anodize',
                    'tolerance' => '+/- 0.015"',
                    'qty' => 50,
                    'uom' => 'EA',
                    'target_price_minor' => 9800,
                    'notes' => 'No deburr step needed.',
                ],
            ],
            'invite_suppliers' => ['northstar-cnc', 'aurora-fab'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'withdrawn',
                    'price_factor' => 1.00,
                    'lead_time_days' => 15,
                    'submitted_days_ago' => 6,
                    'note' => 'Quote withdrawn after program cancellation notice.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-009',
            'title' => 'Quick Disconnect Manifolds',
            'status' => RFQ::STATUS_AWARDED,
            'method' => 'cnc',
            'material' => 'Aluminum 7075',
            'finish' => 'Passivation',
            'tolerance' => '+/- 0.003"',
            'delivery_location' => 'San Jose, CA',
            'incoterm' => 'CIF',
            'currency' => 'USD',
            'publish_days_ago' => 18,
            'due_in_days' => -1,
            'closed_days_ago' => 1,
            'notes' => 'Awarded to cover pilot build.',
            'open_bidding' => false,
            'attachments' => 3,
            'is_partially_awarded' => false,
            'priority' => 'rush',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'MAN-8801',
                    'description' => 'QD manifold block',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 7075',
                    'finish' => 'Passivation',
                    'tolerance' => '+/- 0.003"',
                    'qty' => 55,
                    'uom' => 'EA',
                    'target_price_minor' => 48200,
                    'notes' => 'Heli-coils installed.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'orbit-works', 'summit-metals'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'rejected',
                    'price_factor' => 1.09,
                    'lead_time_days' => 19,
                    'submitted_days_ago' => 5,
                    'note' => 'Lost to lower-cost additive option from Orbit Works.',
                    'shortlisted' => true,
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-010',
            'title' => 'Ground Station Frames',
            'status' => RFQ::STATUS_OPEN,
            'method' => 'sheet_metal',
            'material' => 'Aluminum 6061',
            'finish' => 'Powder Coat',
            'tolerance' => '+/- 0.020"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 8,
            'due_in_days' => 11,
            'notes' => 'Need collapsible hardware per spec.',
            'open_bidding' => true,
            'attachments' => 2,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'FRM-6021',
                    'description' => 'Frame rail set',
                    'method' => 'Sheet Metal',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Powder coat matte',
                    'tolerance' => '+/- 0.020"',
                    'qty' => 45,
                    'uom' => 'EA',
                    'target_price_minor' => 39800,
                    'notes' => 'Panel rivet nuts pre-installed.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'FRM-6025',
                    'description' => 'Caster bracket kit',
                    'method' => 'Sheet Metal',
                    'material' => 'Aluminum 5052',
                    'finish' => 'Powder coat matte',
                    'tolerance' => '+/- 0.020"',
                    'qty' => 45,
                    'uom' => 'EA',
                    'target_price_minor' => 15600,
                    'notes' => 'Casters supplied by buyer.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'forge-alliance', 'cascade-industries', 'stratus-precision'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'submitted',
                    'price_factor' => 1.00,
                    'lead_time_days' => 21,
                    'submitted_days_ago' => 3,
                    'note' => 'Can stage powder coat batches to hold matte spec.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-011',
            'title' => 'Avionics Mounting Kit',
            'status' => RFQ::STATUS_CLOSED,
            'method' => 'cnc',
            'material' => 'Aluminum 7075',
            'finish' => 'Bead Blast',
            'tolerance' => '+/- 0.005"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => 20,
            'due_in_days' => -3,
            'closed_days_ago' => 4,
            'notes' => 'Lot closed, prepping PO release.',
            'open_bidding' => false,
            'attachments' => 2,
            'is_partially_awarded' => false,
            'priority' => 'standard',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'AVN-7200',
                    'description' => 'Avionics mount plate',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 7075',
                    'finish' => 'Bead blast',
                    'tolerance' => '+/- 0.005"',
                    'qty' => 65,
                    'uom' => 'EA',
                    'target_price_minor' => 31200,
                    'notes' => 'Keyed slots per rev D.',
                ],
                [
                    'line_no' => 20,
                    'part_number' => 'AVN-7205',
                    'description' => 'Gimbal bracket',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Anodize',
                    'tolerance' => '+/- 0.010"',
                    'qty' => 65,
                    'uom' => 'EA',
                    'target_price_minor' => 20100,
                    'notes' => 'Critical flatness 0.003 in.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'northstar-cnc'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'rejected',
                    'price_factor' => 1.02,
                    'lead_time_days' => 16,
                    'submitted_days_ago' => 6,
                    'note' => 'Lost after Northstar committed to faster bead blast turnaround.',
                ],
            ],
        ],
        [
            'number' => 'RFQ-2025-012',
            'title' => 'Cooling Plate Pilot',
            'status' => RFQ::STATUS_DRAFT,
            'method' => 'cnc',
            'material' => 'Aluminum 6061',
            'finish' => 'Clear Anodize',
            'tolerance' => '+/- 0.005"',
            'delivery_location' => 'Austin, TX',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'publish_days_ago' => null,
            'due_in_days' => 35,
            'notes' => 'Need thermal simulation before release.',
            'open_bidding' => false,
            'attachments' => 1,
            'is_partially_awarded' => false,
            'priority' => 'strategic',
            'items' => [
                [
                    'line_no' => 10,
                    'part_number' => 'CLP-5500',
                    'description' => 'Cooling plate machined block',
                    'method' => 'CNC Milling',
                    'material' => 'Aluminum 6061',
                    'finish' => 'Clear anodize',
                    'tolerance' => '+/- 0.005"',
                    'qty' => 40,
                    'uom' => 'EA',
                    'target_price_minor' => 54200,
                    'notes' => 'Laser weld cap required.',
                ],
            ],
            'invite_suppliers' => ['aurora-fab', 'orbit-works'],
            'quotes' => [
                [
                    'supplier' => 'aurora-fab',
                    'status' => 'submitted',
                    'price_factor' => 1.07,
                    'lead_time_days' => 24,
                    'submitted_days_ago' => 1,
                    'note' => 'Can prototype cooling channels with quick-turn tooling.',
                ],
            ],
        ],
    ];

    private const PURCHASE_ORDER_BLUEPRINTS = [
        [
            'po_number' => 'PO-7701',
            'rfq_number' => 'RFQ-2025-001',
            'supplier' => 'aurora-fab',
            'status' => 'sent',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 5,
            'expected_in_days' => 18,
            'ack_status' => 'sent',
            'ack_days_ago' => null,
            'ordered_days_ago' => 6,
            'order_status' => 'in_production',
            'ship_mode' => 'Ground Freight',
            'lines' => [
                ['description' => 'Upper motor mount plate', 'quantity' => 120, 'unit_price_minor' => 18900, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Motor mount spacer block', 'quantity' => 120, 'unit_price_minor' => 9800, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7702',
            'rfq_number' => 'RFQ-2025-001',
            'supplier' => 'summit-metals',
            'status' => 'sent',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 7,
            'expected_in_days' => 16,
            'ack_status' => 'acknowledged',
            'ack_days_ago' => 6,
            'ordered_days_ago' => 8,
            'order_status' => 'in_transit',
            'ship_mode' => 'Air Freight',
            'lines' => [
                ['description' => 'Upper motor mount plate', 'quantity' => 60, 'unit_price_minor' => 18100, 'uom' => 'EA', 'receiving_status' => 'open', 'received_qty' => 20],
                ['description' => 'Motor mount spacer block', 'quantity' => 60, 'unit_price_minor' => 9100, 'uom' => 'EA', 'receiving_status' => 'open', 'received_qty' => 20],
            ],
        ],
        [
            'po_number' => 'PO-7703',
            'rfq_number' => 'RFQ-2025-002',
            'supplier' => 'stratus-precision',
            'status' => 'confirmed',
            'incoterm' => 'DAP',
            'currency' => 'USD',
            'tax_rate' => 0.0795,
            'sent_days_ago' => 9,
            'expected_in_days' => 14,
            'ack_status' => 'acknowledged',
            'ack_days_ago' => 8,
            'ordered_days_ago' => 10,
            'order_status' => 'in_production',
            'ship_mode' => 'LTL Freight',
            'lines' => [
                ['description' => 'Battery door outer shell', 'quantity' => 80, 'unit_price_minor' => 22100, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Latch bracket', 'quantity' => 80, 'unit_price_minor' => 7500, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7704',
            'rfq_number' => 'RFQ-2025-003',
            'supplier' => 'lumen-prototyping',
            'status' => 'sent',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 4,
            'expected_in_days' => 25,
            'ack_status' => 'sent',
            'ack_days_ago' => null,
            'ordered_days_ago' => 5,
            'order_status' => 'pending',
            'ship_mode' => 'Dedicated Truck',
            'lines' => [
                ['description' => 'Forward fairing shell', 'quantity' => 30, 'unit_price_minor' => 90500, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Rear fairing shell', 'quantity' => 30, 'unit_price_minor' => 84500, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7705',
            'rfq_number' => 'RFQ-2025-004',
            'supplier' => 'cobalt-labs',
            'status' => 'confirmed',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 11,
            'expected_in_days' => 12,
            'ack_status' => 'acknowledged',
            'ack_days_ago' => 10,
            'ordered_days_ago' => 12,
            'order_status' => 'in_production',
            'ship_mode' => 'Ground Freight',
            'lines' => [
                ['description' => 'Drive shaft - short', 'quantity' => 60, 'unit_price_minor' => 46200, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Drive shaft - long', 'quantity' => 60, 'unit_price_minor' => 62400, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7706',
            'rfq_number' => 'RFQ-2025-005',
            'supplier' => 'vector-dynamics',
            'status' => 'draft',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => null,
            'expected_in_days' => 20,
            'ack_status' => 'draft',
            'ack_days_ago' => null,
            'ordered_days_ago' => null,
            'order_status' => 'pending',
            'ship_mode' => 'Ground Freight',
            'lines' => [
                ['description' => 'Sensor bracket main body', 'quantity' => 90, 'unit_price_minor' => 13100, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Sensor shield plate', 'quantity' => 90, 'unit_price_minor' => 8600, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7707',
            'rfq_number' => 'RFQ-2025-006',
            'supplier' => 'nova-composites',
            'status' => 'sent',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 2,
            'expected_in_days' => 26,
            'ack_status' => 'sent',
            'ack_days_ago' => null,
            'ordered_days_ago' => 3,
            'order_status' => 'pending',
            'ship_mode' => 'Air Freight',
            'lines' => [
                ['description' => 'Landing strut - left', 'quantity' => 30, 'unit_price_minor' => 69200, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Landing strut - right', 'quantity' => 30, 'unit_price_minor' => 69200, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7708',
            'rfq_number' => 'RFQ-2025-007',
            'supplier' => 'forge-alliance',
            'status' => 'draft',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => null,
            'expected_in_days' => 28,
            'ack_status' => 'draft',
            'ack_days_ago' => null,
            'ordered_days_ago' => null,
            'order_status' => 'pending',
            'ship_mode' => 'LTL Freight',
            'lines' => [
                ['description' => 'Control housing shell', 'quantity' => 70, 'unit_price_minor' => 25500, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Housing backplate', 'quantity' => 70, 'unit_price_minor' => 14800, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7709',
            'rfq_number' => 'RFQ-2025-008',
            'supplier' => 'aurora-fab',
            'status' => 'cancelled',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 14,
            'expected_in_days' => 10,
            'ack_status' => 'declined',
            'ack_days_ago' => 12,
            'ordered_days_ago' => 15,
            'order_status' => 'cancelled',
            'ship_mode' => 'Ground Freight',
            'lines' => [
                ['description' => 'Legacy wiring bracket', 'quantity' => 50, 'unit_price_minor' => 9950, 'uom' => 'EA', 'receiving_status' => 'ncr_raised'],
            ],
        ],
        [
            'po_number' => 'PO-7710',
            'rfq_number' => 'RFQ-2025-009',
            'supplier' => 'orbit-works',
            'status' => 'sent',
            'incoterm' => 'CIF',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 3,
            'expected_in_days' => 17,
            'ack_status' => 'sent',
            'ack_days_ago' => null,
            'ordered_days_ago' => 4,
            'order_status' => 'pending',
            'ship_mode' => 'Air Freight',
            'lines' => [
                ['description' => 'QD manifold block', 'quantity' => 40, 'unit_price_minor' => 49800, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7711',
            'rfq_number' => 'RFQ-2025-010',
            'supplier' => 'cascade-industries',
            'status' => 'sent',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 6,
            'expected_in_days' => 15,
            'ack_status' => 'acknowledged',
            'ack_days_ago' => 5,
            'ordered_days_ago' => 6,
            'order_status' => 'in_production',
            'ship_mode' => 'LTL Freight',
            'lines' => [
                ['description' => 'Frame rail set', 'quantity' => 45, 'unit_price_minor' => 40600, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Caster bracket kit', 'quantity' => 45, 'unit_price_minor' => 16300, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
        [
            'po_number' => 'PO-7712',
            'rfq_number' => 'RFQ-2025-011',
            'supplier' => 'northstar-cnc',
            'status' => 'confirmed',
            'incoterm' => 'FOB',
            'currency' => 'USD',
            'tax_rate' => 0.0825,
            'sent_days_ago' => 5,
            'expected_in_days' => 13,
            'ack_status' => 'acknowledged',
            'ack_days_ago' => 4,
            'ordered_days_ago' => 6,
            'order_status' => 'in_production',
            'ship_mode' => 'Ground Freight',
            'lines' => [
                ['description' => 'Avionics mount plate', 'quantity' => 65, 'unit_price_minor' => 31900, 'uom' => 'EA', 'receiving_status' => 'open'],
                ['description' => 'Gimbal bracket', 'quantity' => 65, 'unit_price_minor' => 20500, 'uom' => 'EA', 'receiving_status' => 'open'],
            ],
        ],
    ];

    private const SUPPLIER_INVOICE_BLUEPRINTS = [
        [
            'invoice_number' => 'INV-AF-1001',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Draft->value,
            'invoice_days_ago' => 4,
            'due_in_days' => 26,
            'matched_status' => 'pending',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 24, 'unit_price_minor' => 18900],
                ['po_line_no' => 20, 'quantity' => 24, 'unit_price_minor' => 9900],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1002',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Submitted->value,
            'invoice_days_ago' => 9,
            'due_in_days' => 21,
            'submitted_days_ago' => 8,
            'matched_status' => 'matched',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 30, 'unit_price_minor' => 18900],
                ['po_line_no' => 20, 'quantity' => 30, 'unit_price_minor' => 9900],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1003',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::BuyerReview->value,
            'invoice_days_ago' => 14,
            'due_in_days' => 20,
            'submitted_days_ago' => 13,
            'matched_status' => 'qty_mismatch',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 50, 'unit_price_minor' => 18800],
                ['po_line_no' => 20, 'quantity' => 40, 'unit_price_minor' => 9800],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1004',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Approved->value,
            'invoice_days_ago' => 24,
            'due_in_days' => 15,
            'submitted_days_ago' => 23,
            'reviewed_days_ago' => 21,
            'matched_status' => 'matched',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 60, 'unit_price_minor' => 18900],
                ['po_line_no' => 20, 'quantity' => 60, 'unit_price_minor' => 9800],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1005',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Paid->value,
            'invoice_days_ago' => 32,
            'due_in_days' => 18,
            'submitted_days_ago' => 31,
            'reviewed_days_ago' => 29,
            'matched_status' => 'matched',
            'payment_reference' => 'ACH-00421',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 80, 'unit_price_minor' => 19000],
                ['po_line_no' => 20, 'quantity' => 80, 'unit_price_minor' => 9900],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1006',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Rejected->value,
            'invoice_days_ago' => 12,
            'due_in_days' => 25,
            'submitted_days_ago' => 11,
            'reviewed_days_ago' => 10,
            'matched_status' => 'price_mismatch',
            'review_note' => 'Unit pricing exceeds PO tolerance on both lines.',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 20, 'unit_price_minor' => 20500],
                ['po_line_no' => 20, 'quantity' => 20, 'unit_price_minor' => 10900],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1007',
            'po_number' => 'PO-7709',
            'status' => InvoiceStatus::Submitted->value,
            'invoice_days_ago' => 6,
            'due_in_days' => 20,
            'submitted_days_ago' => 5,
            'matched_status' => 'unmatched',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 25, 'unit_price_minor' => 9950],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1008',
            'po_number' => 'PO-7709',
            'status' => InvoiceStatus::BuyerReview->value,
            'invoice_days_ago' => 16,
            'due_in_days' => 24,
            'submitted_days_ago' => 15,
            'matched_status' => 'qty_mismatch',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 15, 'unit_price_minor' => 11000],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1009',
            'po_number' => 'PO-7709',
            'status' => InvoiceStatus::Draft->value,
            'invoice_days_ago' => 2,
            'due_in_days' => 28,
            'matched_status' => 'pending',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 30, 'unit_price_minor' => 10000],
            ],
        ],
        [
            'invoice_number' => 'INV-AF-1010',
            'po_number' => 'PO-7701',
            'status' => InvoiceStatus::Approved->value,
            'invoice_days_ago' => 19,
            'due_in_days' => 25,
            'submitted_days_ago' => 18,
            'reviewed_days_ago' => 16,
            'matched_status' => 'matched',
            'lines' => [
                ['po_line_no' => 10, 'quantity' => 90, 'unit_price_minor' => 18700],
                ['po_line_no' => 20, 'quantity' => 90, 'unit_price_minor' => 9700],
            ],
        ],
    ];

    private const WAREHOUSE_BLUEPRINTS = [
        [
            'code' => 'ATX-FG',
            'name' => 'Austin Finished Goods',
            'address' => '7200 Metric Dr',
            'city' => 'Austin',
            'state' => 'TX',
            'country' => 'US',
            'bins' => ['A-01', 'A-02', 'A-03', 'B-01', 'B-02'],
        ],
        [
            'code' => 'DAL-HUB',
            'name' => 'Dallas Regional Hub',
            'address' => '1800 Regal Row',
            'city' => 'Dallas',
            'state' => 'TX',
            'country' => 'US',
            'bins' => ['R-01', 'R-02', 'R-03', 'S-01', 'S-02'],
        ],
    ];

    private const PART_BLUEPRINTS = [
        ['part_number' => 'DRV-2410', 'name' => 'Drone Motor Mount', 'category' => 'Structures', 'uom' => 'ea', 'on_hand' => 420, 'allocated' => 180, 'on_order' => 220, 'default_bin' => 'ATX-FG:A-01', 'description' => 'Anodized aluminum motor mount for VTOL prototypes.'],
        ['part_number' => 'DRV-2411', 'name' => 'Motor Spacer Block', 'category' => 'Structures', 'uom' => 'ea', 'on_hand' => 380, 'allocated' => 120, 'on_order' => 160, 'default_bin' => 'ATX-FG:A-02', 'description' => 'Spacer block between motor plate and frame.'],
        ['part_number' => 'ENL-1180', 'name' => 'Battery Door Shell', 'category' => 'Enclosures', 'uom' => 'ea', 'on_hand' => 210, 'allocated' => 80, 'on_order' => 140, 'default_bin' => 'ATX-FG:B-01', 'description' => 'Powder coated stainless battery door shell.'],
        ['part_number' => 'ENL-1181', 'name' => 'Latch Bracket', 'category' => 'Hardware', 'uom' => 'ea', 'on_hand' => 600, 'allocated' => 190, 'on_order' => 260, 'default_bin' => 'ATX-FG:B-02', 'description' => 'Laser cut latch bracket with passivation.'],
        ['part_number' => 'CMP-3300', 'name' => 'Forward Fairing Shell', 'category' => 'Composites', 'uom' => 'ea', 'on_hand' => 64, 'allocated' => 30, 'on_order' => 40, 'default_bin' => 'DAL-HUB:R-01', 'description' => 'Autoclave cured carbon fairing shell.'],
        ['part_number' => 'CMP-3302', 'name' => 'Rear Fairing Shell', 'category' => 'Composites', 'uom' => 'ea', 'on_hand' => 60, 'allocated' => 26, 'on_order' => 36, 'default_bin' => 'DAL-HUB:R-02', 'description' => 'Rear section carbon fairing assembly.'],
        ['part_number' => 'SHA-5520', 'name' => 'Drive Shaft Short', 'category' => 'Powertrain', 'uom' => 'ea', 'on_hand' => 145, 'allocated' => 40, 'on_order' => 80, 'default_bin' => 'DAL-HUB:R-03', 'description' => 'Short run alloy steel drive shaft.'],
        ['part_number' => 'SHA-5525', 'name' => 'Drive Shaft Long', 'category' => 'Powertrain', 'uom' => 'ea', 'on_hand' => 138, 'allocated' => 44, 'on_order' => 82, 'default_bin' => 'DAL-HUB:S-01', 'description' => 'Extended alloy drive shaft assembly.'],
        ['part_number' => 'BRK-7830', 'name' => 'Sensor Bracket Body', 'category' => 'Structures', 'uom' => 'ea', 'on_hand' => 320, 'allocated' => 110, 'on_order' => 150, 'default_bin' => 'ATX-FG:A-03', 'description' => 'Milled bracket with EMI inserts.'],
        ['part_number' => 'BRK-7835', 'name' => 'Sensor Shield Plate', 'category' => 'Structures', 'uom' => 'ea', 'on_hand' => 310, 'allocated' => 90, 'on_order' => 130, 'default_bin' => 'DAL-HUB:S-02', 'description' => 'Shield plate to pair with bracket set.'],
        ['part_number' => 'AVN-7200', 'name' => 'Avionics Mount Plate', 'category' => 'Avionics', 'uom' => 'ea', 'on_hand' => 205, 'allocated' => 70, 'on_order' => 90, 'default_bin' => 'ATX-FG:A-01', 'description' => 'Machined avionics mount plate.'],
        ['part_number' => 'FRM-6021', 'name' => 'Ground Frame Rail', 'category' => 'Enclosures', 'uom' => 'ea', 'on_hand' => 95, 'allocated' => 30, 'on_order' => 60, 'default_bin' => 'ATX-FG:B-01', 'description' => 'Frame rail set for ground station.'],
    ];

    private const PEER_TENANTS = [
        ['slug' => 'northstar-robotics', 'name' => 'Northstar Robotics', 'plan' => 'growth', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'northstar-robotics.test', 'primary_contact' => 'Jordan Avery', 'primary_email' => 'ops@northstar-robotics.test', 'phone' => '+1-612-555-0193', 'address' => '1400 Industrial Blvd, Minneapolis, MN 55413', 'registration_no' => 'NR-2219', 'tax_id' => '84-7789210', 'rfqs_monthly_used' => 22, 'storage_used_mb' => 820, 'website' => 'https://northstar-robotics.test'],
        ['slug' => 'solis-aero', 'name' => 'Solis Aero Systems', 'plan' => 'starter', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'solis-aero.test', 'primary_contact' => 'Marina Blake', 'primary_email' => 'marina@solis-aero.test', 'phone' => '+1-310-555-0142', 'address' => '4500 Lincoln Blvd, Los Angeles, CA 90292', 'registration_no' => 'SA-0903', 'tax_id' => '95-8821340', 'rfqs_monthly_used' => 9, 'storage_used_mb' => 210, 'website' => 'https://solis-aero.test'],
        ['slug' => 'vector-lift', 'name' => 'Vector Lift Technologies', 'plan' => 'growth', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'vectorlift.test', 'primary_contact' => 'Luis Delgado', 'primary_email' => 'ldelgado@vectorlift.test', 'phone' => '+1-214-555-0190', 'address' => '1999 Bryan St, Dallas, TX 75201', 'registration_no' => 'VL-5542', 'tax_id' => '75-7682301', 'rfqs_monthly_used' => 31, 'storage_used_mb' => 640, 'website' => 'https://vectorlift.test'],
        ['slug' => 'helio-dynamics', 'name' => 'Helio Dynamics', 'plan' => 'starter', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'helio-dynamics.test', 'primary_contact' => 'Sarah Gent', 'primary_email' => 'sgent@helio-dynamics.test', 'phone' => '+1-404-555-0168', 'address' => '600 Peachtree St NE, Atlanta, GA 30308', 'registration_no' => 'HD-8841', 'tax_id' => '58-7815500', 'rfqs_monthly_used' => 12, 'storage_used_mb' => 275, 'website' => 'https://helio-dynamics.test'],
        ['slug' => 'blue-harbor-av', 'name' => 'Blue Harbor Aviation', 'plan' => 'growth', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'blueharbor-av.test', 'primary_contact' => 'Clara Singh', 'primary_email' => 'clara@blueharbor-av.test', 'phone' => '+1-206-555-0170', 'address' => '999 3rd Ave, Seattle, WA 98104', 'registration_no' => 'BH-7741', 'tax_id' => '91-7700244', 'rfqs_monthly_used' => 18, 'storage_used_mb' => 455, 'website' => 'https://blueharbor-av.test'],
        ['slug' => 'equator-mobility', 'name' => 'Equator Mobility', 'plan' => 'starter', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'equator-mobility.test', 'primary_contact' => 'Ryan Keller', 'primary_email' => 'rkeller@equator-mobility.test', 'phone' => '+1-786-555-0112', 'address' => '701 Brickell Ave, Miami, FL 33131', 'registration_no' => 'EM-3308', 'tax_id' => '65-4412280', 'rfqs_monthly_used' => 11, 'storage_used_mb' => 188, 'website' => 'https://equator-mobility.test'],
        ['slug' => 'zenith-range', 'name' => 'Zenith Range Systems', 'plan' => 'growth', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'zenith-range.test', 'primary_contact' => 'Devina Rao', 'primary_email' => 'devina@zenith-range.test', 'phone' => '+1-720-555-0107', 'address' => '1700 Broadway, Denver, CO 80290', 'registration_no' => 'ZR-6620', 'tax_id' => '84-2251900', 'rfqs_monthly_used' => 24, 'storage_used_mb' => 520, 'website' => 'https://zenith-range.test'],
        ['slug' => 'polar-sky-ops', 'name' => 'Polar Sky Ops', 'plan' => 'starter', 'region' => 'NA', 'country' => 'US', 'email_domain' => 'polar-sky.test', 'primary_contact' => 'Ian Brewer', 'primary_email' => 'ian@polar-sky.test', 'phone' => '+1-907-555-0184', 'address' => '700 W 6th Ave, Anchorage, AK 99501', 'registration_no' => 'PS-1090', 'tax_id' => '92-4412001', 'rfqs_monthly_used' => 7, 'storage_used_mb' => 95, 'website' => 'https://polar-sky.test'],
    ];

    private array $supplierIndex = [];
    private array $rfqIndex = [];
    private array $quoteIndex = [];
    private array $purchaseOrderIndex = [];

    public function run(): void
    {
        $company = Company::query()->where('slug', 'elements-supply-dev')->first();

        if ($company === null) {
            $this->command?->warn('PresentationDemoSeeder skipped: buyer company not found.');

            return;
        }

        $user = $this->ensureDemoUser($company);
        $user = $this->promoteDemoUserToOwner($company, $user);
        $this->seedPeerCompanies();

        CompanyContext::forCompany($company->id, function () use ($company, $user): void {
            DB::transaction(function () use ($company, $user): void {
                $this->seedSuppliers($company);
                $user = $this->ensureSupplierPersona($company, $user);
                $this->seedRfqs($company, $user);
                $this->seedPurchaseOrdersAndOrders($company);
                $this->seedSupplierInvoices($company, $user);
                $this->seedInventory($company);
            });
        });

        $this->command?->info('Presentation demo data ready for '.$user->email);
    }

    private function ensureDemoUser(Company $company): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Presentation Buyer',
                'password' => Hash::make('password'),
                'role' => 'buyer_admin',
                'company_id' => $company->id,
                'remember_token' => Str::random(32),
            ]
        );

        if ((int) $user->company_id !== (int) $company->id) {
            $user->company_id = $company->id;
            $user->role = 'buyer_admin';
            $user->save();
        }

        DB::table('company_user')->updateOrInsert(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'buyer_admin',
                'updated_at' => Carbon::now(),
            ]
        );

        return $user;
    }

    private function promoteDemoUserToOwner(Company $company, User $user): User
    {
        $needsSave = false;

        if ($user->role !== 'owner') {
            $user->role = 'owner';
            $needsSave = true;
        }

        if ((int) $user->company_id !== (int) $company->id) {
            $user->company_id = $company->id;
            $needsSave = true;
        }

        if ($needsSave) {
            $user->save();
        }

        $now = Carbon::now();

        DB::table('company_user')->updateOrInsert(
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'owner',
                'is_default' => true,
                'last_used_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        if ((int) $company->owner_user_id !== (int) $user->id) {
            $company->owner_user_id = $user->id;
            $company->save();
        }

        return $user->fresh();
    }

    private function ensureSupplierPersona(Company $company, User $user): User
    {
        $supplier = $this->supplierIndex[self::DEMO_SUPPLIER_CODE] ?? null;

        if ($supplier === null) {
            return $user;
        }

        $needsSave = false;

        if (! $user->supplier_capable) {
            $user->supplier_capable = true;
            $needsSave = true;
        }

        if ((int) $user->default_supplier_id !== (int) $supplier->id) {
            $user->default_supplier_id = $supplier->id;
            $needsSave = true;
        }

        if ($needsSave) {
            $user->save();
            $user->refresh();
        }

        $contact = SupplierContact::query()
            ->withTrashed()
            ->firstOrCreate([
                'company_id' => $company->id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
            ]);

        if ($contact->trashed()) {
            $contact->restore();
        }

        return $user;
    }

    private function seedPeerCompanies(): void
    {
        $planMap = Plan::query()->pluck('id', 'code');
        $now = Carbon::now();

        foreach (self::PEER_TENANTS as $tenant) {
            $planCode = $tenant['plan'];

            if (! $planMap->has($planCode)) {
                continue;
            }

            Company::query()->updateOrCreate(
                ['slug' => $tenant['slug']],
                [
                    'name' => $tenant['name'],
                    'status' => 'active',
                    'region' => $tenant['region'],
                    'country' => $tenant['country'],
                    'plan_id' => $planMap[$planCode],
                    'plan_code' => $planCode,
                    'email_domain' => $tenant['email_domain'],
                    'primary_contact_name' => $tenant['primary_contact'],
                    'primary_contact_email' => $tenant['primary_email'],
                    'primary_contact_phone' => $tenant['phone'],
                    'rfqs_monthly_used' => $tenant['rfqs_monthly_used'],
                    'storage_used_mb' => $tenant['storage_used_mb'],
                    'registration_no' => $tenant['registration_no'],
                    'tax_id' => $tenant['tax_id'],
                    'address' => $tenant['address'],
                    'website' => $tenant['website'],
                    'supplier_status' => CompanySupplierStatus::Approved->value,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedSuppliers(Company $company): void
    {
        $now = Carbon::now();

        foreach (self::SUPPLIER_BLUEPRINTS as $blueprint) {
            $capabilities = [
                'methods' => $blueprint['methods'],
                'materials' => $blueprint['materials'],
                'finishes' => $blueprint['finishes'],
                'tolerances' => $blueprint['tolerances'],
                'industries' => $blueprint['industries'],
                'price_band' => $blueprint['price_band'],
            ];

            $attributes = [
                'capabilities' => $capabilities,
                'email' => $this->makeSupplierEmail($blueprint['code']),
                'phone' => $blueprint['phone'],
                'website' => $blueprint['website'],
                'address' => $blueprint['address'],
                'country' => $blueprint['country'],
                'city' => $blueprint['city'],
                'status' => $blueprint['status'],
                'geo_lat' => $blueprint['lat'],
                'geo_lng' => $blueprint['lng'],
                'lead_time_days' => $blueprint['lead_time_days'],
                'moq' => $blueprint['moq'],
                'rating_avg' => $blueprint['rating_avg'],
                'risk_grade' => RiskGrade::tryFrom($blueprint['risk_grade'])?->value ?? RiskGrade::Medium->value,
                'verified_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('suppliers', 'payment_terms')) {
                $attributes['payment_terms'] = $blueprint['payment_terms'];
            }

            if (Schema::hasColumn('suppliers', 'tax_id')) {
                $attributes['tax_id'] = $blueprint['tax_id'];
            }

            if (Schema::hasColumn('suppliers', 'onboarding_notes')) {
                $attributes['onboarding_notes'] = $blueprint['notes'];
            }

            $supplier = Supplier::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'name' => $blueprint['name'],
                    ],
                    $attributes
                );

            if ($supplier->trashed()) {
                $supplier->restore();
            }

            $this->supplierIndex[$blueprint['code']] = $supplier->fresh();
        }
    }

    private function seedRfqs(Company $company, User $user): void
    {
        $now = Carbon::now();

        foreach (self::RFQ_BLUEPRINTS as $blueprint) {
            $publishAt = $blueprint['publish_days_ago'] !== null
                ? $now->copy()->subDays((int) $blueprint['publish_days_ago'])
                : null;
            $dueAt = $blueprint['due_in_days'] !== null
                ? ($publishAt ?? $now)->copy()->addDays((int) $blueprint['due_in_days'])
                : null;
            $closeAt = $blueprint['closed_days_ago'] ?? null;
            $closeAt = $closeAt !== null ? $now->copy()->subDays((int) $closeAt) : null;

            $items = $blueprint['items'];
            $quantityTotal = collect($items)->sum(static fn (array $item) => (int) $item['qty']);

            $rfq = RFQ::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'number' => $blueprint['number'],
                    ],
                    [
                        'created_by' => $user->id,
                        'title' => $blueprint['title'],
                        'method' => $blueprint['method'],
                        'material' => $blueprint['material'],
                        'finish' => $blueprint['finish'],
                        'tolerance' => $blueprint['tolerance'],
                        'delivery_location' => $blueprint['delivery_location'],
                        'incoterm' => $blueprint['incoterm'],
                        'currency' => $blueprint['currency'],
                        'status' => $blueprint['status'],
                        'publish_at' => $publishAt,
                        'due_at' => $dueAt,
                        'close_at' => $closeAt,
                        'notes' => $blueprint['notes'],
                        'open_bidding' => (bool) ($blueprint['open_bidding'] ?? false),
                        'attachments_count' => $blueprint['attachments'] ?? count($items),
                        'rfq_version' => 1,
                        'quantity_total' => $quantityTotal,
                        'is_partially_awarded' => $blueprint['is_partially_awarded'] ?? false,
                        'meta' => [
                            'priority' => $blueprint['priority'] ?? 'standard',
                        ],
                    ]
                );

            if ($rfq->trashed()) {
                $rfq->restore();
            }

            $rfq->refresh();

            $rfqItems = $this->syncRfqItems($rfq, $items);
            $this->syncRfqInvitations($rfq, $blueprint['invite_suppliers'] ?? [], $blueprint['quotes'] ?? [], $user);
            $this->syncQuotes($rfq, $rfqItems, $blueprint['quotes'] ?? []);

            $this->rfqIndex[$blueprint['number']] = $rfq->fresh('items');
        }
    }

    private function seedSupplierInvoices(Company $company, User $actingUser): void
    {
        $supplier = $this->supplierIndex[self::DEMO_SUPPLIER_CODE] ?? null;

        if ($supplier === null) {
            return;
        }

        $now = Carbon::now();

        foreach (self::SUPPLIER_INVOICE_BLUEPRINTS as $blueprint) {
            $po = $this->purchaseOrderIndex[$blueprint['po_number']] ?? PurchaseOrder::query()
                ->with('lines')
                ->where('company_id', $company->id)
                ->where('po_number', $blueprint['po_number'])
                ->first();

            if ($po === null) {
                continue;
            }

            $linePayloads = $this->buildInvoiceLinePayloads($po, $blueprint['lines'] ?? []);

            if ($linePayloads === []) {
                continue;
            }

            $invoiceDate = $now->copy()->subDays((int) ($blueprint['invoice_days_ago'] ?? 7));
            $dueDate = $invoiceDate->copy()->addDays((int) ($blueprint['due_in_days'] ?? 30));
            $submittedAt = isset($blueprint['submitted_days_ago'])
                ? $now->copy()->subDays((int) $blueprint['submitted_days_ago'])
                : null;
            $reviewedAt = isset($blueprint['reviewed_days_ago'])
                ? $now->copy()->subDays((int) $blueprint['reviewed_days_ago'])
                : null;

            $subtotalMinor = array_sum(array_column($linePayloads, 'line_total_minor'));
            $taxRate = (float) ($blueprint['tax_rate'] ?? 0.0825);
            $taxMinor = (int) round($subtotalMinor * $taxRate);
            $totalMinor = $subtotalMinor + $taxMinor;

            $supplierCompanyId = PurchaseOrderSupplierResolver::resolveSupplierCompanyId($po) ?? $company->id;

            $invoice = Invoice::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'invoice_number' => $blueprint['invoice_number'],
                    ],
                    [
                        'purchase_order_id' => $po->id,
                        'supplier_id' => $supplier->id,
                        'supplier_company_id' => $supplierCompanyId,
                        'invoice_number' => $blueprint['invoice_number'],
                        'invoice_date' => $invoiceDate,
                        'due_date' => $dueDate,
                        'currency' => $po->currency,
                        'subtotal' => $subtotalMinor / 100,
                        'tax_amount' => $taxMinor / 100,
                        'total' => $totalMinor / 100,
                        'subtotal_minor' => $subtotalMinor,
                        'tax_minor' => $taxMinor,
                        'total_minor' => $totalMinor,
                        'status' => $blueprint['status'],
                        'matched_status' => $blueprint['matched_status'] ?? 'pending',
                        'created_by_type' => 'supplier',
                        'created_by_id' => $actingUser->id,
                        'submitted_at' => $submittedAt,
                        'reviewed_at' => $reviewedAt,
                        'reviewed_by_id' => $reviewedAt !== null ? $actingUser->id : null,
                        'review_note' => $blueprint['review_note'] ?? null,
                        'payment_reference' => $blueprint['payment_reference'] ?? null,
                    ]
                );

            if ($invoice->trashed()) {
                $invoice->restore();
            }

            $this->syncInvoiceLines($invoice, $linePayloads);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineBlueprints
     * @return array<int, array<string, mixed>>
     */
    private function buildInvoiceLinePayloads(PurchaseOrder $po, array $lineBlueprints): array
    {
        $po->loadMissing('lines');

        $payloads = [];

        foreach ($lineBlueprints as $lineBlueprint) {
            $lineNo = (int) ($lineBlueprint['po_line_no'] ?? 0);

            if ($lineNo <= 0) {
                continue;
            }

            $poLine = $po->lines->firstWhere('line_no', $lineNo);

            if ($poLine === null) {
                $poLine = PurchaseOrderLine::query()
                    ->where('purchase_order_id', $po->id)
                    ->where('line_no', $lineNo)
                    ->first();
            }

            if ($poLine === null) {
                continue;
            }

            $unitPriceMinor = (int) ($lineBlueprint['unit_price_minor'] ?? $poLine->unit_price_minor ?? (int) round((float) ($poLine->unit_price ?? 0) * 100));
            $quantity = (int) ($lineBlueprint['quantity'] ?? $poLine->quantity ?? 0);

            if ($unitPriceMinor <= 0 || $quantity <= 0) {
                continue;
            }

            $payloads[] = [
                'po_line_id' => $poLine->id,
                'description' => $lineBlueprint['description'] ?? $poLine->description,
                'quantity' => $quantity,
                'uom' => $lineBlueprint['uom'] ?? $poLine->uom,
                'unit_price_minor' => $unitPriceMinor,
                'line_total_minor' => $unitPriceMinor * $quantity,
                'currency' => $lineBlueprint['currency'] ?? $po->currency,
            ];
        }

        return $payloads;
    }

    /**
     * @param  array<int, array<string, mixed>>  $linePayloads
     */
    private function syncInvoiceLines(Invoice $invoice, array $linePayloads): void
    {
        $lineIds = [];

        foreach ($linePayloads as $payload) {
            $line = InvoiceLine::query()->updateOrCreate(
                [
                    'invoice_id' => $invoice->id,
                    'po_line_id' => $payload['po_line_id'],
                ],
                [
                    'description' => $payload['description'],
                    'quantity' => $payload['quantity'],
                    'uom' => $payload['uom'],
                    'unit_price' => $payload['unit_price_minor'] / 100,
                    'currency' => $payload['currency'],
                    'unit_price_minor' => $payload['unit_price_minor'],
                    'line_total_minor' => $payload['line_total_minor'],
                ]
            );

            $lineIds[] = $line->id;
        }

        if ($lineIds === []) {
            return;
        }

        $invoice->lines()
            ->whereNotIn('id', $lineIds)
            ->delete();
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function syncRfqItems(RFQ $rfq, array $items): Collection
    {
        $records = collect();

        foreach ($items as $item) {
            $targetMinor = (int) ($item['target_price_minor'] ?? 0);

            $record = RfqItem::query()->updateOrCreate(
                [
                    'rfq_id' => $rfq->id,
                    'line_no' => $item['line_no'],
                ],
                [
                    'part_number' => $item['part_number'],
                    'description' => $item['description'],
                    'method' => $item['method'],
                    'material' => $item['material'],
                    'finish' => $item['finish'],
                    'tolerance' => $item['tolerance'],
                    'qty' => $item['qty'],
                    'uom' => $item['uom'],
                    'target_price' => $targetMinor / 100,
                    'target_price_minor' => $targetMinor,
                    'currency' => $rfq->currency,
                    'specs_json' => [
                        'notes' => $item['notes'] ?? null,
                        'revision' => 'A',
                    ],
                ]
            );

            $records->push($record);
        }

        return $records;
    }

    /**
     * @param array<int, string> $inviteCodes
     * @param array<int, array<string, mixed>> $quotes
     */
    private function syncRfqInvitations(RFQ $rfq, array $inviteCodes, array $quotes, User $user): void
    {
        $quotedSuppliers = collect($quotes)->pluck('supplier')->filter()->all();

        foreach ($inviteCodes as $code) {
            $supplier = $this->supplierIndex[$code] ?? null;

            if ($supplier === null) {
                continue;
            }

            RfqInvitation::query()->updateOrCreate(
                [
                    'rfq_id' => $rfq->id,
                    'supplier_id' => $supplier->id,
                ],
                [
                    'company_id' => $rfq->company_id,
                    'invited_by' => $user->id,
                    'status' => in_array($code, $quotedSuppliers, true) ? RfqInvitation::STATUS_ACCEPTED : RfqInvitation::STATUS_PENDING,
                ]
            );
        }
    }

    /**
     * @param Collection<int, RfqItem> $rfqItems
     * @param array<int, array<string, mixed>> $quotes
     */
    private function syncQuotes(RFQ $rfq, Collection $rfqItems, array $quotes): void
    {
        $now = Carbon::now();

        foreach ($quotes as $quoteData) {
            $supplier = $this->supplierIndex[$quoteData['supplier']] ?? null;

            if ($supplier === null) {
                continue;
            }

            $quote = Quote::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $rfq->company_id,
                        'rfq_id' => $rfq->id,
                        'supplier_id' => $supplier->id,
                    ],
                    [
                        'currency' => $rfq->currency,
                        'status' => $quoteData['status'] ?? 'submitted',
                        'lead_time_days' => $quoteData['lead_time_days'] ?? null,
                        'incoterm' => $rfq->incoterm,
                        'payment_terms' => 'Net 30',
                        'notes' => $quoteData['note'] ?? null,
                        'revision_no' => 1,
                        'unit_price' => 0,
                        'submitted_at' => $now->copy()->subDays($quoteData['submitted_days_ago'] ?? 3),
                        'attachments_count' => 0,
                        'shortlisted_at' => ($quoteData['shortlisted'] ?? false) ? $now->copy()->subDays(1) : null,
                    ]
                );

            if ($quote->trashed()) {
                $quote->restore();
            }

            $totals = $this->updateQuoteItems($quote, $rfqItems, $quoteData['price_factor'] ?? 1.0);

            $quote->fill($totals)->save();

            $this->quoteIndex[$this->quoteKey($rfq->number, $quoteData['supplier'])] = $quote->fresh(['items']);
        }
    }

    private function updateQuoteItems(Quote $quote, Collection $items, float $priceFactor): array
    {
        $subtotalMinor = 0;
        $totalQty = 0;

        foreach ($items as $item) {
            $baseMinor = $item->target_price_minor ?? (int) round(($item->target_price ?? 0) * 100);
            $baseMinor = $baseMinor > 0 ? $baseMinor : 15000;
            $unitPriceMinor = (int) round($baseMinor * $priceFactor);
            $lineQty = (int) ($item->qty ?? 1);
            $lineTotal = $unitPriceMinor * $lineQty;
            $subtotalMinor += $lineTotal;
            $totalQty += $lineQty;

            QuoteItem::query()->updateOrCreate(
                [
                    'quote_id' => $quote->id,
                    'rfq_item_id' => $item->id,
                ],
                [
                    'unit_price' => $unitPriceMinor / 100,
                    'currency' => $quote->currency,
                    'unit_price_minor' => $unitPriceMinor,
                    'lead_time_days' => $quote->lead_time_days,
                ]
            );
        }

        $taxMinor = (int) round($subtotalMinor * 0.0825);
        $totalMinor = $subtotalMinor + $taxMinor;

        return [
            'subtotal' => $subtotalMinor / 100,
            'subtotal_minor' => $subtotalMinor,
            'tax_amount' => $taxMinor / 100,
            'tax_amount_minor' => $taxMinor,
            'total_price' => $totalMinor / 100,
            'total_price_minor' => $totalMinor,
            'unit_price' => $totalQty > 0 ? ($subtotalMinor / $totalQty) / 100 : null,
        ];
    }

    private function seedPurchaseOrdersAndOrders(Company $company): void
    {
        $now = Carbon::now();

        foreach (self::PURCHASE_ORDER_BLUEPRINTS as $blueprint) {
            $supplier = $this->supplierIndex[$blueprint['supplier']] ?? null;

            if ($supplier === null) {
                continue;
            }

            $rfq = $blueprint['rfq_number'] ? ($this->rfqIndex[$blueprint['rfq_number']] ?? null) : null;
            $quote = $blueprint['rfq_number']
                ? ($this->quoteIndex[$this->quoteKey($blueprint['rfq_number'], $blueprint['supplier'])] ?? null)
                : null;

            $sentAt = $blueprint['sent_days_ago'] !== null
                ? $now->copy()->subDays((int) $blueprint['sent_days_ago'])
                : null;
            $expectedAt = $blueprint['expected_in_days'] !== null
                ? ($sentAt ?? $now)->copy()->addDays((int) $blueprint['expected_in_days'])
                : null;
            $ackAt = $blueprint['ack_days_ago'] !== null
                ? $now->copy()->subDays((int) $blueprint['ack_days_ago'])
                : null;
            $orderedAt = $blueprint['ordered_days_ago'] !== null
                ? $now->copy()->subDays((int) $blueprint['ordered_days_ago'])
                : $sentAt;

            $lineTotals = $this->calculatePurchaseOrderTotals($blueprint['lines'], (float) ($blueprint['tax_rate'] ?? 0.0825));

            $po = PurchaseOrder::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'po_number' => $blueprint['po_number'],
                    ],
                    [
                        'supplier_id' => $supplier->id,
                        'rfq_id' => $rfq?->id,
                        'quote_id' => $quote?->id,
                        'currency' => $blueprint['currency'],
                        'incoterm' => $blueprint['incoterm'],
                        'status' => $blueprint['status'],
                        'revision_no' => 0,
                        'ordered_at' => $orderedAt,
                        'expected_at' => $expectedAt,
                        'sent_at' => $sentAt,
                        'ack_status' => $blueprint['ack_status'],
                        'acknowledged_at' => $ackAt,
                        'subtotal' => $lineTotals['subtotal_minor'] / 100,
                        'subtotal_minor' => $lineTotals['subtotal_minor'],
                        'tax_amount' => $lineTotals['tax_minor'] / 100,
                        'tax_amount_minor' => $lineTotals['tax_minor'],
                        'total' => $lineTotals['total_minor'] / 100,
                        'total_minor' => $lineTotals['total_minor'],
                    ]
                );

            if ($po->trashed()) {
                $po->restore();
            }

            $this->syncPurchaseOrderLines($po, $blueprint['lines']);
            $this->syncOrderRecord($po, $lineTotals['total_minor'], $blueprint, $orderedAt, $ackAt, $expectedAt);

            $this->purchaseOrderIndex[$blueprint['po_number']] = $po->fresh(['lines']);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $lines
     * @return array{subtotal_minor:int,tax_minor:int,total_minor:int}
     */
    private function calculatePurchaseOrderTotals(array $lines, float $taxRate): array
    {
        $subtotalMinor = 0;

        foreach ($lines as $line) {
            $subtotalMinor += (int) $line['unit_price_minor'] * (int) $line['quantity'];
        }

        $taxMinor = (int) round($subtotalMinor * $taxRate);

        return [
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => $taxMinor,
            'total_minor' => $subtotalMinor + $taxMinor,
        ];
    }

    private function syncPurchaseOrderLines(PurchaseOrder $po, array $lines): void
    {
        $lineNumber = 10;

        foreach ($lines as $line) {
            PurchaseOrderLine::query()->updateOrCreate(
                [
                    'purchase_order_id' => $po->id,
                    'line_no' => $line['line_no'] ?? $lineNumber,
                ],
                [
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'],
                    'unit_price' => $line['unit_price_minor'] / 100,
                    'currency' => $po->currency,
                    'unit_price_minor' => $line['unit_price_minor'],
                    'delivery_date' => $po->expected_at?->toDateString(),
                    'received_qty' => $line['received_qty'] ?? 0,
                    'receiving_status' => $line['receiving_status'] ?? 'open',
                ]
            );

            $lineNumber += 10;
        }
    }

    private function syncOrderRecord(
        PurchaseOrder $po,
        int $totalMinor,
        array $blueprint,
        ?Carbon $orderedAt,
        ?Carbon $ackAt,
        ?Carbon $expectedAt
    ): void {
        $orderedQty = collect($blueprint['lines'])->sum(static fn (array $line) => (int) $line['quantity']);
        $shippedQty = match ($blueprint['order_status']) {
            'delivered' => $orderedQty,
            'in_transit' => (int) floor($orderedQty * 0.7),
            'in_production' => (int) floor($orderedQty * 0.4),
            default => (int) floor($orderedQty * 0.2),
        };

        $timeline = array_values(array_filter([
            $orderedAt ? ['type' => 'status', 'label' => 'PO Released', 'occurred_at' => $orderedAt->toIso8601String()] : null,
            $ackAt ? ['type' => 'status', 'label' => 'Supplier Acknowledged', 'occurred_at' => $ackAt->toIso8601String()] : null,
            $expectedAt ? ['type' => 'milestone', 'label' => 'Target Dock Date', 'occurred_at' => $expectedAt->toIso8601String()] : null,
        ]));

        $order = Order::query()
            ->withTrashed()
            ->updateOrCreate(
                [
                    'company_id' => $po->company_id,
                    'purchase_order_id' => $po->id,
                ],
                [
                    'number' => $po->po_number,
                    'so_number' => 'SO-'.Str::upper(Str::random(6)),
                    'status' => $blueprint['order_status'],
                    'currency' => $po->currency,
                    'total_minor' => $totalMinor,
                    'ordered_qty' => $orderedQty,
                    'shipped_qty' => max($shippedQty, 0),
                    'timeline' => $timeline,
                    'shipping' => [
                        'mode' => $blueprint['ship_mode'],
                        'incoterm' => $po->incoterm,
                    ],
                    'metadata' => [
                        'rfq_number' => $blueprint['rfq_number'],
                    ],
                    'ordered_at' => $orderedAt,
                    'acknowledged_at' => $ackAt,
                    'delivered_at' => $blueprint['order_status'] === 'delivered' ? Carbon::now()->subDays(1) : null,
                ]
            );

        if ($order->trashed()) {
            $order->restore();
        }
    }

    private function seedInventory(Company $company): void
    {
        $warehouses = $this->ensureWarehouses($company);
        $bins = $this->ensureBins($warehouses);

        foreach (self::PART_BLUEPRINTS as $partData) {
            $part = Part::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'part_number' => $partData['part_number'],
                    ],
                    [
                        'name' => $partData['name'],
                        'description' => $partData['description'],
                        'category' => $partData['category'],
                        'uom' => $partData['uom'],
                        'active' => true,
                        'meta' => [
                            'category' => $partData['category'],
                        ],
                    ]
                );

            if ($part->trashed()) {
                $part->restore();
            }

            [$warehouseCode, $binCode] = explode(':', $partData['default_bin']);
            $warehouse = $warehouses[$warehouseCode] ?? reset($warehouses);
            $bin = $bins[$partData['default_bin']] ?? null;

            Inventory::query()->updateOrCreate(
                [
                    'part_id' => $part->id,
                    'warehouse_id' => $warehouse->id,
                    'bin_id' => $bin?->id,
                ],
                [
                    'on_hand' => $partData['on_hand'],
                    'allocated' => $partData['allocated'],
                    'on_order' => $partData['on_order'],
                    'uom' => $partData['uom'],
                ]
            );
        }
    }

    /**
     * @return array<string, Warehouse>
     */
    private function ensureWarehouses(Company $company): array
    {
        $result = [];

        foreach (self::WAREHOUSE_BLUEPRINTS as $blueprint) {
            $warehouse = Warehouse::query()
                ->withTrashed()
                ->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $blueprint['code'],
                    ],
                    [
                        'name' => $blueprint['name'],
                        'address_json' => [
                            'line1' => $blueprint['address'],
                            'city' => $blueprint['city'],
                            'state' => $blueprint['state'],
                            'country' => $blueprint['country'],
                        ],
                        'active' => true,
                    ]
                );

            if ($warehouse->trashed()) {
                $warehouse->restore();
            }

            $result[$blueprint['code']] = $warehouse;
        }

        return $result;
    }

    /**
     * @param array<string, Warehouse> $warehouses
     * @return array<string, Bin>
     */
    private function ensureBins(array $warehouses): array
    {
        $result = [];

        foreach (self::WAREHOUSE_BLUEPRINTS as $blueprint) {
            $warehouse = $warehouses[$blueprint['code']] ?? null;

            if ($warehouse === null) {
                continue;
            }

            foreach ($blueprint['bins'] as $code) {
                $bin = Bin::query()
                    ->withTrashed()
                    ->updateOrCreate(
                        [
                            'warehouse_id' => $warehouse->id,
                            'code' => $code,
                        ],
                        [
                            'company_id' => $warehouse->company_id,
                            'name' => 'Bin '.$code,
                        ]
                    );

                if ($bin->trashed()) {
                    $bin->restore();
                }

                $result[$blueprint['code'].':'.$code] = $bin;
            }
        }

        return $result;
    }

    private function makeSupplierEmail(string $code): string
    {
        return $code.'@demo-suppliers.test';
    }

    private function quoteKey(string $rfqNumber, string $supplierCode): string
    {
        return $rfqNumber.'|'.$supplierCode;
    }
}
