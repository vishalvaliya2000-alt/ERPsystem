<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductCustomField;
use App\Models\ProductDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Official Indian GST Tariff & Agro-Dehydration HSN Master Database
     */
    public static array $hsnMasterDatabase = [
        // CHAPTER 07 - EDIBLE VEGETABLES & DEHYDRATED PRODUCTS
        [
            'hsn_code' => '07122000',
            'code_short' => '071220',
            'description' => 'Dehydrated Onions (Flakes, Kibbled, Powder, Minced, Chopped, Granules, Toasted, Fried)',
            'gst_rate' => 5.00,
            'category' => 'ONION',
            'keywords' => ['onion', 'white onion', 'pink onion', 'red onion', 'spring onion', 'toasted onion', 'fried onion', 'kibbled onion'],
        ],
        [
            'hsn_code' => '07129020',
            'code_short' => '071290',
            'description' => 'Dehydrated Garlic (Flakes, Powder, Minced, Chopped, Granules, Cloves, Toasted, Fried)',
            'gst_rate' => 5.00,
            'category' => 'GARLIC',
            'keywords' => ['garlic', 'garlic flakes', 'garlic powder', 'garlic minced', 'garlic chopped', 'garlic granules', 'garlic cloves'],
        ],
        [
            'hsn_code' => '07129090',
            'code_short' => '071290',
            'description' => 'Other Dehydrated / Dried Vegetables (Cabbage, Carrot, Beetroot, Spinach, Tomato, Potato, Green Chilli, Bitter Gourd, Mint)',
            'gst_rate' => 5.00,
            'category' => 'VEGETABLES',
            'keywords' => ['cabbage', 'carrot', 'beetroot', 'spinach', 'palak', 'tomato', 'potato', 'green chilli', 'chilli', 'beans', 'peas', 'bitter gourd', 'karela', 'mint', 'pudina', 'vegetable', 'vegetables'],
        ],
        [
            'hsn_code' => '07129010',
            'code_short' => '071290',
            'description' => 'Dried Mushrooms and Truffles (Whole, sliced, pieces or powder)',
            'gst_rate' => 5.00,
            'category' => 'VEGETABLES',
            'keywords' => ['mushroom', 'mushrooms', 'truffle', 'button mushroom', 'oyster mushroom'],
        ],
        [
            'hsn_code' => '07031010',
            'code_short' => '070310',
            'description' => 'Fresh Onions (Raw Mandi Goods - Nil GST)',
            'gst_rate' => 0.00,
            'category' => 'RAW_MATERIAL',
            'keywords' => ['fresh onion', 'raw onion', 'mandi onion'],
        ],
        [
            'hsn_code' => '07032000',
            'code_short' => '070320',
            'description' => 'Fresh Garlic (Raw Mandi Goods - Nil GST)',
            'gst_rate' => 0.00,
            'category' => 'RAW_MATERIAL',
            'keywords' => ['fresh garlic', 'raw garlic', 'mandi garlic'],
        ],

        // CHAPTER 09 - SPICES, HERBS & SEASONINGS
        [
            'hsn_code' => '09101210',
            'code_short' => '091012',
            'description' => 'Dried Ginger Powder (Sonth Powder) / Dried Ginger Flakes',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['ginger', 'dried ginger', 'sonth', 'adrak', 'ginger powder', 'ginger flakes'],
        ],
        [
            'hsn_code' => '09103030',
            'code_short' => '091030',
            'description' => 'Turmeric Powder (Haldi Powder) / Dry Turmeric Finger',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['turmeric', 'haldi', 'curcuma', 'turmeric powder', 'turmeric finger'],
        ],
        [
            'hsn_code' => '09092200',
            'code_short' => '090922',
            'description' => 'Coriander Powder / Crushed Coriander Seeds (Dhaniya)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['coriander', 'dhaniya', 'coriander powder', 'coriander seeds', 'coriander leaves'],
        ],
        [
            'hsn_code' => '09093200',
            'code_short' => '090932',
            'description' => 'Cumin Powder / Cumin Seeds (Jeera)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['cumin', 'jeera', 'cumin powder', 'cumin seeds'],
        ],
        [
            'hsn_code' => '09109914',
            'code_short' => '091099',
            'description' => 'Fenugreek Leaves (Kasuri Methi) / Fenugreek Powder',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['fenugreek', 'methi', 'kasuri methi', 'kasoori methi', 'fenugreek leaves', 'fenugreek powder'],
        ],
        [
            'hsn_code' => '09042211',
            'code_short' => '090422',
            'description' => 'Red Chilli Powder / Crushed Red Chilli Flakes',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['red chilli', 'chilli powder', 'paprika', 'chilli flakes', 'mirch'],
        ],
        [
            'hsn_code' => '09041200',
            'code_short' => '090412',
            'description' => 'Black Pepper Powder / Whole Black Pepper (Kali Mirch)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['black pepper', 'pepper powder', 'kali mirch', 'white pepper'],
        ],
        [
            'hsn_code' => '09083200',
            'code_short' => '090832',
            'description' => 'Cardamom Powder / Whole Green Cardamom (Elaichi)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['cardamom', 'elaichi', 'green cardamom'],
        ],
        [
            'hsn_code' => '09072000',
            'code_short' => '090720',
            'description' => 'Cloves Powder / Whole Cloves (Laung)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['clove', 'cloves', 'laung'],
        ],
        [
            'hsn_code' => '09109100',
            'code_short' => '091091',
            'description' => 'Mixed Seasonings & Spice Blends (Garam Masala / Curry Powder)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['mixed spices', 'garam masala', 'curry powder', 'seasoning spice'],
        ],

        // CHAPTER 11 & 12 - FLOURS & OIL SEEDS
        [
            'hsn_code' => '11063010',
            'code_short' => '110630',
            'description' => 'Flour of Dried Vegetables (Dehydrated Vegetable Flour)',
            'gst_rate' => 5.00,
            'category' => 'VEGETABLES',
            'keywords' => ['vegetable flour', 'onion flour', 'garlic flour'],
        ],
        [
            'hsn_code' => '12074090',
            'code_short' => '120740',
            'description' => 'Sesame Seeds (White / Black / Hulled - Til)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['sesame', 'til', 'sesame seeds', 'hulled sesame'],
        ],
        [
            'hsn_code' => '12075090',
            'code_short' => '120750',
            'description' => 'Mustard Seeds (Rai / Sarson)',
            'gst_rate' => 5.00,
            'category' => 'SPICES',
            'keywords' => ['mustard', 'rai', 'sarson', 'mustard seeds'],
        ],

        // CHAPTER 39, 48 & 63 - PACKAGING MATERIALS
        [
            'hsn_code' => '48191000',
            'code_short' => '481910',
            'description' => 'Corrugated Paper Boxes & Master Cartons',
            'gst_rate' => 18.00,
            'category' => 'PACKAGING',
            'keywords' => ['box', 'corrugated box', 'carton', 'master carton', 'paper box'],
        ],
        [
            'hsn_code' => '48192000',
            'code_short' => '481920',
            'description' => 'Multi-wall Laminated Paper Bags for Food Packaging',
            'gst_rate' => 18.00,
            'category' => 'PACKAGING',
            'keywords' => ['paper bag', 'laminated paper bag', 'kraft paper bag'],
        ],
        [
            'hsn_code' => '39232100',
            'code_short' => '392321',
            'description' => 'Polyethylene Liner Bags (LDPE / HM / Dual Poly Liners)',
            'gst_rate' => 18.00,
            'category' => 'PACKAGING',
            'keywords' => ['poly liner', 'plastic bag', 'liner bag', 'ldpe liner', 'hm liner', 'poly bag'],
        ],
        [
            'hsn_code' => '63053300',
            'code_short' => '630533',
            'description' => 'HDPE Woven Sacks / White Raffia Bags for Agro Exports',
            'gst_rate' => 12.00,
            'category' => 'PACKAGING',
            'keywords' => ['raffia', 'white raffia', 'hdpe bag', 'woven sack', 'pp bag'],
        ],
    ];

    public function index(Request $request)
    {
        $category = $request->query('category', 'ALL');
        $search = $request->query('q', '');

        $query = Product::with(['category', 'orderItems', 'documents'])->orderBy('product_code');

        if ($category !== 'ALL') {
            $query->whereHas('category', fn ($q) => $q->where('slug', strtolower($category)));
        }

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                    ->orWhere('product_code', 'like', "%{$search}%")
                    ->orWhere('hsn_code', 'like', "%{$search}%");
            });
        }

        $products = $query->get();
        $categories = ProductCategory::all();

        return view('products.index', compact('products', 'categories', 'category', 'search'));
    }

    public function show($id)
    {
        $product = Product::with(['category', 'documents.createdByUser', 'shares', 'priceSlabs', 'customValues.customField'])->findOrFail($id);
        $categories = ProductCategory::all();
        $photos = $product->documents->where('document_type', ProductDocument::TYPE_PHOTO)->sortBy('sort_order')->values();
        $documents = $product->documents->where('document_type', '!=', ProductDocument::TYPE_PHOTO)->sortBy(fn ($d) => [$d->document_type, ! $d->is_latest, -$d->id])->values();
        $customFields = ProductCustomField::all();
        $priceSlabs = $product->priceSlabs;

        return view('products.show', compact('product', 'categories', 'photos', 'documents', 'customFields', 'priceSlabs'));
    }

    /**
     * Search & Auto-Suggest HSN Codes for any commodity / query
     */
    public function hsnLookup(Request $request)
    {
        $q = strtolower(trim($request->query('q', '')));

        if (empty($q)) {
            // Return top common dehydration HSN codes by default
            return response()->json([
                'success' => true,
                'results' => array_slice(self::$hsnMasterDatabase, 0, 10),
            ]);
        }

        $matches = [];
        foreach (self::$hsnMasterDatabase as $item) {
            $score = 0;

            // Direct HSN code match
            if (str_starts_with($item['hsn_code'], $q) || str_starts_with($item['code_short'], $q)) {
                $score += 50;
            }

            // Keyword match
            foreach ($item['keywords'] as $kw) {
                if (str_contains($q, $kw) || str_contains($kw, $q)) {
                    $score += 30;
                }
            }

            // Description match
            if (stripos($item['description'], $q) !== false) {
                $score += 20;
            }

            if ($score > 0) {
                $item['score'] = $score;
                $item['suggested_sku'] = $this->generateStandardSku($q);
                $matches[] = $item;
            }
        }

        // Sort by relevance score
        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        return response()->json([
            'success' => true,
            'query' => $q,
            'count' => count($matches),
            'results' => array_slice($matches, 0, 8),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_code' => 'nullable|string|max:50',
            'category_id' => 'nullable|exists:product_categories,id',
            'new_category' => 'nullable|string|max:100',
            'hsn_code' => 'nullable|string|max:30',
            'default_packaging' => 'nullable|string|max:255',
            'packaging' => 'nullable|string|max:255',
            'current_rate_per_kg' => 'nullable|numeric|min:0',
            'standard_rate' => 'nullable|numeric|min:0',
            'tax_rate_percent' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = ! empty($validated['product_code']) ? strtoupper(trim($validated['product_code'])) : $this->generateStandardSku($validated['product_name']);
        $rate = $validated['current_rate_per_kg'] ?? ($validated['standard_rate'] ?? 0);
        $pack = $validated['default_packaging'] ?? ($validated['packaging'] ?? '20 KGs Two Poly Liner Bags with Laminated Paper Bag');
        $tax = ! empty($validated['tax_rate_percent']) ? (float) $validated['tax_rate_percent'] : 5.00;

        DB::beginTransaction();
        try {
            // Auto-assign Category if not explicitly provided
            $categoryId = $validated['category_id'] ?? null;
            if (empty($categoryId)) {
                $catSlug = $this->deriveCategorySlug($validated['product_name']);
                $catName = ucfirst($catSlug).' Products';
                $cat = ProductCategory::where('slug', $catSlug)
                    ->orWhere('name', $catName)
                    ->first();
                if (! $cat) {
                    $cat = ProductCategory::create([
                        'slug' => $catSlug,
                        'name' => $catName,
                    ]);
                }
                $categoryId = $cat->id;
            }

            $hsn = $validated['hsn_code'] ?: $this->deriveHsnCode($validated['product_name']);

            $product = Product::create([
                'product_code' => $code,
                'product_name' => $validated['product_name'],
                'category_id' => $categoryId,
                'hsn_code' => $hsn,
                'packaging' => $pack,
                'standard_rate' => $rate,
                'tax_rate' => $tax,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            ]);

            DB::commit();

            return back()->with('success', "✓ Product '{$product->product_name}' saved successfully with SKU {$code}!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create product: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to create product: '.$e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'product_code' => 'nullable|string|max:50',
            'hsn_code' => 'nullable|string|max:30',
            'default_packaging' => 'nullable|string|max:255',
            'packaging' => 'nullable|string|max:255',
            'current_rate_per_kg' => 'nullable|numeric|min:0',
            'standard_rate' => 'nullable|numeric|min:0',
            'tax_rate_percent' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = ! empty($validated['product_code']) ? strtoupper(trim($validated['product_code'])) : $product->product_code;
        $rate = $validated['current_rate_per_kg'] ?? ($validated['standard_rate'] ?? $product->standard_rate);
        $pack = $validated['default_packaging'] ?? ($validated['packaging'] ?? $product->packaging);
        $tax = ! empty($validated['tax_rate_percent']) ? (float) $validated['tax_rate_percent'] : ($product->tax_rate ?: 5.00);

        DB::beginTransaction();
        try {
            // Update Category dynamically based on name
            $catSlug = $this->deriveCategorySlug($validated['product_name']);
            $catName = ucfirst($catSlug).' Products';
            $cat = ProductCategory::where('slug', $catSlug)
                ->orWhere('name', $catName)
                ->first();
            if (! $cat) {
                $cat = ProductCategory::create([
                    'slug' => $catSlug,
                    'name' => $catName,
                ]);
            }

            $product->update([
                'product_code' => $code,
                'product_name' => $validated['product_name'],
                'category_id' => $cat->id,
                'hsn_code' => $validated['hsn_code'] ?: ($product->hsn_code ?: $this->deriveHsnCode($validated['product_name'])),
                'packaging' => $pack,
                'standard_rate' => $rate,
                'tax_rate' => $tax,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $product->is_active,
            ]);

            DB::commit();

            return back()->with('success', "✓ Product '{$product->product_name}' updated successfully!");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to update product #{$id}: ".$e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Failed to update product: '.$e->getMessage());
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $name = $product->product_name;

        if ($product->orderItems()->count() > 0) {
            return back()->with('error', "Cannot delete '{$name}' because it has historical sales order line items.");
        }

        DB::beginTransaction();
        try {
            $product->delete();
            DB::commit();

            return back()->with('success', "✓ Product '{$name}' deleted successfully.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Failed to delete product #{$id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            return back()->with('error', 'Failed to delete product: '.$e->getMessage());
        }
    }

    /**
     * Smart Standard Export Acronym Generator
     */
    public function generateStandardSku(string $name): string
    {
        $n = strtolower(trim($name));
        $sku = 'D'; // Dehydrated by default

        // Treatment
        if (str_contains($n, 'toasted') || str_contains($n, 'toast')) {
            $sku .= 'T';
        } elseif (str_contains($n, 'fried')) {
            $sku .= 'F';
        }

        // Specific Commodities
        if (str_contains($n, 'spring onion')) {
            $sku .= 'SO';
        } elseif (str_contains($n, 'white onion') || (str_contains($n, 'white') && str_contains($n, 'onion'))) {
            $sku .= 'WO';
        } elseif (str_contains($n, 'pink onion') || (str_contains($n, 'pink') && str_contains($n, 'onion'))) {
            $sku .= 'PO';
        } elseif (str_contains($n, 'red onion') || (str_contains($n, 'red') && str_contains($n, 'onion'))) {
            $sku .= 'RO';
        } elseif (str_contains($n, 'garlic')) {
            $sku .= 'G';
        } elseif (str_contains($n, 'onion')) {
            $sku .= 'O';
        } elseif (str_contains($n, 'cabbage')) {
            $sku = 'DCAB';
        } elseif (str_contains($n, 'carrot')) {
            $sku = 'DCAR';
        } elseif (str_contains($n, 'beetroot') || str_contains($n, 'beet')) {
            $sku = 'DBEET';
        } elseif (str_contains($n, 'spinach') || str_contains($n, 'palak')) {
            $sku = 'DSPIN';
        } elseif (str_contains($n, 'tomato')) {
            $sku = 'DTOM';
        } elseif (str_contains($n, 'ginger') || str_contains($n, 'sonth')) {
            $sku = 'DGIN';
        } elseif (str_contains($n, 'green chilli') || str_contains($n, 'chilli')) {
            $sku = 'DGC';
        } elseif (str_contains($n, 'kasuri methi') || str_contains($n, 'methi')) {
            $sku = 'DKM';
        } elseif (str_contains($n, 'coriander') || str_contains($n, 'dhaniya')) {
            $sku = 'DCOR';
        } elseif (str_contains($n, 'mint') || str_contains($n, 'pudina')) {
            $sku = 'DMINT';
        }

        // Cut / Granulation Suffix
        if (str_contains($n, 'powder')) {
            $sku .= str_contains($sku, '-') ? 'P' : (str_ends_with($sku, 'P') ? '' : (in_array($sku, ['DCAB', 'DCAR', 'DBEET', 'DSPIN', 'DTOM', 'DGIN', 'DGC', 'DKM', 'DCOR', 'DMINT']) ? '-P' : 'P'));
        } elseif (str_contains($n, 'flake') || str_contains($n, 'kibbled') || str_contains($n, 'clove') || str_contains($n, 'leaves')) {
            $sku .= str_contains($sku, '-') ? 'F' : (in_array($sku, ['DCAB', 'DCAR', 'DBEET', 'DSPIN', 'DTOM', 'DGIN', 'DGC', 'DKM', 'DCOR', 'DMINT']) ? '-F' : 'F');
        } elseif (str_contains($n, 'minced')) {
            $sku .= in_array($sku, ['DCAB', 'DCAR', 'DBEET']) ? '-M' : 'M';
        } elseif (str_contains($n, 'chopped')) {
            $sku .= in_array($sku, ['DCAB', 'DCAR', 'DBEET']) ? '-C' : 'C';
        } elseif (str_contains($n, 'granule')) {
            $sku .= in_array($sku, ['DCAB', 'DCAR', 'DBEET']) ? '-G' : 'G';
        }

        // Particle Size / Mesh Suffix
        if (preg_match('/(100|80|60|40)\s*mesh/i', $name, $m)) {
            $sku .= '-'.$m[1];
        } elseif (preg_match('/([0-9]+)\s*-\s*([0-9]+)\s*mm/i', $name, $mm)) {
            $sku .= '-'.$mm[1].'-'.$mm[2];
        } elseif (str_contains($n, '100')) {
            $sku .= '-100';
        }

        return strtoupper(trim($sku));
    }

    public function deriveCategorySlug(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'garlic')) {
            return 'garlic';
        }
        if (str_contains($n, 'onion')) {
            return 'onion';
        }
        if (str_contains($n, 'cabbage') || str_contains($n, 'carrot') || str_contains($n, 'beet') || str_contains($n, 'spinach') || str_contains($n, 'tomato') || str_contains($n, 'potato') || str_contains($n, 'vegetable')) {
            return 'vegetables';
        }
        if (str_contains($n, 'ginger') || str_contains($n, 'turmeric') || str_contains($n, 'coriander') || str_contains($n, 'cumin') || str_contains($n, 'methi') || str_contains($n, 'chilli') || str_contains($n, 'pepper') || str_contains($n, 'spice')) {
            return 'spices';
        }
        if (str_contains($n, 'box') || str_contains($n, 'bag') || str_contains($n, 'liner') || str_contains($n, 'raffia') || str_contains($n, 'pouch')) {
            return 'packaging';
        }

        return 'general';
    }

    public function deriveHsnCode(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'onion')) {
            return '07122000';
        }
        if (str_contains($n, 'garlic')) {
            return '07129020';
        }
        if (str_contains($n, 'cabbage') || str_contains($n, 'carrot') || str_contains($n, 'beet') || str_contains($n, 'spinach') || str_contains($n, 'tomato') || str_contains($n, 'vegetable')) {
            return '07129090';
        }
        if (str_contains($n, 'ginger')) {
            return '09101210';
        }
        if (str_contains($n, 'turmeric')) {
            return '09103030';
        }
        if (str_contains($n, 'coriander')) {
            return '09092200';
        }
        if (str_contains($n, 'cumin')) {
            return '09093200';
        }
        if (str_contains($n, 'methi')) {
            return '09109914';
        }
        if (str_contains($n, 'chilli')) {
            return '09042211';
        }
        if (str_contains($n, 'box') || str_contains($n, 'carton')) {
            return '48191000';
        }
        if (str_contains($n, 'liner') || str_contains($n, 'poly')) {
            return '39232100';
        }

        return '07129090';
    }
}
