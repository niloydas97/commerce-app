<?php
include '../config.php';
include 'auth_check.php';

set_time_limit(120); // Give each batch 2 minutes
header('Content-Type: application/json');

$file_name = $_GET['file'] ?? '';
$offset = (int)($_GET['offset'] ?? 0);
$limit = (int)($_GET['limit'] ?? 50); // Process 50 rows at a time

$csv_file_path = '../images/' . basename($file_name);

if (empty($file_name) || !file_exists($csv_file_path)) {
    echo json_encode(['error' => 'Import file not found.']);
    exit;
}

// Function to download images
function downloadImage($url, $upload_dir = '../images') {
    if (empty($url)) return null;
    try {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $options = ['http' => ['method' => 'GET', 'header' => 'User-Agent: Mozilla/5.0']];
        $context = stream_context_create($options);
        $data = @file_get_contents($url, false, $context);
        if ($data === false) return null;
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($ext) || !in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'webp'])) $ext = 'jpg';
        $filename = 'img_' . uniqid() . '.' . $ext;
        file_put_contents($upload_dir . '/' . $filename, $data);
        return 'images/' . $filename; 
    } catch (Exception $e) {
        return null;
    }
}

$processed_count = 0;
$processed_handles = [];

try {
    $pdo->beginTransaction();
    
    $file = new SplFileObject($csv_file_path, 'r');
    $file->setFlags(SplFileObject::READ_CSV);

    $headers = $file->current(); 
    $col_map = array_flip($headers);
    
    $start_row = $offset + 1;
    $file->seek($start_row); 

    while (!$file->eof() && $processed_count < $limit) {
        $row = $file->current();
        if (empty($row) || $row[0] === null) {
            $file->next();
            continue;
        }
        
        $data = [];
        foreach ($col_map as $name => $index) {
            $data[$name] = $row[$index] ?? null;
        }

        $product_handle = $data['Handle'];
        if (empty($product_handle)) {
            $file->next();
            continue;
        }

        $product_id = null;
        
        // --- 1. Find or Create Parent Product ---
        if (!isset($processed_handles[$product_handle])) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE handle = ?");
            $stmt->execute([$product_handle]);
            $existing_product = $stmt->fetch();

            if ($existing_product) {
                $product_id = $existing_product['id'];
            } else {
                // This is a NEW product. Insert it.
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        handle, name, description, vendor, product_category, product_type, status, published, gift_card, 
                        seo_title, seo_description, google_product_category, google_gender, google_age_group, 
                        google_mpn, google_condition, google_custom_product, google_custom_label_0, 
                        google_custom_label_1, google_custom_label_2, google_custom_label_3, google_custom_label_4
                    ) VALUES (
                        :handle, :name, :desc, :vendor, :cat, :type, :status, :published, :gift_card, 
                        :seo_title, :seo_desc, :g_cat, :g_gender, :g_age, 
                        :g_mpn, :g_cond, :g_custom_prod, :g_label_0, 
                        :g_label_1, :g_label_2, :g_label_3, :g_label_4
                    )
                ");
                $stmt->execute([
                    ':handle' => $product_handle,
                    ':name' => $data['Title'],
                    ':desc' => $data['Body (HTML)'],
                    ':vendor' => $data['Vendor'],
                    ':cat' => $data['Product Category'],
                    ':type' => $data['Type'],
                    ':status' => $data['Status'],
                    ':published' => ($data['Published'] == 'true' || $data['Published'] == 'True' ? 1 : 0),
                    ':gift_card' => ($data['Gift Card'] == 'true' || $data['Gift Card'] == 'True' ? 1 : 0),
                    ':seo_title' => $data['SEO Title'],
                    ':seo_desc' => $data['SEO Description'],
                    ':g_cat' => $data['Google Shopping / Google Product Category'],
                    ':g_gender' => $data['Google Shopping / Gender'],
                    ':g_age' => $data['Google Shopping / Age Group'],
                    ':g_mpn' => $data['Google Shopping / MPN'],
                    ':g_cond' => $data['Google Shopping / Condition'],
                    ':g_custom_prod' => $data['Google Shopping / Custom Product'],
                    ':g_label_0' => $data['Google Shopping / Custom Label 0'],
                    ':g_label_1' => $data['Google Shopping / Custom Label 1'],
                    ':g_label_2' => $data['Google Shopping / Custom Label 2'],
                    ':g_label_3' => $data['Google Shopping / Custom Label 3'],
                    ':g_label_4' => $data['Google Shopping / Custom Label 4']
                ]);
                $product_id = $pdo->lastInsertId();
            }
            $processed_handles[$product_handle] = $product_id;
        } else {
            $product_id = $processed_handles[$product_handle];
        }

        // --- 2. Create Product Variant (if this row is a variant) ---
        $is_variant_row = !empty($data['Option1 Name']) || !empty($data['Variant SKU']);
        if ($is_variant_row) {
            $variant_name = trim(($data['Option1 Value'] ?? '') . ' ' . ($data['Option2 Value'] ?? '') . ' ' . ($data['Option3 Value'] ?? ''));
            if (empty($variant_name) || $variant_name == 'Default Title') $variant_name = $data['Title'];
            
            $variant_image_url = downloadImage($data['Variant Image']);

            $stmt = $pdo->prepare("
                INSERT INTO product_variants (
                    product_id, name, option1_name, option1_value, option2_name, option2_value, option3_name, option3_value,
                    sku, grams, inventory_tracker, stock, inventory_policy, fulfillment_service,
                    price, compare_at_price, requires_shipping, taxable, barcode, image_url,
                    weight_unit, tax_code, cost, unit_price_total_measure, unit_price_total_measure_unit,
                    unit_price_base_measure, unit_price_base_measure_unit
                ) VALUES (
                    :pid, :name, :o1n, :o1v, :o2n, :o2v, :o3n, :o3v,
                    :sku, :grams, :inv_tracker, :stock, :inv_policy, :fulfillment,
                    :price, :compare, :shipping, :taxable, :barcode, :image,
                    :weight_unit, :tax_code, :cost, :up_total, :up_total_unit,
                    :up_base, :up_base_unit
                )
            ");
            $stmt->execute([
                ':pid' => $product_id,
                ':name' => $variant_name,
                ':o1n' => $data['Option1 Name'],
                ':o1v' => $data['Option1 Value'],
                ':o2n' => $data['Option2 Name'],
                ':o2v' => $data['Option2 Value'],
                ':o3n' => $data['Option3 Name'],
                ':o3v' => $data['Option3 Value'],
                ':sku' => $data['Variant SKU'],
                ':grams' => (float)($data['Variant Grams'] ?? 0),
                ':inv_tracker' => $data['Variant Inventory Tracker'],
                ':stock' => (int)($data['Variant Inventory Qty'] ?? 0),
                ':inv_policy' => $data['Variant Inventory Policy'],
                ':fulfillment' => $data['Variant Fulfillment Service'],
                ':price' => (float)($data['Variant Price'] ?? 0),
                ':compare' => (float)($data['Variant Compare At Price'] ?? 0) > 0 ? (float)$data['Variant Compare At Price'] : null,
                ':shipping' => ($data['Variant Requires Shipping'] == 'true' || $data['Variant Requires Shipping'] == 'True' ? 1 : 0),
                ':taxable' => ($data['Variant Taxable'] == 'true' || $data['Variant Taxable'] == 'True' ? 1 : 0),
                ':barcode' => $data['Variant Barcode'],
                ':image' => $variant_image_url,
                ':weight_unit' => $data['Variant Weight Unit'],
                ':tax_code' => $data['Variant Tax Code'],
                ':cost' => (float)($data['Cost per item'] ?? 0),
                ':up_total' => (float)($data['Unit Price Total Measure'] ?? 0),
                ':up_total_unit' => $data['Unit Price Total Measure Unit'],
                ':up_base' => (float)($data['Unit Price Base Measure'] ?? 0),
                ':up_base_unit' => $data['Unit Price Base Measure Unit']
            ]);
        }
        
        // --- 3. Handle Product Images ---
        if (!empty($data['Image Src']) && !empty($data['Image Position'])) {
            $local_image = downloadImage($data['Image Src']);
            if ($local_image) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO product_images (product_id, image_url, position, alt_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$product_id, $local_image, (int)$data['Image Position'], $data['Image Alt Text']]);
            }
        }
        
        // --- 4. Handle Tags ---
        if (!empty($data['Tags'])) {
            $tags = explode(',', $data['Tags']);
            foreach ($tags as $tag_name) {
                $tag_name = trim($tag_name);
                if (empty($tag_name)) continue;
                
                $stmt = $pdo->prepare("SELECT id FROM tags WHERE name = ?");
                $stmt->execute([$tag_name]);
                $tag_id = $stmt->fetchColumn();
                if (!$tag_id) {
                    $stmt = $pdo->prepare("INSERT INTO tags (name) VALUES (?)");
                    $stmt->execute([$tag_name]);
                    $tag_id = $pdo->lastInsertId();
                }
                $stmt = $pdo->prepare("INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $tag_id]);
            }
        }

        // --- 5. Handle Metafields ---
        $metafield_columns = [
            'Checkout Blocks Rule Trigger (product.metafields.checkoutblocks.trigger)',
            'Google: Custom Product (product.metafields.mm-google-shopping.custom_product)',
            'Color (product.metafields.shopify.color-pattern)',
            'Play vehicle propulsion (product.metafields.shopify.play-vehicle-propulsion)',
            'Recommended age group (product.metafields.shopify.recommended-age-group)',
            'Skill level (product.metafields.shopify.skill-level)',
            'Suitable space (product.metafields.shopify.suitable-space)',
            'Toy figure features (product.metafields.shopify.toy-figure-features)',
            'Toy/Game material (product.metafields.shopify.toy-game-material)'
        ];

        $meta_stmt = $pdo->prepare("INSERT IGNORE INTO product_metafields (product_id, meta_namespace, meta_key, meta_value) VALUES (?, ?, ?, ?)");
        foreach ($metafield_columns as $col_name) {
            if (!empty($data[$col_name])) {
                preg_match('/product\.metafields\.(.+)\.(.+)\)/', $col_name, $matches);
                if ($matches) {
                    $namespace = $matches[1];
                    $key = $matches[2];
                    $value = $data[$col_name];
                    $meta_stmt->execute([$product_id, $namespace, $key, $value]);
                }
            }
        }

        $processed_count++;
        $file->next();
    }
    
    $pdo->commit();
    $file = null; 

    echo json_encode(['processed_count' => $processed_count]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Send back a detailed error message
    echo json_encode(['error' => 'SQL Error: ' . $e->getMessage(), 'processed_count' => $processed_count]);
    exit;
}
?>