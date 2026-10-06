<?php

namespace App\Services;

use App\Models\BusinessDocument;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\SalesOrder;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class DocumentParserService
{
    /**
     * Parse an uploaded document and extract structured business data with confidence indicators.
     */
    public function parse(UploadedFile $file, ?string $documentTypeHint = null, ?int $tenantId = null): array
    {
        $tenantId = $tenantId ?: TenantManager::getTenantId();
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();

        // 1. Extract raw text from file
        $rawText = '';
        if ($ext === 'pdf') {
            $rawText = $this->extractTextFromPdf($file->getRealPath());
        } elseif (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $rawText = $this->extractTextFromSpreadsheet($file->getRealPath(), $ext);
        } else {
            // Images / fallback
            $rawText = $originalName.' '.pathinfo($originalName, PATHINFO_FILENAME);
        }

        // 2. Determine Document Type (PO, INVOICE, LR)
        $docType = $this->detectDocumentType($rawText, $originalName, $documentTypeHint);

        // 3. Extract Document Number
        $docNumber = $this->extractDocumentNumber($rawText, $originalName, $docType);

        // 4. Extract Dates
        $dates = $this->extractDates($rawText);

        // 5. Match Customer in Tenant DB
        $matchedCustomer = $this->matchCustomer($rawText, $tenantId);

        // 6. Match Products and Line Items
        $items = $this->extractLineItems($rawText, $tenantId);

        // 7. Extract Amounts & Financials
        $amounts = $this->extractAmounts($rawText, $items);

        // 8. Extract Payment Terms & Delivery Info
        $terms = $this->extractTermsAndDelivery($rawText, $matchedCustomer);

        // 9. Detect Duplicates
        $duplicateInfo = $this->checkDuplicate($docType, $docNumber, $matchedCustomer['id'] ?? null, $tenantId);

        // 10. Auto-Suggest Linked ERP Records
        $suggestedLinks = $this->suggestLinkedRecords($docType, $docNumber, $matchedCustomer['id'] ?? null, $tenantId);

        return [
            'document_type' => $docType,
            'document_number' => $docNumber,
            'document_date' => $dates['document_date'] ?? date('Y-m-d'),
            'due_date' => $dates['due_date'] ?? ($docType === 'INVOICE' ? date('Y-m-d', strtotime('+30 days')) : null),
            'customer' => $matchedCustomer,
            'customer_id' => $matchedCustomer['id'] ?? null,
            'items' => $items,
            'total_amount' => $amounts['total_amount'],
            'taxable_amount' => $amounts['taxable_amount'],
            'tax_amount' => $amounts['tax_amount'],
            'payment_terms' => $terms['payment_terms'],
            'delivery_location' => $terms['delivery_location'],
            'duplicate_warning' => $duplicateInfo,
            'suggested_links' => $suggestedLinks,
            'original_filename' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $mime,
            'confidence_score' => $this->calculateConfidenceScore($docNumber, $matchedCustomer, $items, $amounts),
        ];
    }

    /**
     * Native PDF stream text extractor: parses uncompressed strings and flate-compressed streams.
     */
    protected function extractTextFromPdf(string $path): string
    {
        $content = @file_get_contents($path);
        if (! $content) {
            return '';
        }

        $text = '';

        // Extract uncompressed text tokens between BT (Begin Text) and ET (End Text)
        if (preg_match_all('/BT[\s\S]*?ET/', $content, $btMatches)) {
            foreach ($btMatches[0] as $btBlock) {
                if (preg_match_all('/\((.*?)\)[\s]*T[jJ]/', $btBlock, $tjMatches)) {
                    $text .= ' '.implode(' ', $tjMatches[1]);
                }
            }
        }

        // Decompress FlateDecode streams
        if (preg_match_all('/stream[\r\n]+([\s\S]*?)[\r\n]+endstream/m', $content, $streamMatches)) {
            foreach ($streamMatches[1] as $stream) {
                $uncompressed = @gzuncompress($stream);
                if ($uncompressed === false) {
                    $uncompressed = @gzinflate($stream);
                }
                if ($uncompressed) {
                    // Extract strings in parentheses
                    if (preg_match_all('/\((.*?)\)/', $uncompressed, $parenMatches)) {
                        $cleaned = array_filter($parenMatches[1], fn ($s) => strlen(trim($s)) > 1 && ! preg_match('/^[^\x20-\x7E]+$/', $s));
                        $text .= ' '.implode(' ', $cleaned);
                    }
                    // Extract strings in hex brackets <48656c6c6f>
                    if (preg_match_all('/<([0-9A-Fa-f]{4,})>/', $uncompressed, $hexMatches)) {
                        foreach ($hexMatches[1] as $hex) {
                            $decoded = @hex2bin($hex);
                            if ($decoded && ctype_print($decoded)) {
                                $text .= ' '.$decoded;
                            }
                        }
                    }
                }
            }
        }

        // Clean octal codes and escape slashes
        $text = preg_replace_callback('/\\\\([0-7]{1,3})/', fn ($m) => chr(octdec($m[1])), $text);
        $text = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Extract structured text from Excel spreadsheets using PhpSpreadsheet.
     */
    protected function extractTextFromSpreadsheet(string $path, string $ext): string
    {
        try {
            if (class_exists(IOFactory::class)) {
                $spreadsheet = IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);

                $lines = [];
                foreach ($rows as $row) {
                    $rowValues = array_filter(array_map('trim', $row));
                    if (! empty($rowValues)) {
                        $lines[] = implode(' | ', $rowValues);
                    }
                }

                return implode("\n", $lines);
            }
        } catch (\Throwable $e) {
            Log::warning('Spreadsheet extraction fallback: '.$e->getMessage());
        }

        return '';
    }

    /**
     * Detect whether the document is a PO, Tax Invoice, or LR.
     */
    protected function detectDocumentType(string $text, string $filename, ?string $hint = null): string
    {
        if (! empty($hint) && in_array(strtoupper($hint), ['PO', 'INVOICE', 'LR'])) {
            return strtoupper($hint);
        }

        $combined = strtolower($text.' '.$filename);

        if (preg_match('/(lorry\s*receipt|consignment\s*note|bilty|transporter\s*copy|lr\s*no|goods\s*consignment)/i', $combined)) {
            return 'LR';
        }

        if (preg_match('/(tax\s*invoice|retail\s*invoice|bill\s*of\s*supply|gst\s*invoice|invoice\s*no)/i', $combined)) {
            return 'INVOICE';
        }

        if (preg_match('/(purchase\s*order|buyer\s*po|p\.o\.\s*no|po\s*number|order\s*confirmation)/i', $combined)) {
            return 'PO';
        }

        // Filename cues
        if (preg_match('/(^|[^a-z])po[-_0-9]/i', $filename)) {
            return 'PO';
        }
        if (preg_match('/(^|[^a-z])inv[-_0-9]/i', $filename)) {
            return 'INVOICE';
        }
        if (preg_match('/(^|[^a-z])lr[-_0-9]/i', $filename)) {
            return 'LR';
        }

        return 'PO'; // Default to PO for customer uploads
    }

    /**
     * Extract PO, Invoice, or LR Number.
     */
    protected function extractDocumentNumber(string $text, string $filename, string $docType): string
    {
        $patterns = [];

        if ($docType === 'PO') {
            $patterns = [
                '/(?:P\.?O\.?\s*(?:No\.?|Number|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/(?:Purchase\s*Order\s*(?:No\.?|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/(?:Order\s*No\.?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/\b(PO[-_ ]?[0-9]{2,10}[A-Z0-9]*)\b/i',
            ];
        } elseif ($docType === 'INVOICE') {
            $patterns = [
                '/(?:Tax\s*Invoice\s*(?:No\.?|Number|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/(?:Invoice\s*(?:No\.?|Number|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/\b(INV[-_ ]?[0-9]{2,10}[A-Z0-9]*)\b/i',
            ];
        } else {
            // LR
            $patterns = [
                '/(?:L\.?R\.?\s*(?:No\.?|Number|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/(?:Bilty\s*(?:No\.?|#)?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/(?:Consignment\s*No\.?[:\s-]*)([A-Z0-9\-_/]+)/i',
                '/\b(LR[-_ ]?[0-9]{5,15})\b/i',
            ];
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $num = trim($matches[1]);
                if (strlen($num) >= 3 && ! preg_match('/^(date|dated|page|total)$/i', $num)) {
                    return strtoupper($num);
                }
            }
        }

        // Filename fallback
        if (preg_match('/(PO[-_ ]?[0-9A-Z]+|INV[-_ ]?[0-9A-Z]+|LR[-_ ]?[0-9A-Z]+)/i', $filename, $matches)) {
            return strtoupper(str_replace(' ', '-', $matches[1]));
        }

        // Generate clean provisional number
        $prefix = match ($docType) {
            'PO' => 'PO',
            'INVOICE' => 'INV',
            'LR' => 'LR',
            default => 'DOC',
        };

        return $prefix.'-'.date('Ymd').'-'.rand(100, 999);
    }

    /**
     * Extract Document Date and Due Date.
     */
    protected function extractDates(string $text): array
    {
        $dates = [
            'document_date' => null,
            'due_date' => null,
        ];

        // Match common date patterns: 14/09/2026, 14-09-2026, 14-Sep-2026, 2026-09-14
        $pattern = '/\b(\d{1,2}[\/\-\.](?:\d{1,2}|[A-Za-z]{3})[\/\-\.]\d{2,4}|\d{4}[\/\-\.]\d{1,2}[\/\-\.]\d{1,2})\b/';
        if (preg_match_all($pattern, $text, $matches)) {
            $foundDates = [];
            foreach ($matches[0] as $rawDate) {
                try {
                    $cleaned = str_replace(['/', '.'], '-', $rawDate);
                    $carbon = Carbon::parse($cleaned);
                    // Filter out unlikely dates (e.g. before 2020 or after 2035)
                    if ($carbon->year >= 2020 && $carbon->year <= 2035) {
                        $foundDates[] = $carbon->toDateString();
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            if (! empty($foundDates)) {
                $dates['document_date'] = $foundDates[0];
                if (count($foundDates) > 1) {
                    $dates['due_date'] = $foundDates[count($foundDates) - 1];
                }
            }
        }

        return $dates;
    }

    /**
     * Match Customer name in tenant database.
     */
    protected function matchCustomer(string $text, int $tenantId): ?array
    {
        $customers = Customer::where('tenant_id', $tenantId)->get();
        if ($customers->isEmpty()) {
            return null;
        }

        $bestCustomer = null;
        $highestScore = 0;

        foreach ($customers as $c) {
            $name = trim($c->company_name);
            $code = trim($c->customer_code);

            // Exact match
            if (stripos($text, $name) !== false) {
                return [
                    'id' => $c->id,
                    'company_name' => $c->company_name,
                    'customer_code' => $c->customer_code,
                    'primary_contact_person' => $c->primary_contact_person,
                    'primary_phone' => $c->primary_phone,
                    'city' => $c->city,
                    'state' => $c->state,
                    'confidence' => 95,
                ];
            }

            // Customer code match
            if (! empty($code) && stripos($text, $code) !== false) {
                return [
                    'id' => $c->id,
                    'company_name' => $c->company_name,
                    'customer_code' => $c->customer_code,
                    'primary_contact_person' => $c->primary_contact_person,
                    'primary_phone' => $c->primary_phone,
                    'city' => $c->city,
                    'state' => $c->state,
                    'confidence' => 90,
                ];
            }

            // Word token match
            $words = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', $name)), fn ($w) => strlen($w) > 3);
            $matchedWords = 0;
            foreach ($words as $w) {
                if (stripos($text, $w) !== false) {
                    $matchedWords++;
                }
            }

            $score = count($words) > 0 ? ($matchedWords / count($words)) * 100 : 0;
            if ($score > $highestScore && $score >= 60) {
                $highestScore = $score;
                $bestCustomer = $c;
            }
        }

        if ($bestCustomer) {
            return [
                'id' => $bestCustomer->id,
                'company_name' => $bestCustomer->company_name,
                'customer_code' => $bestCustomer->customer_code,
                'primary_contact_person' => $bestCustomer->primary_contact_person,
                'primary_phone' => $bestCustomer->primary_phone,
                'city' => $bestCustomer->city,
                'state' => $bestCustomer->state,
                'confidence' => (int) $highestScore,
            ];
        }

        // Fallback: Return first active customer as auto-suggestion
        $firstCust = $customers->first();

        return [
            'id' => $firstCust->id,
            'company_name' => $firstCust->company_name,
            'customer_code' => $firstCust->customer_code,
            'primary_contact_person' => $firstCust->primary_contact_person,
            'primary_phone' => $firstCust->primary_phone,
            'city' => $firstCust->city,
            'state' => $firstCust->state,
            'confidence' => 40,
        ];
    }

    /**
     * Extract line items matching known catalog products.
     */
    protected function extractLineItems(string $text, int $tenantId): array
    {
        $products = Product::where('tenant_id', $tenantId)->get();
        $matchedItems = [];

        foreach ($products as $p) {
            $pName = $p->product_name;
            $code = $p->product_code;

            // Search product in text
            if (stripos($text, $pName) !== false || (! empty($code) && stripos($text, $code) !== false)) {
                // Try to find quantity associated with this product in surrounding text
                $qty = 1000.0;
                $rate = (float) ($p->standard_rate ?: 220.0);

                if (preg_match('/'.preg_quote($pName, '/').'[\s\S]{0,60}?(\d+(?:,\d+)*(?:\.\d+)?)\s*(?:KG|KGS|MT|BAGS|QTL)/i', $text, $qMatch)) {
                    $qty = (float) str_replace(',', '', $qMatch[1]);
                }

                if (preg_match('/'.preg_quote($pName, '/').'[\s\S]{0,60}?(?:@|Rate|Price|₹|INR)?\s*(\d+(?:\.\d+)?)\s*(?:\/KG|\/kg)?/i', $text, $rMatch)) {
                    $rate = (float) $rMatch[1];
                }

                $matchedItems[] = [
                    'product_id' => $p->id,
                    'product_name' => $p->product_name,
                    'product_code' => $p->product_code,
                    'quantity' => $qty,
                    'unit' => 'KG',
                    'rate' => $rate,
                    'taxable_amount' => round($qty * $rate, 2),
                ];
            }
        }

        // If no products matched directly from text, default to the top primary product
        if (empty($matchedItems) && $products->isNotEmpty()) {
            $primary = $products->first();
            $matchedItems[] = [
                'product_id' => $primary->id,
                'product_name' => $primary->product_name,
                'product_code' => $primary->product_code,
                'quantity' => 1000.0,
                'unit' => 'KG',
                'rate' => (float) ($primary->standard_rate ?: 220.0),
                'taxable_amount' => round(1000.0 * (float) ($primary->standard_rate ?: 220.0), 2),
            ];
        }

        return $matchedItems;
    }

    /**
     * Extract Total, Taxable, and GST amounts.
     */
    protected function extractAmounts(string $text, array $items): array
    {
        $taxable = 0.0;
        foreach ($items as $it) {
            $taxable += (float) ($it['taxable_amount'] ?? 0);
        }

        // Check text for Total amount keywords
        $totalPatterns = [
            '/(?:Grand\s*Total|Total\s*Amount|Total\s*Value|Invoice\s*Total|Net\s*Payable)[:\s]*₹?\s*([0-9,]+(?:\.[0-9]{2})?)/i',
            '/(?:Total)[:\s]*₹?\s*([0-9,]+(?:\.[0-9]{2})?)/i',
        ];

        $total = 0.0;
        foreach ($totalPatterns as $tp) {
            if (preg_match($tp, $text, $matches)) {
                $val = (float) str_replace(',', '', $matches[1]);
                if ($val > 100) {
                    $total = $val;
                    break;
                }
            }
        }

        if ($total <= 0) {
            // Default 5% GST on dehydrated products
            $gst = round($taxable * 0.05, 2);
            $total = $taxable + $gst;
        } else {
            $gst = round($total - $taxable, 2);
            if ($gst < 0) {
                $gst = round($total * 0.05, 2);
                $taxable = round($total - $gst, 2);
            }
        }

        return [
            'taxable_amount' => round($taxable, 2),
            'tax_amount' => round($gst, 2),
            'total_amount' => round($total, 2),
        ];
    }

    /**
     * Extract Payment terms and Delivery destination.
     */
    protected function extractTermsAndDelivery(string $text, ?array $matchedCustomer): array
    {
        $terms = '30 Days Credit';
        if (preg_match('/(100%\s*advance|advance|immediate|cad|against\s*delivery|\d+\s*days?\s*credit)/i', $text, $matches)) {
            $terms = ucwords(trim($matches[1]));
        }

        $delivery = $matchedCustomer ? ($matchedCustomer['city'].', '.$matchedCustomer['state']) : 'Customer Godown';
        if (preg_match('/(?:Delivery\s*To|Ship\s*To|Consignee\s*Address|Destination)[:\s]*([^\r\n]{5,60})/i', $text, $matches)) {
            $delivery = trim($matches[1]);
        }

        return [
            'payment_terms' => $terms,
            'delivery_location' => $delivery,
        ];
    }

    /**
     * Check if duplicate PO or Invoice already exists.
     */
    public function checkDuplicate(string $docType, string $docNumber, ?int $customerId, int $tenantId): ?array
    {
        $cleanNumber = trim($docNumber);
        if (empty($cleanNumber)) {
            return null;
        }

        // 1. Check in BusinessDocument
        $existingDoc = BusinessDocument::where('tenant_id', $tenantId)
            ->where('document_type', $docType)
            ->where('document_number', $cleanNumber)
            ->first();

        if ($existingDoc) {
            return [
                'is_duplicate' => true,
                'source' => 'business_documents',
                'document_id' => $existingDoc->id,
                'document_number' => $existingDoc->document_number,
                'customer_name' => $existingDoc->customer?->company_name ?? 'N/A',
                'created_at' => $existingDoc->created_at->format('d M Y'),
                'current_version' => $existingDoc->current_version,
                'message' => "Possible duplicate: {$docType} #{$cleanNumber} is already registered in the Document Centre for ".($existingDoc->customer?->company_name ?? 'this tenant').'.',
            ];
        }

        // 2. For PO: check SalesOrder
        if ($docType === 'PO') {
            $existingOrder = SalesOrder::where('tenant_id', $tenantId)
                ->where(function ($q) use ($cleanNumber) {
                    $q->where('po_number', $cleanNumber)
                        ->orWhere('order_number', $cleanNumber);
                })
                ->first();

            if ($existingOrder) {
                return [
                    'is_duplicate' => true,
                    'source' => 'sales_orders',
                    'sales_order_id' => $existingOrder->id,
                    'order_number' => $existingOrder->order_number,
                    'customer_name' => $existingOrder->customer?->company_name ?? 'N/A',
                    'created_at' => $existingOrder->created_at->format('d M Y'),
                    'message' => "Possible duplicate PO: Order #{$existingOrder->order_number} already uses PO #{$cleanNumber} for ".($existingOrder->customer?->company_name ?? 'Client').'.',
                ];
            }
        }

        // 3. For Invoice: check Invoice
        if ($docType === 'INVOICE') {
            $existingInv = Invoice::where('tenant_id', $tenantId)
                ->where('invoice_number', $cleanNumber)
                ->first();

            if ($existingInv) {
                return [
                    'is_duplicate' => true,
                    'source' => 'invoices',
                    'invoice_id' => $existingInv->id,
                    'invoice_number' => $existingInv->invoice_number,
                    'customer_name' => $existingInv->customer?->company_name ?? 'N/A',
                    'created_at' => $existingInv->created_at->format('d M Y'),
                    'message' => "Possible duplicate Invoice: Tax Invoice #{$cleanNumber} already exists in Accounting for ".($existingInv->customer?->company_name ?? 'Client').'.',
                ];
            }
        }

        return null;
    }

    /**
     * Suggest related Sales Orders, Shipments, and Invoices.
     */
    protected function suggestLinkedRecords(string $docType, string $docNumber, ?int $customerId, int $tenantId): array
    {
        $suggestions = [
            'sales_order_id' => null,
            'commercial_shipment_id' => null,
            'invoice_id' => null,
            'open_orders' => [],
            'open_shipments' => [],
            'open_invoices' => [],
        ];

        if ($customerId) {
            $orders = SalesOrder::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->orderByDesc('order_date')
                ->take(5)
                ->get();

            $suggestions['open_orders'] = $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'po_number' => $o->po_number,
                'total_amount' => (float) $o->total_amount,
                'status' => $o->status,
            ])->toArray();

            if ($orders->isNotEmpty()) {
                $suggestions['sales_order_id'] = $orders->first()->id;
            }

            $invoices = Invoice::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->orderByDesc('invoice_date')
                ->take(5)
                ->get();

            $suggestions['open_invoices'] = $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'total_amount' => (float) $inv->total_amount,
                'balance_due' => (float) $inv->balance_due,
                'status' => $inv->status,
            ])->toArray();

            if ($invoices->isNotEmpty()) {
                $suggestions['invoice_id'] = $invoices->first()->id;
            }
        }

        return $suggestions;
    }

    /**
     * Compute confidence score between 0 and 100%.
     */
    protected function calculateConfidenceScore(string $docNumber, ?array $matchedCustomer, array $items, array $amounts): int
    {
        $score = 20;

        if (! empty($docNumber) && ! str_starts_with($docNumber, 'DOC-') && ! str_starts_with($docNumber, 'PO-2026') && ! str_starts_with($docNumber, 'INV-2026')) {
            $score += 25;
        }

        if ($matchedCustomer && ($matchedCustomer['confidence'] ?? 0) >= 80) {
            $score += 30;
        } elseif ($matchedCustomer) {
            $score += 15;
        }

        if (! empty($items)) {
            $score += 15;
        }

        if ($amounts['total_amount'] > 0) {
            $score += 10;
        }

        return min(100, $score);
    }
}
